#!/usr/bin/env python3
"""Rellena msgstr vacíos en ca_ES.UTF-8 con Google Translate (desarrollo)."""

from __future__ import annotations

import argparse
import time

import polib
from deep_translator import GoogleTranslator

ROOT = __path__[0] if False else __import__("os").path.normpath(
    __import__("os").path.join(__import__("os").path.dirname(__file__), "..", "..")
)
PO = __import__("os").path.join(ROOT, "languages", "ca_ES.UTF-8", "LC_MESSAGES", "secretary.po")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--delay", type=float, default=0.3, help="Segundos entre peticiones")
    args = parser.parse_args()

    po = polib.pofile(PO)
    pending = [e for e in po if e.msgid and not e.msgstr]
    if not pending:
        print("Nada pendiente.")
        return 0

    tr = GoogleTranslator(source="es", target="ca")
    print(f"Traduciendo {len(pending)} cadenas…")
    for i, entry in enumerate(pending, 1):
        try:
            entry.msgstr = tr.translate(entry.msgid)
        except Exception as exc:  # noqa: BLE001
            print(f"[{i}] error: {entry.msgid[:50]!r} -> {exc}")
            time.sleep(2.0)
            continue
        if i % 20 == 0:
            po.save(PO)
            print(f"  {i}/{len(pending)}")
        time.sleep(args.delay)

    po.save(PO)
    vacios = sum(1 for e in po if e.msgid and not e.msgstr)
    print(f"Listo. Pendientes: {vacios}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
