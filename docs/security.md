# Beacon PHP Security

Security is experimental and opt-in. It implements the Beacon Security schema v1 and finding
fingerprint v1 from immutable specification commit
[`af1b834501210bfd197bf606163680129cc5d09b`](https://github.com/beacon-observability/beacon-security-spec/tree/af1b834501210bfd197bf606163680129cc5d09b).
Artifact provenance is shipped in `resources/security-contract.json`, independently checked against
`Contract` constants. Normative schema and fingerprint vectors are copied unchanged into
`tests/security/spec/`, with the upstream license. This is a PHP implementation in the lightweight
Agent; the native hook engine remains in `beacon-php-instrumentation`.

## Enablement and lifecycle

Install the Beacon extension and Agent, then require the application's Composer autoloader before
application code. No additional Composer component package is needed for Security observations.
Existing official component instrumentation packages remain responsible for application spans.

```bash
BEACON_SECURITY_ENABLED=true \
OTEL_PHP_AUTOLOAD_ENABLED=true \
OTEL_SERVICE_NAME=orders \
OTEL_LOGS_EXPORTER=otlp \
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf \
OTEL_EXPORTER_OTLP_ENDPOINT=http://127.0.0.1:4318 \
php application.php
```

Configure an OTLP Logs receiver pipeline at the destination. An application with a manually
configured OTel SDK can reuse its global LoggerProvider, provided it is installed before the
Beacon Composer autoload file runs. Otherwise use standard SDK auto-configuration. The PHP
resource detector's environment configuration supplies event identity; programmatic custom
resources must use the same resource identity in `OTEL_RESOURCE_ATTRIBUTES` and
`OTEL_SERVICE_NAME` to keep the body and provider resource consistent.

| Canonical setting | PHP environment binding | Default |
| --- | --- | --- |
| `beacon.security.enabled` | `BEACON_SECURITY_ENABLED` | `false` |
| `beacon.security.sbom.enabled` | `BEACON_SECURITY_SBOM_ENABLED` | `true` |
| `beacon.security.local-output.enabled` | `BEACON_SECURITY_LOCAL_OUTPUT_ENABLED` | `false` |
| `beacon.security.output` | `BEACON_SECURITY_OUTPUT` | system temp directory + `/beacon-security` |
| `beacon.security.evidence.file` | `BEACON_SECURITY_EVIDENCE_FILE` | unique runtime directory + `/evidence.jsonl` |

Booleans accept `true`, `1`, `yes`, or `on`, case-insensitively; other explicit values are false.
The former `SECURITY_*` names are ignored. `OTEL_SDK_DISABLED=true` also prevents Security from
starting. Composer development commands do not activate Security. `doctor --json` reports contract
versions and configured gates without exposing diagnostic paths or transport credentials.

Security installs observers before initializing its logger/resource detectors, emits startup
health, and collects an SBOM at shutdown before the SDK's shutdown/flush callback. It never shuts
down or replaces the application's provider itself. Diagnostics are registered once per PHP
request/runtime. Conventional CLI and PHP request lifecycles are covered; long-running multi-request
workers have process-scoped budgets and no framework request reset integration. They are not
claimed as supported request-isolated collectors. Calls made before autoload, including functions
already observed by the Zend observer engine, cannot be retroactively instrumented. Initialize
Beacon before application or custom SDK code calls the selected sinks.

## Findings and coverage

The input model inspects current string leaves of `$_GET`, `$_POST`, and `$_COOKIE` at sink
invocation. A nonempty value appearing as a substring in a selected sink argument creates a
conservative candidate observation. Nested structural keys identify input fields; oversized or
non-identifier keys are suppressed. Input bytes are only examined in memory and are never retained
in event payloads. There is no VM taint engine, transformation propagation, sanitizer model, exploit
verification or blocking. Coincidental equal values can create false positives; transformed values
can create false negatives. Findings declare these gaps explicitly, use `validation=unvalidated`,
and record only `execution_observation=invocation_attempt`.

| Rule emitted | Selected sinks | Interpretation |
| --- | --- | --- |
| `sql_injection` | `PDO::query`, `exec`, `prepare`; mysqli `query`, `real_query`, `multi_query`, `prepare` and procedural equivalents | Input appears in a SQL template; bound parameter values are not SQL-template evidence. |
| `command_execution` | `exec`, `system`, `passthru`, `shell_exec`, `popen`, `proc_open` | Shell script or argv executable invocation. With no matching source this is only `assessment=observation`. |
| `path_traversal` | `file_get_contents`, `fopen`, `readfile`, `file`, `file_put_contents`, `unlink`, `copy`, `rename` | Input appears in a selected file path. No path traversal or filesystem outcome is confirmed. |
| `ssrf` | `curl_exec` configured effective URL; HTTP(S) URLs passed to the selected read functions | Input appears in the destination. Redirect hops, DNS/IP resolution and network outcome are not modeled. |

Only installed extensions expose their sinks. mysqli observers are registered but have no local
live database integration evidence yet. There are no separate `command_injection` or
`http_request_input` event producers, header/raw JSON/framework body collectors, Guzzle/framework
hooks, stream write/network wrapper modeling, argv argument tracking, `include`/`require`/`eval`
opcode observers, or interprocedural propagation. `fopen` write/append/create/read-write modes use
`operation=write`; read-only modes use `read`. `copy` and `rename` observe source and target.

Ranges use byte offsets and `exact=false`; only the first matching occurrence per input leaf is
reported. Propagation is empty. Application file/line locations and argument-free stack entries
supply context; stream filenames are suppressed. Routes, request timing, server-span attribution
and sink-to-package attribution remain unresolved instead of being invented. Active trace context
is included when present. No request values, SQL, shell scripts, destination URLs, cookies, exception
messages, or function arguments are serialized. Normal structural field names, application paths
and explicitly configured OTel identity remain visible, as required for diagnosis.

## Identity and transport

`application_id = app- + SHA256(service.namespace + "|" + service.name)` uses the SDK resource
detectors. The SDK's `unknown_service:php` fallback is reported as `identity_status=fallback`.
Every PHP runtime lifecycle gets a unique instance ID; configured `service.instance.id` remains a
service resource attribute. Finding IDs use all v1 length-prefixed segments, UTF-16 signature sort
order, UTF-8 hashing and U+FFFD replacement for invalid input strings. All upstream vectors,
including isolated-surrogate replacement, must pass.

Events use scope `io.beacon.security`, Agent scope version, INFO severity, native event name,
`event.name` and `source` attributes, and a JSON string body. They reuse `OTEL_LOGS_EXPORTER`,
`OTEL_EXPORTER_OTLP_*`, authentication, TLS, batching, and the global LoggerProvider. There is no
separate Security endpoint or service-name setting. A successful API call, including a call to a
no-op logger, is not receiver/backend acknowledgement. SDK-level queue loss and receiver errors
remain SDK concerns; health reports only observed API exceptions and the local channel budget.
Instrumentation exceptions never replace application results or application exceptions.

## Bounded collection and diagnostic output

Limits are fixed implementation bounds per PHP request/runtime:

- 128 visited source nodes, depth 4, 64 matched source leaves, 4 KiB per source value per sink;
- 64 KiB per sink argument, 64 findings, 12 stack frames;
- 256 event records, with one reserved additional loss record, and 64 KiB per serialized record;
- 8,192 included files, 2,048 Composer packages, 100 dependencies per snapshot part.

Source/sink/finding limits emit `beacon.security.collection.incomplete` once per reason. These are
approximate diagnostics, not exact counts of skipped input objects. Record overflow emits the
minimal truncation envelope with the original source. Event-count overflow emits
`beacon.security.export.dropped` once with an at-least loss count; later events are suppressed.
Shutdown health can itself be dropped when the channel is exhausted. No durable delivery is implied.

Local output requires `BEACON_SECURITY_LOCAL_OUTPUT_ENABLED=true`. It creates a random private
runtime subdirectory (0700), `evidence.jsonl` and, when SBOM is enabled, `application.cdx.json`
(0600). The latter uses the CycloneDX 1.7 top-level shape and standard `properties` metadata.
An explicitly configured evidence file must not already exist: existing files and symlinks are
not overwritten or appended. Use a writable parent directory for a new file per runtime; a fixed
shared file is unsuitable for PHP-FPM workers. The output directory holds process-local snapshots,
not multi-process aggregation. Write failures emit `beacon.security.snapshot.failed` without file
paths or exception details. Setting an evidence path alone never enables disk output.

## Runtime SBOM

Snapshots include loaded PHP extensions and Composer packages with at least one included file
under their real install path. Installed package metadata alone does not establish runtime loading.
Every snapshot has explicit partial completeness and reasons: loading is not execution reachability,
a complete application inventory, or confirmed build provenance. There are no synthetic artifact
hashes, lockfile hashes or vulnerability assertions. Snapshot IDs include runtime identity to
prevent multipart revisions from different PHP workers being mixed. Revision 1 is emitted once at
shutdown, with complete revision counts and zero-based part indexes; empty snapshots still have
one part. There is no background refresh or build-SBOM import. Snapshot construction failure emits
`beacon.security.sbom.update_failed`. Disabling SBOM leaves finding collection active.

## Validation evidence and performance

Local validation used Linux x86_64 and Beacon extension 1.0.1 / upstream engine 1.4.2, built from
pinned commit `72d777a505a100cf21576422e987cb9ce9e4245e`, on PHP 8.2.34 and 8.4.26. A PHP 8.3.35
Beacon container also passed the tests. Contract/privacy/budget tests, real PDO SQLite/file/command/
cURL observers, result/exception preservation, enable/disable lifecycle gates, and real HTTP input/request isolation are automated. A separately
installed release archive passed the same native hook tests alongside official PDO auto-instrumentation
0.6.0 (API 1.10.1 / SDK 1.15.0).
The upstream JSON Schema validates actual emitted bodies. A real Collector 0.135.0 received OTLP
HTTP/protobuf findings, runtime SBOM and correlated trace IDs with no raw probe value. Release CI
repeats the receiver check using its pinned Collector version. This does not prove backend ingestion,
other platforms, production performance, live mysqli coverage or all official component combinations.

A single local PHP 8.4 CLI microbenchmark, below the finding budget and using a no-op logger with
local output off, measured 32 SQLite queries at 0.27 ms with Security disabled versus 3.90 ms enabled;
autoload/startup was 13.70 versus 25.58 ms and peak allocated memory was 2 versus 4 MiB. This is an
illustrative active-collection cost, not a production overhead bound. It excludes network export
and shutdown SBOM cost. Measure the application's actual workload before enabling the feature.

```bash
composer test
BEACON_TEST_EVENTS=/tmp/events.jsonl BEACON_TEST_HOOK_EVENTS=/tmp/hooks.jsonl composer test
python3 tests/security/verify-events.py /tmp/events.jsonl /tmp/hooks.jsonl # requires jsonschema
BEACON_SECURITY_ENABLED=false php tests/security/benchmark.php
BEACON_SECURITY_ENABLED=true php tests/security/benchmark.php
```

CI runs PHP 8.2/8.3/8.4 with the pinned native extension, schema validation, and package checks.
The release pipeline additionally asserts Collector receipt of finding and SBOM, native event
names, instrumentation scope, active trace correlation, and absence of the private probe value.
