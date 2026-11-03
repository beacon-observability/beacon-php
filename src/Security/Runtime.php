<?php

declare(strict_types=1);

namespace Beacon\PHP\Security;

/** Native hooks remain in beacon-php-instrumentation; these are Beacon-only observers. */
final class Runtime
{
    private static ?self $instance = null;
    private bool $busy = false;
    private bool $closed = false;
    private Events $events;
    private Collector $collector;
    private int $failedHooks = 0;

    public static function boot(): void
    {
        if (self::$instance !== null || !Contract::enabled('BEACON_SECURITY_ENABLED')
            || Contract::enabled('OTEL_SDK_DISABLED') || PHP_SAPI === 'phpdbg') {
            return;
        }
        try {
            self::$instance = new self();
        } catch (\Throwable) {
            // Instrumentation must never fail application startup or reveal application data.
        }
    }

    private function __construct()
    {
        $this->busy = true;
        $available = function_exists('OpenTelemetry\\Instrumentation\\hook');
        if ($available) {
            $this->installHooks();
        }
        $this->events = new Events();
        $this->collector = new Collector($this->events);
        $this->events->emit('beacon.security.health', [
            'spec_commit' => Contract::SPEC_COMMIT, 'fingerprint_version' => Contract::FINGERPRINT_VERSION,
            'hooks_available' => $available, 'failed_hooks' => $this->failedHooks,
            'coverage' => 'php_builtin_value_correlation', 'coverage_gaps' => Collector::GAPS,
            'delivery' => 'otel_api_only_no_receiver_acknowledgement',
        ]);
        $this->busy = false;
        // Register after provider initialization; emit SBOM before the SDK shutdown callback
        // by using OTel's shutdown handler, whose callbacks execute in reverse order.
        \OpenTelemetry\SDK\Common\Util\ShutdownHandler::register([$this, 'shutdown']);
    }

    private function installHooks(): void
    {
        foreach (['query', 'exec', 'prepare'] as $method) {
            $this->hook('PDO', $method, 0, 'sql_injection', 'sql_template');
        }
        foreach (['query', 'real_query', 'multi_query', 'prepare'] as $method) {
            $this->hook('mysqli', $method, 0, 'sql_injection', 'sql_template');
            $this->hook(null, 'mysqli_' . $method, 1, 'sql_injection', 'sql_template');
        }
        foreach (['exec', 'system', 'passthru', 'shell_exec', 'popen'] as $function) {
            $this->hook(null, $function, 0, 'command_execution', 'shell_script');
        }
        $this->hook(null, 'proc_open', 0, 'command_execution', 'shell_script');
        foreach (['file_get_contents', 'fopen', 'readfile', 'file'] as $function) {
            $this->hook(null, $function, 0, 'path_traversal', 'file_path', 'read', 'source');
        }
        $this->hook(null, 'file_put_contents', 0, 'path_traversal', 'file_path', 'write', 'target');
        $this->hook(null, 'unlink', 0, 'path_traversal', 'file_path', 'delete', 'target');
        foreach (['copy', 'rename'] as $function) {
            $this->hook(null, $function, 0, 'path_traversal', 'file_path', $function, 'source');
            $this->hook(null, $function, 1, 'path_traversal', 'file_path', $function, 'target');
        }
        $this->hook(null, 'curl_exec', -1, 'ssrf', 'destination_address');
    }

    private function hook(?string $class, string $function, int $argument, string $rule, string $role, string $operation = '', string $pathRole = ''): void
    {
        $name = ($class === null ? '' : $class . '::') . $function;
        $ok = \OpenTelemetry\Instrumentation\hook($class, $function, function (mixed $object, array $params) use ($name, $function, $argument, $rule, $role, $operation, $pathRole): void {
            if ($this->busy || $this->closed) {
                return;
            }
            $this->busy = true;
            try {
                $value = $argument < 0
                    ? (isset($params[0]) && $params[0] instanceof \CurlHandle ? curl_getinfo($params[0], CURLINFO_EFFECTIVE_URL) : null)
                    : ($params[$argument] ?? null);
                $actualRole = $role;
                // proc_open's argv form bypasses the shell: observe only its executable.
                if ($function === 'proc_open' && is_array($value)) {
                    $value = $value[0] ?? null;
                    $actualRole = 'executable';
                }
                if (is_string($value) && in_array($function, ['file_get_contents', 'fopen', 'readfile', 'file'], true)
                    && preg_match('/\Ahttps?:\/\//i', $value)) {
                    $this->collector->observe($name, $value, 'ssrf', 'destination_address');
                } else {
                    $actualOperation = $operation;
                    $actualPathRole = $pathRole;
                    if ($function === 'fopen') {
                        $mode = $params[1] ?? '';
                        $actualOperation = is_string($mode) && strpbrk($mode, 'waxc+') !== false ? 'write' : 'read';
                        $actualPathRole = $actualOperation === 'read' ? 'source' : 'target';
                    }
                    $this->collector->observe($name, $value, $rule, $actualRole, $actualOperation, $actualPathRole);
                }
            } catch (\Throwable) {
                // Hook arguments, return values, exceptions and application execution are untouched.
            } finally {
                $this->busy = false;
            }
        });
        if (!$ok) {
            ++$this->failedHooks;
        }
    }

    public function shutdown(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->busy = true;
        try {
            if (Contract::enabled('BEACON_SECURITY_SBOM_ENABLED', true)) {
                try {
                    Sbom::snapshot($this->events);
                } catch (\Throwable) {
                    $this->events->emit('beacon.security.sbom.update_failed', ['reason' => 'runtime_snapshot_failed'], 'beacon_security_sbom');
                }
            }
            $this->events->emit('beacon.security.health', [
                'phase' => 'shutdown', 'api_calls' => $this->events->apiCalls, 'api_failures' => $this->events->failures,
                'budget_dropped' => $this->events->dropped, 'delivery' => 'otel_api_only_no_receiver_acknowledgement',
            ]);
        } catch (\Throwable) {
        }
    }
}
