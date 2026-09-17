# Traducciones gettext — Secretario

Catálogos en `languages/`; convención en código: `_("texto")` con comillas dobles.

## Estructura

```text
languages/
  secretary.pot
  es_ES.UTF-8/LC_MESSAGES/secretary.po / .mo
  ca_ES.UTF-8/LC_MESSAGES/secretary.po / .mo
tools/i18n/
  actualizar.sh          # xgettext + sync + msgfmt
  sync_catalogs.py       # sincroniza .po desde .pot (es + ca desde Orbix)
  ca_translations.json   # léxico ca completo
  aplicar_ca.py            # vuelca el léxico al .po
  traduir_groq.py          # relleno asistido (GROQ_API_KEY en .env)
```

## Actualizar tras cambiar `_()` en el código

```bash
tools/i18n/actualizar.sh
tools/i18n/.venv/bin/python tools/i18n/aplicar_ca.py   # si cambió el .pot y hay que remapear ca
msgfmt -o languages/ca_ES.UTF-8/LC_MESSAGES/secretary.mo languages/ca_ES.UTF-8/LC_MESSAGES/secretary.po
```

Tras añadir msgid nuevos al `.pot`, edite `ca_translations.json` (o use `traduir_groq.py`) antes de `aplicar_ca.py`.

## Cadenas sin traducir (es = ca)

Comprobar:

```bash
tools/i18n/.venv/bin/python -c "
import json
lex=json.load(open('tools/i18n/ca_translations.json'))
same=[k for k,v in lex.items() if k==v]
print(len(same), 'iguales de', len(lex))
"
```

Para rellenar entradas donde `msgstr` sigue en español:

1. Exportar pendientes (opcional): mismas líneas Python filtrando `k==v`.
2. Traducir en lotes → `ca_batch_N.json`.
3. Fusionar:

```bash
tools/i18n/.venv/bin/python tools/i18n/fusionar_batches.py
```

`rellenar_ca.py` intenta MyMemory (límite de peticiones); preferible revisión manual o lotes JSON.

Las ~75 entradas que siguen idénticas suelen ser cognados (`Error`, `Total`, `Persona`), códigos (`613 P`, `2FA`, `IBAN`) o abreviaturas de informes (`VºBº El d`).

## Runtime

`ServicioCatalogoIdioma::activarDesdeSesion()` en `Kernel::boot()` lee `$_SESSION['idioma']` (`es` / `ca`).

El manual de ayuda (`docs/manual/`) permanece en español; la ayuda IA adapta idioma por prompt.
