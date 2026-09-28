<?php

declare(strict_types=1);

namespace Beacon\PHP;

final class Diagnostics
{
    /** @return array{version: string, checks: list<array{name: string, status: string, detail: string}>, components: array<string, array{package: string, installed: bool, version: ?string}>} */
    public static function collect(): array
    {
        $extensionVersion = phpversion('opentelemetry');
        $distributionConstant = 'OpenTelemetry\\Instrumentation\\BEACON_DISTRIBUTION';
        $extensionDistribution = defined($distributionConstant) ? constant($distributionConstant) : null;
        $endpoint = getenv('OTEL_EXPORTER_OTLP_ENDPOINT');
        $serviceName = getenv('OTEL_SERVICE_NAME');
        $autoload = getenv('OTEL_PHP_AUTOLOAD_ENABLED');

        return [
            'version' => Version::VERSION,
            'checks' => [
                self::check('PHP >= 8.2', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION),
                self::check('ext-opentelemetry loaded', extension_loaded('opentelemetry'), $extensionVersion ?: 'not loaded'),
                self::check('ext-opentelemetry >= 1.4.2', is_string($extensionVersion) && version_compare($extensionVersion, '1.4.2', '>='), $extensionVersion ?: 'not loaded'),
                self::check('Beacon extension distribution', $extensionDistribution === 'Beacon', is_string($extensionDistribution) ? $extensionDistribution : 'not detected'),
                self::check('OpenTelemetry API installed', interface_exists(\OpenTelemetry\API\Trace\TracerProviderInterface::class)),
                self::check('OpenTelemetry SDK installed', class_exists(\OpenTelemetry\SDK\Trace\TracerProvider::class)),
                self::check('OTLP exporter installed', class_exists(\OpenTelemetry\Contrib\Otlp\SpanExporter::class)),
                self::warning('OTEL_PHP_AUTOLOAD_ENABLED', self::isTruthy($autoload), $autoload ?: 'not set'),
                self::warning('OTEL_EXPORTER_OTLP_ENDPOINT', is_string($endpoint) && $endpoint !== '', $endpoint ? 'configured' : 'not set'),
                self::warning('OTEL_SERVICE_NAME', is_string($serviceName) && $serviceName !== '', $serviceName ?: 'not set'),
            ],
            'components' => ComponentRegistry::withInstallationStatus(),
        ];
    }

    /** @param array{checks: list<array{name: string, status: string, detail: string}>} $report */
    public static function hasFailures(array $report): bool
    {
        foreach ($report['checks'] as $check) {
            if ($check['status'] === 'FAIL') {
                return true;
            }
        }
        return false;
    }

    /** @param array{version: string, checks: list<array{name: string, status: string, detail: string}>, components: array<string, array{package: string, installed: bool, version: ?string}>} $report @param resource $stream */
    public static function render(array $report, $stream): void
    {
        fprintf($stream, "Beacon PHP %s\n", $report['version']);
        foreach ($report['checks'] as $check) {
            fprintf($stream, "[%s] %s: %s\n", $check['status'], $check['name'], $check['detail']);
        }

        $installed = array_filter($report['components'], static fn (array $component): bool => $component['installed']);
        if ($installed === []) {
            fwrite($stream, "[WARN] No component instrumentation installed. Run `beacon-php components`.\n");
            return;
        }
        foreach ($installed as $alias => $component) {
            fprintf($stream, "[OK] component:%s: %s\n", $alias, $component['version'] ?? 'installed');
        }
    }

    /** @return array{name: string, status: string, detail: string} */
    private static function check(string $name, bool $passed, string $detail = ''): array
    {
        return ['name' => $name, 'status' => $passed ? 'OK' : 'FAIL', 'detail' => $detail !== '' ? $detail : ($passed ? 'available' : 'missing')];
    }

    /** @return array{name: string, status: string, detail: string} */
    private static function warning(string $name, bool $passed, string $detail): array
    {
        return ['name' => $name, 'status' => $passed ? 'OK' : 'WARN', 'detail' => $detail];
    }

    private static function isTruthy(string|false $value): bool
    {
        return is_string($value) && in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
    }

    private function __construct()
    {
    }
}
