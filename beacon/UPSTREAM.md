# OpenTelemetry dependency policy

Beacon PHP consumes released OpenTelemetry PHP packages rather than synchronizing the full Contrib monorepo.

## Unchanged components

Install official packages directly from Composer. The alias manifest in `resources/components.json` is the runtime source used by `beacon-php components` and `beacon-php install`.

Dependency updates are ordinary Composer updates. Review the affected package release, update `composer.lock`, and run package, extension, and OTLP integration tests appropriate to the change.

## General fixes

Develop fixes against the official component repository and submit them upstream. A temporary sparse checkout may be used during development, but no permanent full Contrib mirror is maintained.

## Urgent Beacon fixes

If a production fix cannot wait for an upstream release, create a standalone Beacon-owned package containing only the affected component. It must use its own Composer name, conflict with the official implementation, preserve licensing and attribution, link to the upstream contribution, and define when the package will be retired. Do not place multiple unrelated component forks in this repository.

## Native extension

The hook extension is pinned independently in `beacon/compatibility.lock.json`. Extension updates are reviewed and tested in `beacon-php-instrumentation` first, then adopted here by updating the pinned commit and minimum extension version.
