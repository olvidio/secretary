#!/usr/bin/env python3
"""Aplica ca_translations.json al catálogo ca_ES.UTF-8."""

from __future__ import annotations

import json
import os
import sys

import polib

ROOT = os.path.normpath(os.path.join(os.path.dirname(__file__), "..", ".."))
LEX = os.path.join(os.path.dirname(__file__), "ca_translations.json")
PO = os.path.join(ROOT, "languages", "ca_ES.UTF-8", "LC_MESSAGES", "secretary.po")


def main() -> int:
    if not os.path.isfile(LEX):
        print(f"Falta {LEX}", file=sys.stderr)
        return 1
    with open(LEX, encoding="utf-8") as handle:
        lex = json.load(handle)
    po = polib.pofile(PO)
    applied = 0
    for entry in po:
        if not entry.msgid:
            continue
        if entry.msgid in lex and lex[entry.msgid]:
            entry.msgstr = lex[entry.msgid]
            applied += 1
    po.save(PO)
    vacios = sum(1 for e in po if e.msgid and not e.msgstr)
    print(f"Aplicadas {applied} traducciones. Pendientes: {vacios}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
