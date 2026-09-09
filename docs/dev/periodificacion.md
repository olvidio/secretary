# Periodificación — fecha de operación vs fecha de imputación (D13, Fase 4c)

Léelo junto con `docs/dev/plan_ampliaciones.md` (D13) y `docs/dev/cierre_ejercicio.md`.

## Qué problema resuelve

Una operación se ejecuta un día (sale el dinero el 8/01) y hay que **contarla** en otro (31/12). Un asiento tiene una sola `fecha`; si las dos no coinciden hacen falta **dos asientos enlazados**, no dos etiquetas sobre el mismo.

## Nombres

| Campo | Nombre | Qué determina |
| --- | --- | --- |
| `asientos.fecha` | Fecha de imputación | Diario, 613, E37, saldos de ingreso/gasto, ejercicio al que pertenece el concepto |
| `asientos.fecha_operacion` | Fecha de operación | Cuándo se ejecutó. En la pata de tesorería coincide con `fecha` |

No se llama «fecha de devengo» al 31/12 si el hecho es del 8/01: es imputación a período.

## Un asiento o dos

- **Misma fecha** (o imputación vacía): un asiento, dos movimientos (concepto + caja/banco). Como hasta ahora.
- **Fechas distintas** y origen B/C: par enlazado con `asiento_par_id`.
  - Imputación (`tipo=normal`, `fecha` = imputación): concepto contra `PUENTE.PERIODIFICACION`.
  - Tesorería (`tipo=periodificacion`, `fecha` = operación): puente contra caja/banco.
- Origen A, 41/42 y cierre de mes: no admiten imputación distinta.

La UI de entrada sigue siendo un solo formulario. El listado de apuntes fusiona el par en una fila (fecha = operación, anotación de imputación).

`created_at` ya cubre «cuándo se tecleó»; no hay tercer campo de negocio.

## Cuenta puente

`PUENTE.PERIODIFICACION` por libro P y G (`tipo=puente`, `codigo_maestro=PERIODIFICACION`). No es `PUENTE.LIBROS` ni G/41-42.

La apertura de ejercicio (D12) **arrastra** su saldo, igual que `PUENTE.LIBROS`.

## Ejercicios (D11/D12)

Solo un ejercicio abierto. El 8/01 vs 31/12 de año civil no cabe en el mismo período.

1. **Mismo ejercicio abierto**, operación posterior a `fecha_fin` (se entra en enero y 2027 aún no existe): las dos patas viven en el ejercicio actual; la de tesorería tiene `fecha` fuera del período. Los informes hasta `fecha_fin` no ven la salida de caja y sí el puente. Al crear el ejercicio siguiente, `GenerarApertura` **mueve** los `tipo=periodificacion` cuya fecha cae en el nuevo período.
2. **Ejercicio anterior cerrado y actual abierto**: la pata de imputación se escribe en el cerrado (excepción a «solo abiertos admiten asientos») y la de tesorería en el abierto; se regenera la apertura del abierto.

## Importación (Fase 5)

El Excel tiene una sola fecha: `fecha_operacion = fecha`. Nunca se parte al importar.
Detalle del algoritmo: `docs/dev/importacion.md`.

## Tests

- `tests/unit/ConstructorAsientoPeriodificadoTest.php`
- `tests/unit/ConstructorAsientoAperturaTest.php` (arrastre del puente)
- `tests/integration/PeriodificacionTest.php`
