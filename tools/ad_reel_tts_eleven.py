#!/usr/bin/env python3
"""ElevenLabs TTS for KupiTelefon reels (pro Serbian/Balkan voice)."""

from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.request
from pathlib import Path


def main() -> int:
    if len(sys.argv) < 3:
        print("Usage: ad_reel_tts_eleven.py <script.txt> <out.mp3> [voice_id] [api_key]", file=sys.stderr)
        return 2

    script_path = Path(sys.argv[1])
    out_path = Path(sys.argv[2])
    voice_id = sys.argv[3] if len(sys.argv) > 3 else os.environ.get(
        "ELEVENLABS_VOICE_ID", "j96cp162VcYdsYfSp1nc"
    )
    api_key = sys.argv[4] if len(sys.argv) > 4 else os.environ.get("ELEVENLABS_API_KEY", "")
    model_id = os.environ.get("ELEVENLABS_MODEL_ID", "eleven_multilingual_v2")

    if not api_key:
        print("Missing ELEVENLABS_API_KEY", file=sys.stderr)
        return 1

    text = script_path.read_text(encoding="utf-8").strip()
    if not text:
        print("Empty script", file=sys.stderr)
        return 1

    url = f"https://api.elevenlabs.io/v1/text-to-speech/{voice_id}?output_format=mp3_44100_128"
    speed = float(os.environ.get("ELEVENLABS_SPEED", "1.12"))
    speed = max(0.7, min(1.2, speed))
    payload = {
        "text": text,
        "model_id": model_id,
        "voice_settings": {
            "stability": float(os.environ.get("ELEVENLABS_STABILITY", "0.35")),
            "similarity_boost": float(os.environ.get("ELEVENLABS_SIMILARITY", "0.85")),
            "style": float(os.environ.get("ELEVENLABS_STYLE", "0.45")),
            "use_speaker_boost": True,
            "speed": speed,
        },
    }
    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Accept": "audio/mpeg",
            "Content-Type": "application/json",
            "xi-api-key": api_key,
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=90) as resp:
            audio = resp.read()
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", errors="replace")
        print(f"HTTP {e.code}: {body[:400]}", file=sys.stderr)
        return 1
    except Exception as e:  # noqa: BLE001
        print(f"Request failed: {e}", file=sys.stderr)
        return 1

    if len(audio) < 1000:
        print("Short audio response", file=sys.stderr)
        return 1

    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_bytes(audio)
    print(f"OK {out_path} ({len(audio)} bytes) voice={voice_id}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
