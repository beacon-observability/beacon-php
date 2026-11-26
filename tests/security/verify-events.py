"""Validate compact Security Bodies and diagnostic record wrappers."""
import json
import sys
from pathlib import Path
from jsonschema import Draft202012Validator, FormatChecker
root = Path(__file__).parent / "spec"
validator = Draft202012Validator(json.loads((root / "beacon-security-log-body-v1.schema.json").read_text()), format_checker=FormatChecker())
count = 0
for filename in sys.argv[1:]:
    for line in Path(filename).read_text().splitlines():
        record = json.loads(line)
        event = json.loads(record["body"]) if "body" in record else record
        validator.validate(event)
        if "body" in record:
            assert 0 < int(record["time_unix_nano"]) <= 9223372036854775807
            assert record["attributes"]["event_id"]
            assert record["attributes"]["source"] == event["source"]
        count += 1
assert count, "No emitted events to validate"
print(f"Validated {count} compact Security Bodies.")
