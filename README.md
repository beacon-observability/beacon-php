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

Use the versioned Beacon PHP installer. It selects the matching native extension, verifies archive
checksums, installs the prebuilt Agent runtime, configures PHP startup, and runs diagnostics.
Standalone installation requires no Composer and leaves application Composer files unchanged.

```bash
beacon_version=1.3.0
curl --fail --location --output beacon-setup.php \
  "https://github.com/beacon-observability/beacon-php/releases/download/v${beacon_version}/beacon-setup.php"
php beacon-setup.php --php-bin=all \
  --service-name=my-php-service \
  --otlp-endpoint=http://127.0.0.1:4318
```

Run with the permissions required by PHP's extension/INI directories and the installation directory
(default `/opt/beacon/php` on Unix). System PHP installations usually require administrator access;
container builds can run the same command as their build user. `--dry-run` shows the plan first.
Release `SHA256SUMS` also contains the installer checksum for independent verification.

The installer records service and endpoint defaults. Restart PHP-FPM or Apache after installation.
The **application service or container environment** can override these defaults:

```bash
OTEL_SERVICE_NAME=my-php-service
OTEL_EXPORTER_OTLP_ENDPOINT=http://127.0.0.1:4318
```

PHP-FPM pools must pass environment overrides to workers through their pool configuration; the
installer defaults also work with `clear_env=yes`. The standalone Agent defaults to OTLP HTTP/protobuf. It loads automatically before application code
and bundles selected official PDO, cURL, Guzzle, Laravel and Symfony instrumentation. The native
extension remains maintained in `beacon-php-instrumentation`; users do not need to install it separately.

Use the same entry point for diagnostics, upgrades and removal:

```bash
php beacon-setup.php doctor --php-bin=all
# Download the installer from the target release before upgrading.
php beacon-setup.php upgrade --php-bin=all
php beacon-setup.php uninstall --php-bin=all
```

See [standalone deployment and compatibility](docs/standalone.md) for offline installation,
existing startup hooks, rollback and advanced Composer project integration.

## Security (experimental, opt-in)

Enable Security during installation or an idempotent reinstall:

```bash
php beacon-setup.php --php-bin=all --enable-security
```

Alternatively set `BEACON_SECURITY_ENABLED=true` in the application environment. Security and local
output are disabled by default. Security reuses OTLP Logs, so the Collector must enable a **logs**
pipeline. Findings are conservative observations, not confirmed vulnerabilities.
See [Security coverage and configuration](docs/security.md).

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
