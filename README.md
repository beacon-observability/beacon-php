# Beacon PHP

Beacon PHP is a PHP auto-instrumentation and enhancement project built from the complete OpenTelemetry PHP Contrib source tree. This standalone downstream repository preserves upstream history without using a GitHub fork, while maintaining Beacon-specific features, tests, versions, and release processes independently.

The Beacon Composer base package is named `beacon-observability/beacon-php`. Stable releases use `beacon-vX.Y.Z` Git tags and attach an installable Composer artifact, license, and checksums to the corresponding GitHub release. The package is not currently mirrored to public Packagist because its source is maintained in the `beacon-package/` monorepo subtree.

PHP auto-instrumentation consists of the component instrumentation packages in this repository and the [`Beacon PHP Instrumentation`](https://github.com/beacon-observability/beacon-php-instrumentation) native extension based on `zend_observer`. The two repositories track their respective OpenTelemetry upstream projects independently and are integration-tested at pinned versions. The currently pinned native extension release is [`v0.1.0`](https://github.com/beacon-observability/beacon-php-instrumentation/releases/tag/v0.1.0).

## Development resources

- [Development guide and project boundaries](beacon/README.md)
- [Source provenance and upstream baseline](beacon/upstream.lock.json)
- [OpenTelemetry PHP Contrib synchronization](beacon/UPSTREAM.md)
- [Release process](beacon/RELEASING.md)
- [Beacon PHP 1.0.0 release notes](beacon/releases/1.0.0.md)
- [Beacon Composer base package](beacon-package/)
- [OpenTelemetry PHP Contrib components](src/)
- [Contribution guide](CONTRIBUTING.md)

The daily CI workflow validates only Beacon-owned entry points, the candidate package, and project metadata. Adopting a new upstream baseline requires the complete test suites for every affected Contrib component; daily CI does not replace upstream synchronization validation.

## Beacon Contributors

<p align="center">
  <a href="https://github.com/lrwh">
    <img src="https://avatars.githubusercontent.com/u/17264378?v=4" width="96" height="96" alt="Reid Liu">
    <br>
    Reid Liu
  </a>
</p>

## Product and upstream projects

- [Beacon product repository](https://github.com/beacon-observability/beacon)
- [OpenTelemetry PHP Contrib](https://github.com/open-telemetry/opentelemetry-php-contrib)
- [Beacon PHP Instrumentation Extension](https://github.com/beacon-observability/beacon-php-instrumentation)
- [OpenTelemetry PHP Instrumentation upstream](https://github.com/open-telemetry/opentelemetry-php-instrumentation)

This repository preserves the upstream source layout, history, package names, and [license](LICENSE). Only Beacon-owned Composer packages use the Beacon name. Upstream `open-telemetry/*` packages are never presented as Beacon artifacts by changing their versions.
