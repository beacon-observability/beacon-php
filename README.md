# Beacon PHP

Beacon PHP is a lightweight OpenTelemetry distribution for PHP applications. It combines the Beacon native hook extension with the official OpenTelemetry PHP SDK, OTLP exporter, and application-selected official auto-instrumentation packages.

This repository does **not** vendor or mirror the OpenTelemetry PHP Contrib monorepo. Framework, database, and client instrumentation is installed directly from the official `open-telemetry/*` Composer packages. Beacon-specific fixes should be contributed upstream first; a temporary, narrowly scoped Beacon package is allowed only when an urgent fix cannot wait for an upstream release.

## Architecture

| Layer | Responsibility |
| --- | --- |
| [`beacon-php-instrumentation`](https://github.com/beacon-observability/beacon-php-instrumentation) | Native `zend_observer` hook engine and platform binaries |
| `beacon-observability/beacon-php` | Agent dependencies, component selection, diagnostics, packaging, and integration tests |
| Official `open-telemetry/opentelemetry-auto-*` packages | Laravel, Symfony, PDO, Guzzle, and other component spans |

There is no `beacon-php-contrib` repository or full downstream copy of OpenTelemetry PHP Contrib.

## Installation

Install and enable the matching Beacon PHP Instrumentation extension first, then add the Agent package to the application:

```bash
composer require beacon-observability/beacon-php
vendor/bin/beacon-php components
vendor/bin/beacon-php install laravel guzzle pdo
```

The `install` command modifies the current Composer project and accepts only aliases from the shipped component manifest. Use `--dry-run` to inspect the Composer command without changing the project.

Enable the official OpenTelemetry SDK autoloader and configure OTLP using standard environment variables:

```bash
export OTEL_PHP_AUTOLOAD_ENABLED=true
export OTEL_SERVICE_NAME=my-php-service
export OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
export OTEL_EXPORTER_OTLP_ENDPOINT=http://127.0.0.1:4318
vendor/bin/beacon-php doctor
```

`doctor --json` and `components --json` provide machine-readable output. Diagnostics never print OTLP headers or credentials.

## Development

The release validation and prebuilt extension matrix covers PHP 8.2, 8.3, and 8.4. From the repository root:

```bash
composer install
composer test
php beacon/scripts/check-project.php
```

See [the project boundary](beacon/README.md), [upstream component policy](beacon/UPSTREAM.md), and [release process](beacon/RELEASING.md).

## License

Apache License 2.0. See [LICENSE](LICENSE).
