# Repository collaboration guidelines

- Use English for repository documentation, pull request titles, and pull request descriptions. Use Simplified Chinese when communicating directly with the user.
- This repository is a standalone downstream of `opentelemetry-php-contrib`, not a GitHub fork. Preserve the upstream history, directory layout, and licenses.
- `origin` must point only to `beacon-observability/beacon-php`. Use `upstream` only to fetch official updates and never push to it.
- Keep Beacon-owned code, documentation, tests, and candidate artifacts in `beacon/`, `beacon-package/`, or clearly identified Beacon component directories. Do not rewrite upstream Composer package names or versions in bulk.
- The `ext-opentelemetry` auto-instrumentation extension is maintained in a separate repository. Do not copy its source into this repository.
- Keep daily CI focused. An upstream synchronization must run and record the component tests required by its impact scope.
- Do not claim a stable release or production support until the pinned source, candidate installation, runtime environments, and receiver integration have been validated.
