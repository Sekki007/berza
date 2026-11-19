#!/usr/bin/env python3
import json
import os
import sys
import urllib.request

api_key = sys.argv[1] if len(sys.argv) > 1 else os.environ.get("ELEVENLABS_API_KEY", "")
if not api_key:
    raise SystemExit("missing key")

req = urllib.request.Request(
    "https://api.elevenlabs.io/v1/voices",
    headers={"xi-api-key": api_key, "Accept": "application/json"},
)
with urllib.request.urlopen(req, timeout=60) as resp:
    data = json.loads(resp.read().decode("utf-8"))

for v in data.get("voices", []):
    labels = v.get("labels") or {}
    print(
        f"{v.get('voice_id')}\t{v.get('name')}\t"
        f"cat={v.get('category')}\t"
        f"lang={labels.get('language','')}\t"
        f"accent={labels.get('accent','')}\t"
        f"desc={labels.get('description','')}"
    )
