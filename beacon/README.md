# Beacon PHP development guide

Beacon PHP is split across two repositories and three runtime layers:

| Responsibility | Source |
| --- | --- |
| Native function and method hooks | `beacon-php-instrumentation` |
| Agent dependencies, component manifest, CLI, diagnostics, and release integration | This repository |
| Framework, database, and client spans | Official OpenTelemetry Composer packages |

The base package requires the OpenTelemetry API, SDK, OTLP exporter, and the Beacon-compatible native extension. It deliberately does not force mutually exclusive application frameworks into one dependency graph. The default public `beacon-setup.php` installer delivers a prebuilt private runtime without changing application dependencies. Advanced project mode selects components through Composer and shares the application SDK/context.

## Boundaries

- `src/` contains only Beacon Agent code.
- `resources/components.json` is the supported alias-to-official-package manifest.
- `beacon/compatibility.lock.json` pins the native extension integration contract.
- Framework instrumentation source is not copied into this repository. The standalone build scopes locked official packages into private release artifacts; application/framework sources are pruned.
- `beacon/runtime/` holds the build profile and isolation rules; `beacon/build-tools/` contains locked build-only tooling.
- Manual instrumentation remains available through the official OpenTelemetry API and SDK without component packages.

## Verification

```bash
composer validate --strict --no-check-version
composer install
composer test
php beacon/scripts/check-project.php
vendor/bin/beacon-php doctor
```

The release workflow additionally validates cold standalone installation across PHP 8.2–8.4, real framework/client spans, PHP-FPM/Apache startup, and actual OTLP receipt. See [release process](RELEASING.md) for runtime/installer builds.
