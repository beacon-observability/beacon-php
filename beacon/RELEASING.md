# Beacon PHP release process

The Composer package is built from the repository root. Stable tags use `vX.Y.Z`.

Before publishing a release:

1. Set the same version in `beacon/version.properties`, `src/Version.php`, `composer.json`, `beacon/runtime/composer.json`, `beacon-setup.php`, and the
   README release-download example; add matching versioned release notes.
2. Pin a released `beacon-php-instrumentation` tag and immutable commit in `beacon/compatibility.lock.json`.
3. Update the root, runtime and build-tool Composer locks; review official dependency changes and isolation patches.
4. Run project metadata, unit, PHP 8.2/8.4, native extension, archive-install, and OTLP receiver validation.
5. Verify the archive contains Agent code and resources but no development state, credentials, logs, or vendored Contrib source.
6. Build the standalone runtime and compiled installer with locked build dependencies on PHP 8.4:

   ```bash
   composer install --working-dir=beacon/build-tools
   composer install --working-dir=beacon/runtime
   php beacon/scripts/build-runtime.php
   composer archive --format=zip --dir=dist
   php beacon/scripts/build-installer.php
   ```

   Build the installer after the library ZIP is final: it embeds both archive hashes.
   Publish immutable installer, runtime TAR.GZ, library ZIP, license and checksum assets from the tagged commit.
7. Download the published installer and runtime/native assets, verify `SHA256SUMS`, and repeat cold
   standalone installation, lifecycle and real OTLP receipt. Also validate advanced Composer project
   integration with PHP ZIP enabled and an artifact directory containing only the library ZIP.
   Do not claim Packagist publication unless the package has actually been registered there.

Write release notes as concise user-visible changes with links to versioned documentation.
The GitHub release title supplies the heading; record validation evidence in the PR or issue.

Development versions use `X.Y.Z-dev`, release candidates use `X.Y.Z-rc.N`, and stable versions use `X.Y.Z`. Never move a published tag.

The release workflow installs the generated ZIP into a clean Composer project before publication. A release failure caused by committed content is fixed under a new version; only transient workflow failures may be rerun for an unchanged tag.

The default standalone lifecycle matrix covers Linux glibc x86_64 PHP 8.2–8.4. The runtime build
prunes framework and client dependencies; inspect `runtime.json` and the package inventory before
claiming additional platform/framework support. Never publish the unresolved source installer template.
