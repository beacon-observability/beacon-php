"""Verify real Collector receipt, event mapping and trace correlation for the probe."""
import json
import sys
from pathlib import Path

from jsonschema import Draft202012Validator, FormatChecker

text = Path(sys.argv[1]).read_text()
schema = json.loads((Path(__file__).parent / 'spec/beacon-security-event-v1.schema.json').read_text())
validator = Draft202012Validator(schema, format_checker=FormatChecker())
events = []
for line in text.splitlines():
    if line.startswith('Body: Str({'):
        event = json.loads(line[len('Body: Str('):-1])
        if event.get('source') in ('beacon_security', 'beacon_security_sbom'):
            validator.validate(event)
            events.append(event)
findings = [e for e in events if e['event_name'] == 'beacon.security.finding'
            and e['service'].get('service.name') == 'beacon-security-release-validation']
snapshots = [e for e in events if e['event_name'] == 'beacon.security.sbom.snapshot']
assert findings and snapshots, 'Collector did not receive finding and runtime SBOM'
finding = findings[0]
assert finding['rule'] == 'sql_injection'
assert finding['trace_id'] and finding['current_span_id'], 'Finding lacks active trace context'
assert f"Trace ID: {finding['trace_id']}" in text
assert f"Span ID: {finding['current_span_id']}" in text
assert 'InstrumentationScope io.beacon.security ' in text
assert 'EventName: beacon.security.finding' in text
assert 'EventName: beacon.security.sbom.snapshot' in text
assert 'PRIVATE_OTLP_VALUE_91e2' not in text, 'Raw source/sink value leaked to Collector'
print(f'Collector received {len(events)} conforming Security events, including trace-correlated finding and SBOM.')
