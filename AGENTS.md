# Repository collaboration guidelines

- Use English for repository documentation, pull request titles, and pull request descriptions. Use Simplified Chinese when communicating directly with the user.
- This repository is the lightweight Beacon PHP Agent and distribution. It must not contain or mirror the complete `opentelemetry-php-contrib` source tree.
- Use official `open-telemetry/opentelemetry-auto-*` Composer packages for unchanged component instrumentation.
- Keep the native `ext-opentelemetry` hook engine in the separate `beacon-php-instrumentation` repository; never copy its source here.
- A Beacon component fork must be a narrowly scoped standalone package with an upstream reference, explicit package conflict rules, tests, and a documented removal condition.
- Keep installation, diagnostics, release metadata, and integration tests in this repository.
- Do not claim stable support until the package, pinned native extension, target PHP versions, selected component packages, and OTLP receiver path have been validated.
