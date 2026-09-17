#!/usr/bin/env python3
"""Generate ca_translations.json with manual Catalan UI translations."""

from __future__ import annotations

import json
from pathlib import Path

MSGIDS = Path("/tmp/secretary_msgids.json")
OUT = Path(__file__).parent / "ca_translations.json"

# fmt: off
T: dict[str, str] = {}
# fmt: on


def main() -> int:
    msgids: list[str] = json.loads(MSGIDS.read_text(encoding="utf-8"))
    missing = [m for m in msgids if m not in T]
    if missing:
        print(f"Missing {len(missing)} translations:")
        for m in missing[:20]:
            print(f"  {m!r}")
        return 1
    extra = set(T) - set(msgids)
    if extra:
        print(f"Extra keys: {len(extra)}")
    out = {m: T[m] for m in msgids}
    OUT.write_text(json.dumps(out, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Written {len(out)} entries to {OUT}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
