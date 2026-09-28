# Beacon PHP release preparation

The public product name is Beacon PHP. The candidate Composer package is named `beacon-observability/beacon-php`, and stable release tags will use `beacon-vX.Y.Z`. Upstream `open-telemetry/*` subpackages retain their own versions and are not assigned the Beacon product version in bulk.

A stable release workflow is not available yet. Complete all of the following requirements before creating the Packagist package and release workflow:

1. Confirm ownership of the `beacon-observability` Packagist organization and package name, then define GitHub and Packagist publishing permissions and manual approval requirements.
2. Define the initial component scope. The base Beacon package must not force mutually exclusive frameworks such as Laravel, Symfony, or WordPress into the same installation; applications install framework instrumentation as needed.
3. Pin the Contrib commit, `beacon-php-instrumentation` release commit, official extension baseline, and Composer dependencies. Review licenses and third-party notices.
4. Build the candidate archive from the pinned commit, install it in clean environments across the supported PHP matrix, and run `vendor/bin/beacon-php doctor`.
5. Run unit, static, and integration tests for every supported component, then validate the OTLP trace path against the target receiver version.
6. Record artifact checksums, known limitations, upgrade and rollback procedures, and versioned usage documentation.

## Versioning rules

- `beacon/version.properties` is the single manually maintained source for the product development version.
- `beacon-package/src/Version.php` is the code-level copy verified by the project check script.
- Development versions use `X.Y.Z-dev`, release candidates use `X.Y.Z-rc.N`, and stable versions use `X.Y.Z`.
- The final Composer version comes from an immutable Git tag. Never overwrite an artifact published for an existing version.

## Candidate artifact

The current project can generate a Composer archive for validation:

```bash
php beacon/scripts/check-project.php
composer install --working-dir=beacon-package
composer check --working-dir=beacon-package
COMPOSER_ROOT_VERSION=0.1.0 composer archive \
  --working-dir=beacon-package --format=zip --dir=dist
```

A successful build confirms only package metadata and basic runtime checks. It does not constitute a release and does not replace framework-component or receiver acceptance testing.
