# Beacon PHP Composer Package

This is the Composer base package for Beacon PHP. Release archives are attached to Beacon PHP GitHub releases and can be installed through a Composer artifact repository. The package is not currently mirrored to public Packagist because its source is maintained in a monorepo subtree.

The package provides the Beacon version identity and the `beacon-php doctor` diagnostic command. It also pins the foundational OpenTelemetry API, SDK, OTLP Exporter, and `ext-opentelemetry` constraints. Applications must install component instrumentation for Laravel, Symfony, Guzzle, PDO, and other libraries as needed so the base package does not force mutually exclusive frameworks into the same installation.

For Beacon PHP 1.0.0, download the release ZIP and `SHA256SUMS`, verify the files, configure their directory as a Composer artifact repository, and install the package:

```bash
sha256sum --check SHA256SUMS
composer config repositories.beacon artifact /path/to/downloaded/artifacts
composer require beacon-observability/beacon-php:1.0.0
vendor/bin/beacon-php doctor
```

The separately distributed Beacon PHP instrumentation extension must be installed before running `beacon-php doctor`.
