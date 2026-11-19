#!/usr/bin/env python3
"""Generate clear Serbian TTS MP3 for ad reels via edge-tts."""

from __future__ import annotations

import asyncio
import sys
from pathlib import Path

import edge_tts


async def synthesize(text: str, out_path: Path, voice: str, rate: str) -> None:
    communicate = edge_tts.Communicate(text, voice=voice, rate=rate, pitch="-1Hz")
    await communicate.save(str(out_path))


def main() -> int:
    if len(sys.argv) < 3:
        print("Usage: ad_reel_tts.py <script.txt> <out.mp3> [voice] [rate]", file=sys.stderr)
        return 2
    script_path = Path(sys.argv[1])
    out_path = Path(sys.argv[2])
    voice = sys.argv[3] if len(sys.argv) > 3 else "sr-RS-NicholasNeural"
    rate = sys.argv[4] if len(sys.argv) > 4 else "-12%"
    text = script_path.read_text(encoding="utf-8").strip()
    if not text:
        print("Empty script", file=sys.stderr)
        return 1
    out_path.parent.mkdir(parents=True, exist_ok=True)
    asyncio.run(synthesize(text, out_path, voice, rate))
    if not out_path.is_file() or out_path.stat().st_size < 500:
        print("TTS output missing", file=sys.stderr)
        return 1
    print(f"OK {out_path} ({out_path.stat().st_size} bytes) voice={voice} rate={rate}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
