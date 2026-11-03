"""Validate emitted events against the pinned upstream schema, not a duplicate model."""
import json
import sys
from pathlib import Path

from jsonschema import Draft202012Validator, FormatChecker

schema = json.loads((Path(__file__).parent / 'spec/beacon-security-event-v1.schema.json').read_text())
validator = Draft202012Validator(schema, format_checker=FormatChecker())
count = 0
for filename in sys.argv[1:]:
    for line in Path(filename).read_text().splitlines():
        validator.validate(json.loads(line))
        count += 1
if count == 0:
    raise SystemExit('No emitted events to validate')
print(f'Validated {count} Security events against the pinned schema.')
