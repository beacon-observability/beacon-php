"""Require actual standalone Protobuf receipt, framework scopes, propagation and app SBOM."""
import json
import re
import sys
from pathlib import Path

from jsonschema import Draft202012Validator, FormatChecker

text = Path(sys.argv[1]).read_text()
for scope in ['io.beacon.php.http', 'io.opentelemetry.contrib.php.pdo',
              'io.opentelemetry.contrib.php.guzzle', 'io.opentelemetry.contrib.php.symfony',
              'io.opentelemetry.contrib.php.laravel.http.kernel']:
    assert f'InstrumentationScope {scope}' in text, f'Missing Collector scope: {scope}'
for scope in ['io.beacon.php.http', 'io.opentelemetry.contrib.php.symfony',
              'io.opentelemetry.contrib.php.laravel.http.kernel']:
    pattern = re.escape('InstrumentationScope ' + scope) + r'[^\n]*\nSpan #\d+\n\s+Trace ID\s+: 12345678901234567890123456789012\n\s+Parent ID\s+: 1234567890123456'
    assert re.search(pattern, text), f'Incoming parent not preserved for {scope}'
for service in ['beacon-standalone-fpm-validation', 'beacon-standalone-apache-validation']:
    assert service in text, f'Missing server receipt: {service}'
schema = json.loads((Path(__file__).parent.parent / 'security/spec/beacon-security-event-v1.schema.json').read_text())
validator = Draft202012Validator(schema, format_checker=FormatChecker())
events = []
for line in text.splitlines():
    if line.startswith('Body: Str({'):
        value = json.loads(line[len('Body: Str('):-1])
        if value.get('service', {}).get('service.name') == 'beacon-standalone-http-validation':
            if value.get('source') in ['beacon_security', 'beacon_security_sbom']:
                validator.validate(value)
                events.append(value)
snapshots = [e for e in events if e.get('event_name') == 'beacon.security.sbom.snapshot']
assert snapshots, 'Missing standalone Security SBOM'
inventory = json.dumps(snapshots)
for package in ['laravel/framework', 'symfony/http-kernel', 'guzzlehttp/guzzle']:
    assert package in inventory, f'Business dependency missing from runtime SBOM: {package}'
print('Collector confirmed standalone HTTP/framework/client spans, W3C parent and business runtime SBOM.')
