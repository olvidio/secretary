# Importación idempotente (D8, Fase 5)

Léelo junto con `docs/dev/plan_ampliaciones.md` (D8) y `docs/dev/periodificacion.md` (una sola fecha en el Excel).

## Qué problema resuelve

Hasta la Fase 4c, `import:excel` volcaba el Excel a `apuntes` y después `ConvertirApuntesAAsientos` **borraba todos los asientos del ejercicio** y los recreaba. Un asiento tecleado a mano, un préstamo entre libros o la apertura de un año nuevo desaparecían al reimportar.

## Tablas

- **`import_ejecuciones`** — cada pasada (fichero, sha256, `--dry-run`, altas/cambios/bajas/omitidos).
- **`import_filas`** — clave natural `(ejercicio_id, hoja, fila)` → `hash_contenido` + `asiento_id`.

La hoja de movimientos es `Talonarios`. El hash cubre fecha, libro, origen A/B/C, iniciales, concepto, importe y observaciones.

## Algoritmo

Por unidad de asiento (una fila, o el par 41/42 fusionado):

| Situación | Acción |
| --- | --- |
| Hash nuevo (no había fila) | Crear asiento `origen=import` |
| Hash igual | Saltar |
| Hash distinto | Actualizar el asiento existente (mismo `id` y número) |
| Fila registrada que ya no está en el Excel | Anular el asiento **solo si** `origen='import'` |

Lo manual, el cierre de mes, la remesa y la apertura generada (`GenerarApertura`, `origen=manual`) no se tocan.

Si el ejercicio aún no tiene filas en `import_filas` (base convertida en la Fase 3), la primera pasada borra únicamente los asientos `origen=import` y los vuelve a crear ya enlazados al registro. Es el puente para `secretario` real, que tenía 423 asientos de importación sin esta tabla.

El Excel trae **una** fecha: `fecha_operacion = fecha`. Nunca se parte en periodificación al importar.

## Qué se sigue reemplazando

`apuntes` y `presupuesto_lineas` siguen siendo una instantánea del último Excel (el golden master agrega `apuntes`). No son el libro diario. Las personas siguen en upsert (Fase 2b).

## CLI

```bash
php bin/console.php import:excel [fichero.xlsm] [--dry-run] [--centro=Montagut] [--ejercicio=2026]
```

`--dry-run` informa altas/cambios/bajas sin escribir asientos ni hashes. `--centro` y `--ejercicio` eligen el destino; por defecto, el centro del Excel y su ejercicio abierto.

`asientos:convertir` queda como no-op si el ejercicio ya tiene asientos: la conversión vive en la importación.

## Tests

- `tests/integration/ImportacionIdempotenteTest.php` — tres pasadas, id estables, asiento manual, cambio de hash, baja, `--dry-run`
- `tests/unit/HashFilaImportacionTest.php`
