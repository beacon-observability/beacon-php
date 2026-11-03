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

Install and enable the matching Beacon PHP Instrumentation extension first. Beacon PHP is distributed
through immutable GitHub Release ZIP assets; it is not currently published on Packagist. Composer
must be configured with the downloaded release directory before requiring the Agent. The Composer
artifact repository requires PHP's `zip` extension (in addition to the Agent's runtime requirements).

Download the ZIP, license and checksum file from the same release and verify them:

```bash
beacon_version=1.2.1
beacon_release_dir=/absolute/path/to/beacon-release
mkdir -p "${beacon_release_dir}"
for asset in "beacon-php-${beacon_version}.zip" LICENSE SHA256SUMS; do
  curl --fail --location \
    --output "${beacon_release_dir}/${asset}" \
    "https://github.com/beacon-observability/beacon-php/releases/download/v${beacon_version}/${asset}"
done
(cd "${beacon_release_dir}" && sha256sum --check SHA256SUMS)
```

Use an equivalent SHA-256 verifier on systems without `sha256sum`. Keep the release directory
available while Composer installs the package. From the application project:

```bash
cd /path/to/application
composer config repositories.beacon artifact "${beacon_release_dir}"
composer require "beacon-observability/beacon-php:${beacon_version}"
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

## Security (experimental, opt-in)

Enable runtime Security observations and runtime SBOM using the same OpenTelemetry SDK and
OTLP Logs pipeline as the application:

```bash
export BEACON_SECURITY_ENABLED=true
export OTEL_PHP_AUTOLOAD_ENABLED=true
export OTEL_SERVICE_NAME=my-php-service
export OTEL_LOGS_EXPORTER=otlp
export OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
export OTEL_EXPORTER_OTLP_ENDPOINT=http://127.0.0.1:4318
```

Require the application's `vendor/autoload.php` before application calls. Security is disabled by
default, and enabling it does not write local files. The Collector must enable an OTLP **logs**
pipeline. Findings are conservative runtime observations, not confirmed vulnerabilities.
See [Security coverage, configuration and validation](docs/security.md) before enabling it.

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
