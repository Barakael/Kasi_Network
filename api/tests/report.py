#!/usr/bin/env python3
"""Summarises `artisan test --compact` agent-format JSON on stdin.

The agent formatter emits one JSON object with every failure message inline,
which is unreadable in a terminal once a few tests break. This trims it to the
test name, line and message.
"""
import json
import sys

raw = sys.stdin.read().strip()

try:
    data = json.loads(raw)
except json.JSONDecodeError:
    print(raw)
    sys.exit(1)

print(f"{data.get('result')}: {data.get('passed')}/{data.get('tests')} passed, "
      f"{data.get('assertions')} assertions, {data.get('duration_ms')}ms")

problems = []
for key in ("failure_details", "error_details"):
    value = data.get(key)
    if isinstance(value, list):
        problems.extend(value)

for problem in problems:
    name = problem.get("test", "?").split("::")[-1]
    print(f"\n--- {name}  (line {problem.get('line')})")
    print(problem.get("message", "")[:800])

sys.exit(0 if data.get("result") == "passed" else 1)
