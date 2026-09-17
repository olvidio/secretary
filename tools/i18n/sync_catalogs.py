#!/usr/bin/env python3
"""Sincroniza es_ES y ca_ES.UTF-8 desde secretary.pot; rellena es; importa solapes de Orbix."""

from __future__ import annotations

import os
import sys

import polib

ROOT = os.path.normpath(os.path.join(os.path.dirname(__file__), "..", ".."))
LANG = os.path.join(ROOT, "languages")
POT = os.path.join(LANG, "secretary.pot")
DOMAIN = "secretary"
ORBIX_CA = os.path.normpath(
    os.path.join(ROOT, "..", "docker_images", "orbix", "web", "html", "orbix", "languages", "ca_ES.UTF-8", "LC_MESSAGES", "orbix.po")
)


def sync_locale(locale: str, fill_from_pot: bool, orbix_map: dict[str, str] | None) -> None:
    po_dir = os.path.join(LANG, locale, "LC_MESSAGES")
    os.makedirs(po_dir, exist_ok=True)
    po_path = os.path.join(po_dir, f"{DOMAIN}.po")

    if os.path.isfile(po_path):
        po = polib.pofile(po_path)
        po.merge(polib.pofile(POT))
    else:
        po = polib.pofile(POT)
        po.metadata = polib.pofile(POT).metadata.copy()

    po.metadata["Language"] = locale
    po.metadata["Content-Type"] = "text/plain; charset=UTF-8"
    po.metadata["Plural-Forms"] = "nplurals=2; plural=(n != 1);"

    for entry in po:
        if not entry.msgid:
            continue
        if fill_from_pot:
            entry.msgstr = entry.msgid
        elif orbix_map and entry.msgid in orbix_map and not entry.msgstr:
            entry.msgstr = orbix_map[entry.msgid]

    po.save(po_path)
    vacios = sum(1 for e in po if e.msgid and not e.msgstr)
    print(f"{locale}: {len([e for e in po if e.msgid])} entrades, {vacios} sense traduir")


def main() -> int:
    if not os.path.isfile(POT):
        print(f"Falta {POT}. Ejecute xgettext antes.", file=sys.stderr)
        return 1

    orbix_map: dict[str, str] = {}
    if os.path.isfile(ORBIX_CA):
        for e in polib.pofile(ORBIX_CA):
            if e.msgid and e.msgstr:
                orbix_map[e.msgid] = e.msgstr
        print(f"Orbix ca: {len(orbix_map)} traduccions importables")

    sync_locale("es_ES.UTF-8", fill_from_pot=True, orbix_map=None)
    sync_locale("ca_ES.UTF-8", fill_from_pot=False, orbix_map=orbix_map)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
