# Tesorería física compartida (D10, Fase 4)

Documenta la implementación de **tesorería física compartida entre libros** en Secretario.
Léelo junto con `docs/dev/plan_ampliaciones.md` (D10) y `docs/dev/ambito.md` (semilla
`CAJA.1/P`, `BANCO.1/G`, etc.).

## Dos niveles

| Nivel | Tabla / código | Qué representa |
| --- | --- | --- |
| **Física** | `cuentas_fisicas` | La caja o el banco del mundo real (nombre, IBAN). Pertenece al **centro**, no a un libro. |
| **Mayor** | `cuentas` con `tipo=tesoreria` y `cuenta_fisica_id` | La parte contable en cada libro: `CAJA.1/P`, `CAJA.1/G`, `BANCO.2/G`… Un asiento del libro P solo puede usar cuentas `/P`. |

El **saldo por libro** es el de su cuenta de mayor. El **saldo físico** (extracto, arqueo) es la **suma P+G** de esa física.

## Alta de cuentas físicas

`POST /api/tesoreria` (`CrearCuentaFisica`): crea la fila en `cuentas_fisicas` y automáticamente las dos cuentas de mayor (`{CAJA|BANCO}.{orden}/P` y `/G`). El `orden` es 1 + MAX del mismo tipo en el centro.

La UI está en `/tesoreria` (Utilidades).

## Apuntes con banco/caja concreto

`POST /api/apuntes` acepta opcional `cuenta_fisica_id` cuando `origen` es `B` o `C`. Si hay **más de una** física activa de ese tipo y no se indica id, la API devuelve error pidiendo elegirla. En Entrada P/G el selector de caja o banco aparece solo cuando hay más de una activa.

Los traspasos Excel 41/42 siguen usando la tesorería por defecto (`orden` 1).

## Traspaso mismo libro

`POST /api/traspasos` — Debe destino / Haber origen sobre cuentas de mayor del **mismo** libro. UI: `/traspasos`.

## Préstamo entre libros

Cuando G adelanta efectivo a P sobre la **misma** caja física:

- Asiento **origen** (G): Debe `PUENTE.LIBROS/G` · Haber tesorería G.
- Asiento **destino** (P): Debe tesorería P · Haber `PUENTE.LIBROS/P`.
- Enlace mutuo en `asientos.asiento_par_id`. Borrar uno de los dos anula el par (la FK circular se libera antes del DELETE).

El saldo **físico** de la caja no cambia; sube P y baja G el mismo importe.

`POST /api/prestamos-libros` — UI en `/traspasos`.

## Arqueo

El arqueo se cuadra contra el **saldo físico** (P+G) de la caja seleccionada, no contra un solo libro.

- Rutas legadas `/api/arqueos/{P|G}`: interpretan P/G como la **primera caja activa** y muestran desglose `saldo_fisico`, `saldo_caja_p`, `saldo_caja_g`.
- `GET/POST /api/arqueos/fisica/{id}` para una física concreta.
- Si la diferencia conteo − saldo contable es múltiplo de 9 (y no cero), la pantalla ofrece **Buscar capuchinos**: apuntes de caja (origen C) cuyo importe, con dos cifras invertidas o la coma corrida, explicaría el descuadre. `GET /api/arqueos/capuchinos?diferencia=&hasta=`.
- `ultimo('P')` / `ultimo('G')` del golden siguen filtrando por columna legada `arqueos.cuenta`.

## Informes

`CalcularSaldos` **no cambia**: `caja` y `banco` siguen siendo la suma de **todas** las cuentas CAJA / BANCO activas.

El desglose por física está en `GET /api/informes/tesoreria?hasta=` y en la tabla extra de `/saldos`.

## Cuenta puente

`PUENTE.LIBROS/P` y `PUENTE.LIBROS/G` (`tipo=puente`, `codigo_maestro=PUENTE`) — sembradas por `AmbitoSeeder`. **No** confundir con G/41 ni G/42 (traspaso caja↔banco del Excel) ni con `PUENTE.PERIODIFICACION` (D13, imputación a otro período).
