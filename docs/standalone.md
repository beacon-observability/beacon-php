# Standalone deployment

`beacon-setup.php` is the public installation entry point. Source repositories remain separate:
`beacon-php` owns the installer, PHP runtime and component distribution; `beacon-php-instrumentation`
owns the native hook engine. The default installer coordinates both from pinned release artifacts.

## Default runtime

The installation contains versioned directories under `--install-dir`, a per-PHP-INI startup
launcher, and a managed INI file. Composer is used at build time only. The host needs PHP 8.2,
8.3 or 8.4 and either `tar` or PHP's Phar support for TAR.GZ extraction. Standalone mode does not
require PHP ZIP, Packagist access, or changes to the application's Composer project.

The runtime ships selected official instrumentation packages and an isolated SDK/exporter. Its
private class map does not register global OpenTelemetry, PSR or Symfony dependencies. Application
objects passed to official instrumentation retain their public type identities. Generated Protobuf
PHP descriptor identities follow the private namespace; protobuf wire names are unchanged.
Runtime dependency versions, licenses, source references, lock hash and file hashes are recorded in
`runtime.json`. Framework source code is not shipped with the Agent.

The initial component set is PDO, cURL, Guzzle, Laravel and Symfony. Laravel uses the official
component's supported framework range (10–13); framework and client packages belong to the application.
Plain PHP HTTP requests receive a generic server span. Applications whose lock file identifies a
framework use the official framework server instrumentation. Configure unsupported/custom layouts
through advanced project integration rather than assuming full framework coverage.

The standalone runtime has its own OpenTelemetry context. Business code using its own SDK keeps
its own types and provider; use project mode when manual application spans must directly share the
Agent's in-process SDK/context. Standard W3C propagation continues to correlate distributed requests.
Do not install two automatic instrumentation distributions for the same application.

## Existing configuration

The installer preserves existing INI content and the previous `auto_prepend_file`. The previous
prepend executes once in the global script scope after Agent initialization. Environment `OTEL_*`
and `BEACON_SECURITY_*` settings take precedence over installation defaults. PHP-FPM must pass
worker environment overrides through its pool configuration; installer service/endpoint defaults
also work with `clear_env=yes`. Pool-level `php_admin_value[auto_prepend_file]` can override the
global INI: validate an actual request for every pool before claiming successful activation. The Agent's private
SDK auto-configuration does not enable the application's separate Composer SDK implicitly.

Installation state is stored beside the managed INI in a private `.beacon.json` file. Modified
managed configuration is detected before replacement or removal. Runtime staging occurs on the
destination filesystem and startup files are activated by rename. A failed installation restores
previous managed INI/state/settings. Cached versioned binaries and runtimes are retained so a
previous release's installer can reactivate them. Restart PHP services after changes.

Diagnostics check the loaded native distribution and actual standalone bootstrap. Diagnostic probes
skip application prepend execution and suppress telemetry/disk output; they do not claim Collector
receipt. `OTEL_SDK_DISABLED=true` disables runtime activation.

## Offline installation

Copy the compiled installer, `beacon-php-runtime-<version>.tar.gz`, and matching pinned native
extension archives into one directory. The installer checks its embedded SHA-256 values offline:

```bash
php beacon-setup.php --php-bin=all --offline-dir=/path/to/release-files
```

Native artifacts must match PHP API, thread-safety mode, OS and architecture. The current native
release supports its documented Linux glibc, macOS arm64 and Windows x86_64 combinations. Debug,
32-bit and unsupported musl/Alpine builds fail validation rather than selecting an incompatible
binary. Native compatibility does not by itself establish full standalone/framework acceptance on
every platform; CI and release evidence determine validated combinations.

## Advanced Composer project integration

Use this mode when the application should own the SDK dependencies and context:

```bash
php beacon-setup.php --mode=project --project=/srv/application --components=auto
```

The installer downloads the pinned library ZIP, configures the application's Composer artifact
repository and installs the selected official component packages. This mode requires Composer and
PHP ZIP and intentionally updates the project's Composer files. Run Composer as the application
owner. For privileged system directories, perform the Composer phase with `--skip-extension`
after the Beacon extension is enabled, or configure an already-installed project with
`--skip-composer` as the system administrator. `--ini` and `--extension-dir` support writable custom
PHP installations and each require one explicit PHP target.

Security's runtime SBOM combines the isolated Agent registry with the application's own Composer
metadata, requiring an actually included file under each reported package's real path. Both inventories
remain explicitly partial. Agent operational calls are excluded from standalone Security sink matching.
