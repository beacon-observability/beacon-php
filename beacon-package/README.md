# Beacon PHP Composer Package

This is the candidate Composer package for Beacon PHP. It is currently intended only for development and artifact validation and has not been published to Packagist.

The package provides the Beacon version identity and the `beacon-php doctor` diagnostic command. It also pins the foundational OpenTelemetry API, SDK, OTLP Exporter, and `ext-opentelemetry` constraints. Applications must install component instrumentation for Laravel, Symfony, Guzzle, PDO, and other libraries as needed so the base package does not force mutually exclusive frameworks into the same installation.

Stable installation instructions will be provided after the first release and public package-index verification are complete.
