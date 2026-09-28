# Beacon PHP release process

The Composer package is built from the repository root. Stable tags use `vX.Y.Z`.

Before publishing a release:

1. Set the same version in `beacon/version.properties`, `src/Version.php`, and `composer.json`.
2. Pin a released `beacon-php-instrumentation` tag and immutable commit in `beacon/compatibility.lock.json`.
3. Update `composer.lock` and review all official OpenTelemetry dependency changes.
4. Run project metadata, unit, PHP 8.2/8.4, native extension, archive-install, and OTLP receiver validation.
5. Verify the archive contains Agent code and resources but no development state, credentials, logs, or vendored Contrib source.
6. Publish immutable ZIP, license, and checksum assets from the tagged commit.

Development versions use `X.Y.Z-dev`, release candidates use `X.Y.Z-rc.N`, and stable versions use `X.Y.Z`. Never move a published tag.

The release workflow installs the generated ZIP into a clean Composer project before publication. A release failure caused by committed content is fixed under a new version; only transient workflow failures may be rerun for an unchanged tag.
