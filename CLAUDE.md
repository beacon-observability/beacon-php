# Beacon PHP repository guide

Beacon PHP is a thin Composer distribution built on the official OpenTelemetry PHP SDK, OTLP exporter, and independently released auto-instrumentation packages. It is not an OpenTelemetry PHP Contrib monorepo mirror.

The native hook engine is maintained in the separate `beacon-php-instrumentation` repository. Keep native C code there. Keep Agent dependency orchestration, the component manifest, diagnostics, packaging, and integration tests here.

Useful commands:

```bash
composer install
composer test
php beacon/scripts/check-project.php
vendor/bin/beacon-php components
vendor/bin/beacon-php doctor
```

Do not copy framework instrumentation into `src/`. General fixes go upstream. Urgent downstream fixes require a standalone Beacon-owned component package with explicit conflicts, tests, upstream tracking, and a removal condition.
