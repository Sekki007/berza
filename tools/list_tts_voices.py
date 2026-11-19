import asyncio
import edge_tts

async def main():
    voices = await edge_tts.list_voices()
    for v in voices:
        loc = v.get("Locale", "")
        name = v.get("ShortName", "")
        if "sr-" in loc.lower() or "serbian" in loc.lower() or "sr-" in name.lower():
            print(name, loc, v.get("Gender"))

asyncio.run(main())
