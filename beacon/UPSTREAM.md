# Synchronizing OpenTelemetry PHP Contrib

Run all commands from the repository root. `main` is the Beacon downstream branch. The official `main` branch is used only to discover updates and must never overwrite Beacon-owned commits. PHP Contrib Composer subpackages are released independently, so the repository does not provide a recent unified version tag suitable as a baseline. This project therefore uses a reviewed full upstream commit as its synchronization baseline and pins it in the [baseline record](upstream.lock.json).

## Remote configuration

Verify the remotes after cloning:

```bash
git remote -v
```

The expected configuration is:

```text
origin    https://github.com/beacon-observability/beacon-php.git
upstream  https://github.com/open-telemetry/opentelemetry-php-contrib.git
```

If `upstream` is missing, add it and disable accidental pushes:

```bash
git remote add upstream https://github.com/open-telemetry/opentelemetry-php-contrib.git
git remote set-url --push upstream DISABLED
git config remote.pushDefault origin
```

## Synchronization procedure

1. Fetch the official main branch and record the full commit under consideration:

   ```bash
   git fetch --no-tags upstream main
   git show --no-patch --format=fuller upstream/main
   ```

2. Create a synchronization branch from a clean Beacon `main` and merge the pinned commit with a merge commit. Do not use directory replacement or squash away upstream provenance:

   ```bash
   git switch main
   git switch -c sync/php-contrib-YYYYMMDD
   git merge --no-ff <reviewed-upstream-commit>
   ```

3. Resolve conflicts while preserving `beacon/`, `beacon-package/`, the root README, and Beacon CI. Review newly introduced upstream Actions and do not import upstream split-release, bot, Packagist, or organization-specific credential workflows.
4. Run `php beacon/scripts/check-project.php`, the candidate-package CI, and static analysis and tests for every affected subproject. Auto-instrumentation changes must also be checked against the corresponding stable `ext-opentelemetry` release. Extension upgrades require a separate review and validation cycle.
5. Update `upstream.commit` in `upstream.lock.json` only after validation passes. Update extension fields only when the new extension version is actually adopted and validated, never merely because upstream published a release.
6. Confirm that the pinned upstream commit is part of the current history:

   ```bash
   git merge-base --is-ancestor <reviewed-upstream-commit> HEAD
   ```

Fetching, merging, testing, and releasing are distinct states. Completing a synchronization does not mean that Beacon PHP has been released or that untested components have received a support commitment.
