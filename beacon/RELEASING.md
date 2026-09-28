# Beacon PHP release process

The public product name is Beacon PHP. The Composer base package is named `beacon-observability/beacon-php`, and stable release tags use `beacon-vX.Y.Z`. Upstream `open-telemetry/*` subpackages retain their own versions and are not assigned the Beacon product version in bulk.

Complete all of the following requirements before pushing a stable tag:

1. Confirm GitHub release permissions and configure the `release` environment with the required manual reviewers. Public Packagist publication additionally requires a split repository because the package source is a monorepo subtree.
2. Define the initial component scope. The base Beacon package must not force mutually exclusive frameworks such as Laravel, Symfony, or WordPress into the same installation; applications install framework instrumentation as needed.
3. Pin the Contrib commit, `beacon-php-instrumentation` release commit, official extension baseline, and candidate-validation Composer dependencies. Review licenses and third-party notices. Commit `beacon-package/composer.lock` for reproducible validation; it remains excluded from the library archive so consuming applications resolve their own dependency graph.
4. Build the candidate archive from the pinned commit, install it in clean environments across the supported PHP matrix, and run `vendor/bin/beacon-php doctor`.
5. Run unit, static, and integration tests for every supported component, then validate the OTLP trace path against the target receiver version.
6. Record artifact checksums, known limitations, upgrade and rollback procedures, and versioned usage documentation.

## Versioning rules

- `beacon/version.properties` is the single manually maintained source for the product development version.
- `beacon-package/src/Version.php` is the code-level copy verified by the project check script.
- Development versions use `X.Y.Z-dev`, release candidates use `X.Y.Z-rc.N`, and stable versions use `X.Y.Z`.
- The final Composer version comes from an immutable Git tag. Never overwrite an artifact published for an existing version.

## Release validation and publication

Open a release pull request that sets the stable version and adds the versioned notes under `beacon/releases/`. Run the release workflow manually on the pull request branch and review every matrix result. After merging, create and push the immutable tag from the validated `main` commit:

```bash
git switch main
git pull --ff-only origin main
git tag -s beacon-vX.Y.Z -m "Beacon PHP X.Y.Z"
git push origin beacon-vX.Y.Z
```

The tag starts `.github/workflows/beacon-release.yml`. It rebuilds the pinned extension on PHP 8.2 and PHP 8.4, installs the package in clean environments, runs diagnostics, exports a validation span to the pinned OpenTelemetry Collector, builds the archive and checksums, waits for the protected `release` environment, and creates the GitHub release.

Never move or overwrite a release tag. If validation or publication fails because the committed release content is defective, fix it under a new version. A transient workflow failure may be rerun against the same unchanged tag.
