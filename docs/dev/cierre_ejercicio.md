# Cierre de ejercicio y apertura automática (D12, Fase 4b)

Implementación de **cierre de ejercicio** y **apertura regenerable** en Secretario.
Léelo junto con `docs/dev/plan_ampliaciones.md` (D12) y `docs/dev/ambito.md`
(trampa `fecha_fin` / `fecha_corte`).

## Modelo

- `ejercicios.estado`: `abierto` | `cerrado`.
- `ejercicio_anterior_id`: enlace al ejercicio contiguo cerrado (inicio = fin anterior + 1 día).
- Un asiento `tipo=apertura` por libro (P y G), fecha = `fecha_inicio` del ejercicio nuevo,
  `origen=manual`, glosa «Apertura de ejercicio».
- Libro G: contrapartida patrimonio = cuenta `32` existente (`conceptoCodigo='32'` en el asiento).
- Libro P: contrapartida = `RESULTADO.ANT` (sembrada por `AmbitoSeeder`, no está en
  `CatalogoConceptos`).

### Cuentas que se arrastran

Saldos del ejercicio anterior a su **`fecha_fin`** (`saldosPorCuenta`, debe − haber):

| Tipo | Qué |
| --- | --- |
| `tesoreria` | Todas las físicas por libro (también inactivas si saldo ≠ 0) |
| `personal` | `CC.*` en P y `DEUDORES.VIV` en G |
| `puente` | `PUENTE.LIBROS` y `PUENTE.PERIODIFICACION` (D13) |

**No** se arrastran ingreso, gasto ni la línea G/32 del ejercicio anterior (el 32 de 2027
se calcula como plug de patrimonio).

Saldo > 0 → Debe; saldo < 0 → Haber (importe = abs). `persona_id` del movimiento = el de la cuenta en CC.

Si el plug de patrimonio es 0 y hay ≥ 2 movimientos de arrastre, no se añade línea de
patrimonio. Si un libro no tiene ningún saldo ≠ 0 arrastrable, no se crea asiento (D1 exige ≥ 2 movimientos).

## Casos de uso

| Clase | Qué hace |
| --- | --- |
| `CerrarEjercicio` | `estado=cerrado`, `fecha_corte=fecha_fin`. No borra asientos. |
| `ReabrirEjercicio` | Ver flujos abajo. Sincroniza `configuracion`. |
| `GenerarApertura` | Recalcula movimientos y llama a `reemplazarAperturas()` (borra y recrea en una transacción de persistencia). |
| `SincronizarConfiguracionConEjercicio` | Alinea `configuracion` legada con el ejercicio de trabajo. |
| `CrearEjercicio` | Si es contiguo al anterior cerrado: `ejercicio_anterior_id` + apertura + sync. |

Tras cerrar un ejercicio, `PdoAsientoRepository::guardar` rechaza nuevos asientos con
`InvalidArgumentException`.

`borrarAperturas()` elimina solo `tipo=apertura`; **no** usar `borrar()` (borra pares de préstamos y de periodificación).

Tras generar la apertura, se mueven al ejercicio nuevo los asientos `tipo=periodificacion` del anterior cuya fecha cae en el período nuevo (`docs/dev/periodificacion.md`).

## Reglas de negocio

- Solo un ejercicio **abierto** por centro. `CrearEjercicio` exige cerrar el actual.
- Ejercicio contiguo: el anterior debe estar **cerrado** (`Cierre primero el ejercicio …`).
- Hueco de fechas: ejercicio sin anterior ni apertura automática.
- Primer ejercicio importado (2026): `ejercicio_anterior_id` NULL; el 32 se teclea a mano.
- Antes de generar apertura: abortar si el ejercicio anterior tiene asientos descuadrados.

## Flujo de corrección (test de aceptación)

1. Cerrar 2026, crear 2027 (apertura automática).
2. **Reabrir 2026** — exige que 2027 solo tenga `tipo=apertura`; 2027 pasa a `cerrado`
   (sin borrar aperturas); 2026 queda abierto; sync config → 2026.
3. `CrearApunte` extra en 2026 (p. ej. 1 € gasto G/201 origen C).
4. Cerrar 2026; **reabrir 2027**; sync config → 2027; `GenerarApertura` de 2027
   (`POST /api/ejercicios/{id}/apertura`).

## API y UI

```
POST /api/ejercicios/{id}/cerrar
POST /api/ejercicios/{id}/reabrir
POST /api/ejercicios/{id}/apertura
```

`GET /api/ejercicios` incluye `puede_cerrar`, `puede_reabrir`, `puede_regenerar_apertura`,
`asientos_apertura`.

UI: `/ejercicios` — botones con `confirm()`.

## Configuración legada

Al crear el ejercicio siguiente o reabrir, `SincronizarConfiguracionConEjercicio` actualiza:

- `anio` = año de `fecha_inicio` (o etiqueta si es entero)
- `fecha_inicio` / `fecha_cierre` (= `ejercicio.fecha_corte`; al abrir 2027, corte = inicio)
- `modo_ejercicio`: `Año` si fin 31-12 mismo año; `Curso` si 31-08 año siguiente; si no, `Año`

`CalcularSaldos`, 613 y E37 usan esas fechas **y** `ejercicio_id` de `ResolverAmbitoActual`.

## Tests

- `tests/integration/CierreEjercicioAperturaTest.php` — base `secretario_test_cierre`, Excel real.
- `tests/unit/ConstructorAsientoAperturaTest.php` — cuadre del constructor de movimientos.
