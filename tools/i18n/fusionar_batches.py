#!/usr/bin/env python3
"""Fusiona ca_batch_*.json en ca_translations.json y regenera .po/.mo."""

from __future__ import annotations

import glob
import json
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
LEX = Path(__file__).resolve().parent / "ca_translations.json"
BATCHES = Path(__file__).resolve().parent / "ca_batch_*.json"


def main() -> int:
    with LEX.open(encoding="utf-8") as handle:
        lex: dict[str, str] = json.load(handle)

    actualizadas = 0
    for path in sorted(glob.glob(str(BATCHES))):
        with open(path, encoding="utf-8") as handle:
            batch = json.load(handle)
        for msgid, msgstr in batch.items():
            if msgid not in lex or not msgstr:
                continue
            if lex[msgid] != msgstr:
                lex[msgid] = msgstr
                actualizadas += 1

    with LEX.open("w", encoding="utf-8") as handle:
        json.dump(lex, handle, ensure_ascii=False, indent=2, sort_keys=True)

    aplicar = Path(__file__).resolve().parent / "aplicar_ca.py"
    actualizar = Path(__file__).resolve().parent / "actualizar.sh"
    subprocess.run([sys.executable, str(aplicar)], check=True)
    subprocess.run([str(actualizar)], check=True)

    iguales = sum(1 for k, v in lex.items() if k == v)
    print(f"Actualizadas {actualizadas} entradas. Iguales es=ca: {iguales} (cognados o códigos).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
