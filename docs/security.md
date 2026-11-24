# Beacon PHP Security

Security is experimental and opt-in. Current output uses only SIEM v1 envelopes
with complete original serialized schema v2 evidence in `message`, pinned to
[`4a30f30e1027568cfbcf8661079a072fc89bd825`](https://github.com/beacon-observability/beacon-security-spec/tree/4a30f30e1027568cfbcf8661079a072fc89bd825).
Fingerprint v1 is unchanged. `resources/security-contract.json` records the wire
profile and schema hashes; `beacon/scripts/check-project.php` validates them.
The native hook engine remains in `beacon-php-instrumentation`.

## Enablement and configuration

Install with the [unified installer](../README.md#installation). Configure the
host SDK's OTLP Logs provider before Security starts, or use standard automatic
SDK configuration. Resource identity, transport, TLS and authentication reuse
`OTEL_SERVICE_NAME`, `OTEL_RESOURCE_ATTRIBUTES` and `OTEL_EXPORTER_OTLP_*`.
No Security-specific transport or service-name setting is added.

| Environment binding | Default | Meaning |
| --- | --- | --- |
| `BEACON_SECURITY_ENABLED` | `false` | Enable Security. |
| `BEACON_SECURITY_SBOM_ENABLED` | `true` | Collect loaded dependencies while enabled. |
| `BEACON_SECURITY_SBOM_REFRESH_SECONDS` | `300` | Positive integer delay after collection; invalid/zero uses 300. |
| `BEACON_SECURITY_EXPORT_NO_EVIDENCE_ENABLED` | `false` | Include `no_evidence` findings in OTel and diagnostic JSONL. |
| `BEACON_SECURITY_LOCAL_OUTPUT_ENABLED` | `false` | Enable private local diagnostics. |
| `BEACON_SECURITY_OUTPUT` | system temp + `/beacon-security` | Base diagnostic directory. |
| `BEACON_SECURITY_EVIDENCE_FILE` | unique directory + `/evidence.jsonl` | New private JSONL file. |

Booleans accept `true`, `1`, `yes`, or `on`, case-insensitively. Legacy
`SECURITY_*` settings are ignored. `OTEL_SDK_DISABLED=true` prevents startup.
Security alone never enables local output. Files are private diagnostic aids,
not durable multi-process history or confirmation of backend delivery.

## Output and migration

OTel Logs retain scope `io.beacon.security`, INFO severity, native event name and
`event.name`/`source` attributes. Body and diagnostic JSONL contain the same SIEM
envelope. The fifteen base fields are extended by required process `instance_id`
and available `trace_id`, `span_id`, and numeric `trace_flags`. The native log is
associated with the invocation span, including a request-entry span; no different
span is substituted when unavailable. Old `server_span_id` and `current_span_id`
fields are absent. `message` retains the exact original serialized v2 evidence.

Resource `service.name` supplies service, configured `tenant.id` supplies tenant,
and missing tenant becomes `default`. Programmatic custom Resources must be passed
to `Events` or also configured through the standard Resource environment bindings.
Verified actor and trusted client-IP enrichment are unavailable and remain empty.
Finding outcome is `unknown` and severity `informational`, reflecting log level
rather than assessed risk. Inventory success means successful local collection.

Only finding and inventory event families are emitted. No independent health,
summary, loss, failed-scan, or truncated-record replacement events are exported.
Filtering precedes the 256-record admission budget and applies to both OTel and
local evidence. Unknown and suspected remain eligible; inventory is unaffected.
The current approximate classifier emits unknown, not new attack-analysis claims.
Request-local repeated findings are deduplicated by their unchanged fingerprint.

Final serialized envelope bytes, including JSON escaping and enrichment, must fit
65536 bytes. Findings may omit optional evidence with explicit truncation markers;
unrepresentable records are dropped without shortening `message` after projection.
Inventory parts retain every dependency exactly once. All parts are planned and
validated before any are emitted, with stable deterministic IDs and shared timing.
Successful empty lists can clear inventory. Consumers assemble all parts before
replacement; a failed scan or incomplete round must preserve the previous list.

Update consumers to parse `message` before upgrading. There is no legacy-output
switch. Historical stored records remain unchanged. Rollback requires the previous
Agent and its v1-body consumer together.

## Runtime inventory limitations

PHP lacks an independent in-process background scheduler. This implementation
collects initially during startup, checks the refresh deadline at selected native
hook boundaries, and collects at shutdown before the host SDK flushes. Unchanged
lists are republished when a deadline is observed. It installs no signal handler
and changes no application event loop. Idle processes therefore do not publish at
wall-clock intervals, and initial collection is synchronous: these remain explicit
gaps against the standard's asynchronous, independent periodic lifecycle.

Linux process identity derives from boot identity, PID and kernel process-start
ticks, remaining stable across requests in one worker. Inventory timing uses
`/proc/self/stat` and `/proc/uptime` (Linux USER_HZ=100); it is not framework readiness
time. Other platforms retain a lifecycle UUID but emit no inventory when process
timing is unavailable. Loaded extensions and Composer packages with included files
are observed dependencies, not a complete build graph. Capacity and scan failures
emit no replacement. Local CycloneDX output is diagnostic only.

Conventional CLI and PHP request lifecycles are covered. Long-running multi-request
framework workers lack a request-reset adapter and remain unsupported for request
isolation. No background-process, Windows, or full-platform conformance is claimed.

## Findings and coverage

The input model inspects current string leaves of `$_GET`, `$_POST`, and `$_COOKIE` at sink
invocation. A nonempty value appearing as a substring in a selected sink argument creates a
conservative candidate observation. Nested structural keys identify input fields; oversized or
non-identifier keys are suppressed. Input bytes are only examined in memory and are never retained
in event payloads. There is no VM taint engine, transformation propagation, sanitizer model, exploit
verification or blocking. Coincidental equal values can create false positives; transformed values
can create false negatives. Findings declare these gaps explicitly, use `attack_evidence.status=unknown` with an explicit reason. An invocation is not a completion or exploit verdict.

| Rule emitted | Selected sinks | Interpretation |
| --- | --- | --- |
| `sql_injection` | `PDO::query`, `exec`, `prepare`; mysqli `query`, `real_query`, `multi_query`, `prepare` and procedural equivalents | Input appears in a SQL template; bound parameter values are not SQL-template evidence. |
| `command_execution` | `exec`, `system`, `passthru`, `shell_exec`, `popen`, `proc_open` | Shell script or argv executable invocation. With no matching source this remains an unknown invocation observation. |
| `path_traversal` | `file_get_contents`, `fopen`, `readfile`, `file`, `file_put_contents`, `unlink`, `copy`, `rename` | Input appears in a selected file path. No path traversal or filesystem outcome is confirmed. |
| `ssrf` | `curl_exec` configured effective URL; HTTP(S) URLs passed to the selected read functions | Input appears in the destination. Redirect hops, DNS/IP resolution and network outcome are not modeled. |

Only installed extensions expose their sinks. mysqli observers are registered but have no local
live database integration evidence yet. There are no separate `command_injection` or
`http_request_input` event producers, header/raw JSON/framework body collectors, Guzzle/framework
hooks, stream write/network wrapper modeling, argv argument tracking, `include`/`require`/`eval`
opcode observers, or interprocedural propagation. `fopen` write/append/create/read-write modes use
`operation=write`; read-only modes use `read`. `copy` and `rename` observe source and target.

Ranges use byte offsets and `exact=false`; only the first matching occurrence per input leaf is
reported. Empty propagation and evidence arrays are omitted. Application file/line locations and argument-free stack entries
supply context; stream filenames are suppressed. Routes, request timing, server-span attribution
and sink-to-package attribution remain unresolved instead of being invented. Active trace context
is included when present. No request values, SQL, shell scripts, destination URLs, cookies, exception
messages, or function arguments are serialized. Normal structural field names, application paths
and explicitly configured OTel identity remain visible, as required for diagnosis.


## Verification

Run `composer check`, `php tests/security/siem.php` and the native-hook/lifecycle
checks with the matching Beacon extension. `tests/security/verify-events.py`
validates both envelopes and decoded v2 messages against the pinned schemas.
`tests/security/verify-collector.py` requires actual Collector receipt and matching
native/body invocation IDs. Collector receipt does not establish backend storage.
