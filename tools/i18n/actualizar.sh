#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

find frontend src -name '*.php' -print0 | xargs -0 xgettext \
  --language=PHP \
  --from-code=UTF-8 \
  --keyword=_ \
  --output=languages/secretary.pot

"$ROOT/tools/i18n/.venv/bin/python" "$ROOT/tools/i18n/sync_catalogs.py"
"$ROOT/tools/i18n/.venv/bin/python" "$ROOT/tools/i18n/aplicar_ca.py"

msgfmt -o languages/es_ES.UTF-8/LC_MESSAGES/secretary.mo languages/es_ES.UTF-8/LC_MESSAGES/secretary.po
msgfmt -o languages/ca_ES.UTF-8/LC_MESSAGES/secretary.mo languages/ca_ES.UTF-8/LC_MESSAGES/secretary.po

echo "Catálogos es/ca compilados."
