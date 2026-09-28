<?php

declare(strict_types=1);

use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$endpoint = $argv[1] ?? 'http://127.0.0.1:4318/v1/traces';
$transport = (new OtlpHttpTransportFactory())->create($endpoint, 'application/x-protobuf');
$provider = new TracerProvider(new SimpleSpanProcessor(new SpanExporter($transport)));
$span = $provider
    ->getTracer('beacon.release.validation', \Beacon\PHP\Version::VERSION)
    ->spanBuilder('beacon.release.validation')
    ->startSpan();

$span->setAttribute('beacon.release.version', \Beacon\PHP\Version::VERSION);
$span->end();

if (!$provider->shutdown()) {
    fwrite(STDERR, "OTLP exporter shutdown failed.\n");
    exit(1);
}

fwrite(STDOUT, "Exported beacon.release.validation span.\n");
