<?php

declare(strict_types=1);

namespace Beacon\PHP\Security;

use OpenTelemetry\API\Trace\Span;

/** Conservative value correlation, not VM taint tracking or exploit confirmation. */
final class Collector
{
    public const GAPS = ['value_correlation_not_taint_tracking', 'transformed_inputs_not_tracked',
        'framework_routes_and_bodies_not_instrumented', 'sanitizers_not_modeled', 'source_values_may_coincide'];
    private int $findings = 0;
    private array $seen = [];
    private array $reportedGaps = [];

    public function __construct(private readonly Events $events)
    {
    }

    public function observe(string $function, mixed $value, string $rule, string $role, string $operation = '', string $pathRole = ''): void
    {
        if (!is_string($value) || $value === '') {
            return;
        }
        if (strlen($value) > 65536 || $this->findings >= 64) {
            $this->gap('sink_or_finding_budget');
            return;
        }
        $sources = [];
        $ranges = [];
        $nodes = 0;
        foreach (['_GET' => 'http.request.query', '_POST' => 'http.request.body', '_COOKIE' => 'http.request.cookie'] as $global => $type) {
            $this->scan($GLOBALS[$global] ?? [], $global, $type, $value, $sources, $ranges, $nodes);
        }
        // Command execution is still an observation when no modeled request source matches.
        if ($sources === [] && $rule !== 'command_execution') {
            return;
        }
        $stack = [];
        $location = '';
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12) as $frame) {
            $file = $frame['file'] ?? '';
            if ($file === '' || str_starts_with($file, __DIR__ . '/')) {
                continue;
            }
            // PHP filenames can be stream URLs; never export URL credentials or queries.
            if (str_contains($file, '://')) {
                $file = '[stream]';
            }
            $stack[] = $file . ':' . ($frame['line'] ?? 0);
            $location = $location ?: end($stack);
        }
        $sink = ['function' => $function, 'role' => $role, 'location' => $location,
            'operation' => $operation, 'path_role' => $pathRole, 'input_part' => ''];
        $fingerprint = Contract::fingerprint(['application_id' => $this->events->applicationId(),
            'language' => 'php', 'rule' => $rule, 'sink' => $sink,
            'source_signatures' => array_map(static fn (array $s): string => $s['type'] . '|' . $s['name'], $sources)]);
        if (isset($this->seen[$fingerprint])) { return; }
        $this->seen[$fingerprint] = true;
        ++$this->findings;
        $span = Span::getCurrent();
        $context = $span->getContext();
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $payload = [
            'occurrence_id' => Contract::uuid(), 'finding_id' => $fingerprint,
            'fingerprint_version' => 1, 'rule' => $rule,
            'precision' => 'conservative', 'attack_evidence' => ['status' => 'unknown', 'reason' => 'approximate_flow_without_policy'],
            'evidence_sampled' => true, 'collection_complete' => false,
            'sink' => array_filter($sink, static fn (string $value): bool => $value !== ''),
            'coverage_gaps' => [...self::GAPS, ...array_keys($this->reportedGaps)],
        ];
        foreach (['sources' => $sources, 'ranges' => $ranges, 'stack' => $stack] as $key => $value) {
            if ($value !== []) { $payload[$key] = $value; }
        }
        if (in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'], true)) {
            $payload['request'] = ['method' => $method];
        }
        if ($this->reportedGaps !== []) {
            $payload['truncated'] = true;
            $payload['truncation_reason'] = 'collection_budget';
        }
        if ($context->isValid()) {
            $payload['trace_id'] = $context->getTraceId();
            $payload['span_id'] = $context->getSpanId();
            $payload['trace_flags'] = $context->getTraceFlags();
        }
        $this->events->emit('beacon.security.finding', $payload, context: $span->storeInContext(\OpenTelemetry\Context\Context::getRoot()));
    }

    private function scan(array $values, string $path, string $type, string $sink, array &$sources, array &$ranges, int &$nodes, int $depth = 0): void
    {
        foreach ($values as $key => $value) {
            if (++$nodes > 128 || count($sources) >= 64) {
                $this->gap('source_node_budget');
                return;
            }
            // Only structural keys are emitted. Dynamic or oversized keys are suppressed.
            $key = (string) $key;
            $name = preg_match('/\A[a-zA-Z_][a-zA-Z0-9_.-]{0,63}\z/D', $key) ? $key : '[key]';
            $location = substr($path . '[' . $name . ']', 0, 256);
            if (is_array($value)) {
                if ($depth >= 4) {
                    $this->gap('source_depth_budget');
                } else {
                    $this->scan($value, $location, $type, $sink, $sources, $ranges, $nodes, $depth + 1);
                }
                continue;
            }
            if (!is_string($value) || $value === '') {
                continue;
            }
            if (strlen($value) > 4096) {
                $this->gap('source_value_budget');
                continue;
            }
            $start = strpos($sink, $value);
            if ($start === false) {
                continue;
            }
            $id = 'src-' . (count($sources) + 1);
            $sources[] = ['id' => $id, 'type' => $type, 'name' => $location,
                'location' => $location, 'value_type' => 'string', 'value_length' => strlen($value)];
            $ranges[] = ['source_id' => $id, 'start' => $start, 'end' => $start + strlen($value),
                'exact' => false, 'unit' => 'byte'];
        }
    }

    private function gap(string $reason): void
    {
        if (isset($this->reportedGaps[$reason])) {
            return;
        }
        $this->reportedGaps[$reason] = true;
    }
}
