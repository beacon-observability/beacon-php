<?php

declare(strict_types=1);

namespace Beacon\PHP\Standalone;

use Beacon\PHP\Version;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Common\Util\ShutdownHandler;

final class Runtime
{
    public static function boot(): void
    {
        if (empty($_SERVER['REQUEST_METHOD']) || self::hasFramework()) { return; }
        $method = $_SERVER['REQUEST_METHOD'];
        $method = in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'], true) ? $method : 'HTTP';
        $headers = [];
        foreach (['traceparent', 'tracestate', 'baggage'] as $header) {
            if (isset($_SERVER['HTTP_' . strtoupper($header)])) { $headers[$header] = $_SERVER['HTTP_' . strtoupper($header)]; }
        }
        $parent = Globals::propagator()->extract($headers);
        $span = Globals::tracerProvider()->getTracer('io.beacon.php.http', Version::VERSION)->spanBuilder($method)
            ->setSpanKind(SpanKind::KIND_SERVER)->setParent($parent)
            ->setAttribute('http.request.method', $method)
            ->setAttribute('url.path', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/')
            ->startSpan();
        $scope = $span->activate();
        ShutdownHandler::register(static function () use ($span, $scope): void {
            $status = http_response_code();
            if ($status !== false) { $span->setAttribute('http.response.status_code', $status); }
            $scope->detach();
            $span->end();
        });
    }

    private static function hasFramework(): bool
    {
        $directory = dirname($_SERVER['SCRIPT_FILENAME'] ?? getcwd());
        for ($i = 0; $i < 8; ++$i) {
            $path = $directory . '/composer.lock';
            if (is_file($path)) {
                $lock = json_decode((string) file_get_contents($path), true);
                foreach ($lock['packages'] ?? [] as $package) {
                    if (in_array($package['name'], ['laravel/framework', 'symfony/http-kernel'], true)) { return true; }
                }
                return false;
            }
            $parent = dirname($directory);
            if ($parent === $directory) { break; }
            $directory = $parent;
        }
        return false;
    }
}
