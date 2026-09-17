#!/usr/bin/env python3
"""Traduce entradas de ca_translations.json donde msgstr == msgid (español sin traducir)."""

from __future__ import annotations

import argparse
import json
import re
import time
from pathlib import Path

from deep_translator import MyMemoryTranslator

ROOT = Path(__file__).resolve().parents[2]
LEX_PATH = Path(__file__).resolve().parent / "ca_translations.json"

# Correcciones posteriores a traducción automática (UI Secretario).
CORRECCIONES: dict[str, str] = {
    "Guardar": "Desar",
    "Añadir": "Afegir",
    "Filtrar": "Filtrar",
    "Entrar": "Entrar",
    "Salir": "Sortir",
    "Apuntes": "Apunts",
    "Remesas": "Remeses",
    "Tesorería": "Tesoreria",
    "Configuración": "Configuració",
    "Contraseña": "Contrasenya",
    "Idioma": "Idioma",
    "Centros": "Centres",
    "Nombres": "Noms",
    "Copias": "Còpies",
    "Ejercicios": "Exercicis",
    "Saldos": "Saldo",
    "Disponible": "Disponible",
    "Plantillas": "Plantilles",
    "Conceptos": "Conceptes",
    "Presupuesto P": "Pressupost P",
    "Presupuesto G": "Pressupost G",
    "Entrada P": "Entrada P",
    "Entrada G": "Entrada G",
    "Cierre de mes": "Tancament de mes",
    "Fecha cierre": "Data de tancament",
    "Comprobaciones": "Comprovacions",
    "Enviar a DL": "Enviar a DL",
    "Por concepto": "Per concepte",
    "Resumen E37": "Resum E37",
    "Cuentas personales": "Comptes personals",
    "Inicio": "Inici",
    "Utilidades": "Utilitats",
    "Parámetros": "Paràmetres",
    "Movimientos": "Moviments",
    "Presupuestos": "Pressupostos",
    "Resúmenes": "Resums",
    "Personales": "Personals",
    "Generales": "Generals",
    "Personales y generales": "Personals i generals",
    "Plan contable": "Pla comptable",
    "Ayuda": "Ajuda",
    "Mis cuentas": "Els meus comptes",
    "Resumen": "Resum",
    "Lista": "Llista",
    "Banco": "Banc",
    "Categorías": "Categories",
    "Remesa": "Remesa",
    "Cierre": "Tancament",
    "Observaciones": "Observacions",
    "Cantidad": "Quantitat",
    "Concepto": "Concepte",
    "Iniciales": "Inicials",
    "Fecha": "Data",
    "Apunte": "Apunt",
    "Caja": "Caixa",
    "Cancelar": "Cancel·lar",
    "Continuar": "Continuar",
    "Borrar": "Esborrar",
    "Editar": "Editar",
    "Importe": "Import",
    "Persona": "Persona",
    "Centro": "Centre",
    "Nombre": "Nom",
    "Descripción": "Descripció",
    "Activo": "Actiu",
    "Inactivo": "Inactiu",
    "Sí": "Sí",
    "No": "No",
    "Todos": "Tots",
    "Ninguno": "Cap",
    "Total": "Total",
    "Subir": "Pujar",
    "Descargar": "Descarregar",
    "Importar": "Importar",
    "Exportar": "Exportar",
    "Buscar": "Cercar",
    "Cerrar": "Tancar",
    "Aceptar": "Acceptar",
    "Rechazar": "Rebutjar",
    "Confirmar": "Confirmar",
    "Proponer": "Proposar",
    "Enviar": "Enviar",
    "Ver": "Veure",
    "Volver": "Tornar",
    "Registrarse": "Registrar-se",
    "Usuario o email": "Usuari o correu",
    "Español": "Espanyol",
}

PH_RE = re.compile(r"(%(\d+\$)?[sd]|%s|%d)")


def proteger(text: str) -> tuple[str, list[str]]:
    placeholders: list[str] = []

    def repl(m: re.Match[str]) -> str:
        placeholders.append(m.group(0))
        return f"__PH{len(placeholders) - 1}__"

    return PH_RE.sub(repl, text), placeholders


def restaurar(text: str, placeholders: list[str]) -> str:
    for i, ph in enumerate(placeholders):
        text = text.replace(f"__PH{i}__", ph)
    return text


def traducir(tr: MyMemoryTranslator, text: str, retries: int = 3) -> str:
    if text in CORRECCIONES:
        return CORRECCIONES[text]
    protegido, phs = proteger(text)
    for intento in range(retries):
        try:
            out = tr.translate(protegido)
            out = restaurar(out, phs).strip()
            if out in CORRECCIONES:
                return CORRECCIONES[out]
            return out
        except Exception:
            time.sleep(1.5 * (intento + 1))
    return text


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--delay", type=float, default=0.35)
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()

    with LEX_PATH.open(encoding="utf-8") as handle:
        lex: dict[str, str] = json.load(handle)

    pendientes = [k for k, v in lex.items() if k == v]
    print(f"Pendientes de traducir: {len(pendientes)}")
    if not pendientes:
        return 0

    if args.dry_run:
        return 0

    tr = MyMemoryTranslator(source="es-ES", target="ca-ES")
    hechas = 0
    for i, msgid in enumerate(pendientes, 1):
        lex[msgid] = traducir(tr, msgid)
        if lex[msgid] != msgid:
            hechas += 1
        if i % 25 == 0:
            with LEX_PATH.open("w", encoding="utf-8") as handle:
                json.dump(lex, handle, ensure_ascii=False, indent=2, sort_keys=True)
            print(f"  {i}/{len(pendientes)} ({hechas} traducidas)")
        time.sleep(args.delay)

    for msgid, override in CORRECCIONES.items():
        if msgid in lex:
            lex[msgid] = override

    with LEX_PATH.open("w", encoding="utf-8") as handle:
        json.dump(lex, handle, ensure_ascii=False, indent=2, sort_keys=True)

    iguales = sum(1 for k, v in lex.items() if k == v)
    print(f"Listo. Traducidas: {hechas}. Siguen iguales: {iguales}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
