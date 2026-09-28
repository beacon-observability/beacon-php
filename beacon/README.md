# Beacon PHP development guide

Beacon PHP is split across two repositories and three runtime layers:

| Responsibility | Source |
| --- | --- |
| Native function and method hooks | `beacon-php-instrumentation` |
| Agent dependencies, component manifest, CLI, diagnostics, and release integration | This repository |
| Framework, database, and client spans | Official OpenTelemetry Composer packages |

The base package requires the OpenTelemetry API, SDK, OTLP exporter, and the Beacon-compatible native extension. It deliberately does not force mutually exclusive application frameworks into one dependency graph. Applications select only the components they use through `beacon-php install` or ordinary `composer require` commands.

## Boundaries

- `src/` contains only Beacon Agent code.
- `resources/components.json` is the supported alias-to-official-package manifest.
- `beacon/compatibility.lock.json` pins the native extension integration contract.
- Framework instrumentation source is not copied into this repository.
- Manual instrumentation remains available through the official OpenTelemetry API and SDK without component packages.

## Verification

```bash
composer validate --strict --no-check-version
composer install
composer test
php beacon/scripts/check-project.php
vendor/bin/beacon-php doctor
```

The release workflow additionally builds the pinned native extension and exports a real OTLP span to an OpenTelemetry Collector.
