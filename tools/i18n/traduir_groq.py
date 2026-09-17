#!/usr/bin/env python3
"""Traduce entradas vacías de secretary.po usando la API de Groq."""

from __future__ import annotations

import argparse
import json
import os
import time

import polib
from groq import Groq

from secretary_env import load_repo_env

load_repo_env()

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
LANG_DIR = os.path.normpath(os.path.join(SCRIPT_DIR, "..", "..", "languages"))
DOMAIN = "secretary"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Traduce msgstr vacíos de languages/<idioma>/LC_MESSAGES/secretary.po"
    )
    parser.add_argument(
        "--idioma",
        default=os.environ.get("SECRETARY_I18N_LANG", "ca_ES.UTF-8"),
        help="Carpeta bajo languages/ (p. ej. ca_ES.UTF-8)",
    )
    parser.add_argument(
        "--idioma-nom",
        default="catalán",
        help="Nombre del idioma destino para el prompt",
    )
    parser.add_argument("--lot", type=int, default=35, help="Frases por petición")
    return parser.parse_args()


def build_context_prompt(idioma_nom: str) -> str:
    return (
        "Actúa como traductor experto de software. "
        "El proyecto es Secretario: contabilidad personal y general de un centro (tesorería, remesas, apuntes, cierre de mes). "
        f"Idioma destino: {idioma_nom}. Origen: español. "
        "Tono formal y claro, propio de software de gestión.\n\n"
        "Recibirás JSON con textos indexados. Devuelve solo JSON con las mismas claves y las traducciones.\n\n"
        "Reglas:\n"
        "1. Mantén %s, %d, %1$s y similares.\n"
        "2. No añadas markdown ni explicaciones.\n"
        "3. Respeta comillas y puntos finales del original cuando tenga sentido."
    )


def traduir_lot_ia(client: Groq, context_prompt: str, lot_entrades: list) -> int:
    dades_enviar = {str(idx): entry.msgid for idx, entry in enumerate(lot_entrades)}
    text_json = json.dumps(dades_enviar, ensure_ascii=False)
    resposta_neta = ""

    try:
        response = client.chat.completions.create(
            model="llama-3.1-8b-instant",
            messages=[
                {"role": "system", "content": context_prompt},
                {"role": "user", "content": text_json},
            ],
            temperature=0.1,
            response_format={"type": "json_object"},
        )
        resposta_neta = response.choices[0].message.content.strip()
        if resposta_neta.startswith("```json"):
            resposta_neta = resposta_neta[7:]
        if resposta_neta.endswith("```"):
            resposta_neta = resposta_neta[:-3]
        traduccions = json.loads(resposta_neta)
        for idx, entry in enumerate(lot_entrades):
            val = traduccions.get(str(idx))
            if isinstance(val, str) and val.strip():
                entry.msgstr = val.strip()
        return len(lot_entrades)
    except Exception as exc:  # noqa: BLE001
        print(f"Error en lot: {exc}\nResposta: {resposta_neta[:200]}")
        return 0


def main() -> None:
    args = parse_args()
    api_key = os.environ.get("GROQ_API_KEY", "").strip()
    if not api_key:
        raise SystemExit("Falta GROQ_API_KEY en .env o entorno")

    po_path = os.path.join(LANG_DIR, args.idioma, "LC_MESSAGES", f"{DOMAIN}.po")
    if not os.path.isfile(po_path):
        raise SystemExit(f"No existe {po_path}")

    po = polib.pofile(po_path)
    buides = [e for e in po if e.msgid and not e.msgstr and not e.obsolete]
    if not buides:
        print("No hi ha cap cadena buida.")
        return

    client = Groq(api_key=api_key)
    prompt = build_context_prompt(args.idioma_nom)
    print(f"Traduint {len(buides)} entrades a {args.idioma}…")

    lot = max(1, args.lot)
    traduides = 0
    for i in range(0, len(buides), lot):
        chunk = buides[i : i + lot]
        n = traduir_lot_ia(client, prompt, chunk)
        traduides += n
        po.save(po_path)
        print(f"  {min(i + lot, len(buides))}/{len(buides)}")
        time.sleep(0.5)

    po.save(po_path)
    print(f"Fet. Traduccions aplicades: {traduides}")


if __name__ == "__main__":
    main()
