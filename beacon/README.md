# Beacon PHP development guide

Beacon PHP uses OpenTelemetry PHP Contrib as the primary upstream for component instrumentation. It preserves the complete Git history and maintains Beacon enhancements in the same source tree. The downstream repository, pinned baseline, candidate Composer package, and minimal CI are in place, but stable release acceptance is not yet complete.

## Project boundaries

| Responsibility | Location or source |
| --- | --- |
| Component auto-instrumentation, propagators, resource detectors, and helper packages | Repository root and `src/` |
| Beacon product version, synchronization, and release documentation | `beacon/` |
| Beacon Composer candidate package | `beacon-package/` |
| Native PHP hook extension | Separately released [`beacon-php-instrumentation v0.1.0`](https://github.com/beacon-observability/beacon-php-instrumentation/releases/tag/v0.1.0) |
| OpenTelemetry API, SDK, and OTLP Exporter | Upstream Composer dependencies |

PHP auto-instrumentation requires the Beacon distribution of `ext-opentelemetry` and one or more component instrumentation packages selected for the application's dependencies. New Beacon components and urgent fixes should keep their implementations, compatibility ranges, and tests in this repository instead of blocking on upstream acceptance. General-purpose fixes should still be contributed upstream in parallel. Manual instrumentation depends only on the OpenTelemetry API and SDK and works without loading the extension.

## Local verification

The following commands require PHP 8.2 or later, Composer 2, and `ext-opentelemetry`:

```bash
php beacon/scripts/check-project.php
composer validate --no-check-publish
composer validate --working-dir=beacon-package --strict
composer install --working-dir=beacon-package
composer check --working-dir=beacon-package
COMPOSER_ROOT_VERSION=0.1.0 composer archive \
  --working-dir=beacon-package --format=zip --dir=dist
```

These commands cover only the Beacon-owned candidate package. When modifying or synchronizing a component under `src/`, also enter the corresponding subproject and run its Composer installation, static analysis, and PHPUnit suite. Components may require databases, messaging systems, or different PHP and framework versions; follow their `composer.json`, README, and upstream test matrix.

## Current limitations

- `beacon-observability/beacon-php` is not registered on Packagist.
- The candidate package provides Beacon release identity, foundational OTel SDK and OTLP dependencies, and diagnostics. It does not install every framework component automatically. CI builds the Beacon native extension from the immutable commit for release `v0.1.0` for integration testing.
- The complete Contrib test matrix, Packagist publishing permissions, receiver integration path, and upgrade and rollback acceptance remain incomplete.
- Beacon PHP does not yet have a stable release, installation entry point, or production support commitment.
