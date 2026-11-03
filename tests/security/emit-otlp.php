<?php

declare(strict_types=1);

require $argv[1] ?? dirname(__DIR__, 2) . '/vendor/autoload.php';

$_GET = ['q' => 'PRIVATE_OTLP_VALUE_91e2'];
$span = \OpenTelemetry\API\Globals::tracerProvider()->getTracer('beacon.security.validation')
    ->spanBuilder('beacon.security.validation')->startSpan();
$scope = $span->activate();
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->query("SELECT 'PRIVATE_OTLP_VALUE_91e2'");
} finally {
    $scope->detach();
    $span->end();
}
// Security's shutdown callback emits the runtime SBOM before SDK shutdown/flush.
