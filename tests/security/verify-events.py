"""Validate emitted SIEM envelopes and their original v2 evidence."""
import json
import sys
from pathlib import Path
from jsonschema import Draft202012Validator, FormatChecker
root = Path(__file__).parent / "spec"
validators = [Draft202012Validator(json.loads((root / name).read_text()), format_checker=FormatChecker())
              for name in ["beacon-security-siem-v1.schema.json", "beacon-security-event-v2.schema.json"]]
count = 0
for filename in sys.argv[1:]:
    for line in Path(filename).read_text().splitlines():
        envelope = json.loads(line)
        validators[0].validate(envelope)
        validators[1].validate(json.loads(envelope["message"]))
        count += 1
assert count, "No emitted events to validate"
print(f"Validated {count} SIEM envelopes and embedded v2 events.")
