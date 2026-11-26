# Security log binding adoption validation

Current source pins specification `d9d76e5a41d974d294bb596e06918bb954319bf6`
and output profile `beacon-security-log-body-v1`. This is source validation,
not release acceptance or a claim about every receiver.

The producer retains v2 fields at their existing paths, moves `observed_at` to
native event time and `occurrence_id` to log attribute `event_id`, and emits a
compact single-line JSON Body. The original inventory identity tuple and
fingerprint v1 remain unchanged. Byte budgets use the transmitted UTF-8 Body.

## Verification

PHP 8.4.26 with the published Beacon native extension and the existing locked
Composer dependencies passed:

- `tests/security/log-record.php` shared binding vectors, precision and invalid times;
- `tests/security/run.php` filtering, native timestamp/ID preservation through the SDK, privacy, SBOM and multipart budgets;
- SIEM reference, native hooks, lifecycle gates, real HTTP isolation and periodic inventory tests;
- `tests/run.php` (28 components) and `beacon/scripts/check-project.php`;
- real PDO flow through OTLP HTTP/protobuf and Collector 0.135.0, including a trace-correlated finding and inventory (three records).

Diagnostic JSONL explicitly retains native time and attributes outside its Body
string. The diagnostic wrapper is never sent as OTLP Body. This verification does
not cover other PHP versions or SAPIs.
