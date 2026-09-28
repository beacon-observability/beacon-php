# Contributing

Beacon PHP is a thin distribution and integration layer. Do not copy the OpenTelemetry PHP Contrib source tree into this repository.

For general framework or library instrumentation fixes, open a change in the relevant official OpenTelemetry PHP repository. Beacon may carry a temporary standalone package only when a production fix cannot wait for an upstream release. Such a package must:

- contain only the affected component;
- use a Beacon-owned Composer package name;
- preserve upstream license and attribution;
- declare explicit `conflict` or `replace` rules so both implementations cannot load together;
- include compatibility and integration tests;
- have an upstream issue or pull request and an exit condition.

Changes to the native hook engine belong in `beacon-php-instrumentation`. Changes to component selection, diagnostics, packaging, or end-to-end validation belong here.

Before opening a pull request, run:

```bash
composer validate --strict --no-check-version
composer test
php beacon/scripts/check-project.php
```
