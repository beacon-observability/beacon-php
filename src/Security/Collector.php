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
        ++$this->findings;
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
        $context = Span::getCurrent()->getContext();
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $this->events->emit('beacon.security.finding', [
            'evidence_id' => 'ev-' . bin2hex(random_bytes(16)), 'finding_id' => $fingerprint,
            'fingerprint_version' => 1, 'rule' => $rule,
            'assessment' => $sources === [] ? 'observation' : 'candidate_risk', 'validation' => 'unvalidated',
            'severity' => 'unknown', 'confidence' => 'conservative_flow', 'precision' => 'conservative',
            'execution_observation' => 'invocation_attempt',
            'trace_id' => $context->isValid() ? $context->getTraceId() : '',
            'current_span_id' => $context->isValid() ? $context->getSpanId() : '',
            'server_span_id' => '', 'trace_flags' => $context->getTraceFlags(),
            'sources' => $sources, 'propagation' => [], 'ranges' => $ranges, 'sink' => $sink,
            'truncated' => $this->reportedGaps !== [], 'coverage' => 'php_builtin_value_correlation',
            'coverage_gaps' => [...self::GAPS, ...array_keys($this->reportedGaps)],
            'request' => ['method' => in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'], true) ? $method : '',
                'route' => '', 'route_status' => 'unavailable', 'status_code' => null,
                'started_at' => null, 'ended_at' => null, 'framework' => '', 'transport' => PHP_SAPI, 'error_type' => ''],
            'component' => ['status' => 'unresolved', 'sbom_id' => '', 'revision' => null, 'release_id' => '',
                'application_id' => $this->events->applicationId(), 'bom-ref' => '', 'reason' => 'sink_component_not_resolved',
                'observed_url' => '', 'query' => ''], 'stack' => $stack,
        ]);
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
        $this->events->emit('beacon.security.collection.incomplete', [
            'truncated' => true, 'coverage_gaps' => [$reason], 'collection_status' => 'partial',
            'counts' => ['objects' => 0, 'nodes' => 0, 'sources' => 0, 'findings' => $this->findings, 'retained_bytes' => 0],
        ]);
    }
}
