# Nivel 1 — contabilidad personal (D5, Fase 7)

El nivel personal es el **mismo** despliegue y el mismo motor de asientos. Cambia
el ámbito de sesión (`nivel=persona`, `persona_id`) y el libro: **`X`**.

La persona cierra el mes y envía una **remesa** al centro (`/yo/remesas`). Ver
`docs/dev/remesas.md`. Hasta que el centro acepta, el libro P del centro no
cambia. La tesorería del nivel 1 no viaja como movimientos; el saldo de
caja+banco sí puede enviarse para el disponible (`docs/dev/disponible.md`).

## Cuentas

`AsegurarPlanPersonal` (y `Nivel1Seeder`) copia el plan maestro **P** salvo el
concepto 9 (saldo de c/c) a cuentas `libro=X` de esa persona, más:

- `CAJA` y `BANCO` propias (`cuenta_fisica_id` NULL: no son la tesorería del centro, D10).
- `PUENTE.PERIODIFICACION` para D13.
- `22.pendiente` / `113.pendiente` (Por categorizar) para el extracto CSV.
- `OTRA.gasto` / `OTRA.ingreso` (**Otra contabilidad**): el movimiento sigue
  en BANCO (el saldo cuadra con el extracto) y se puede recategorizar después;
  no entra en ingresos/gastos del plan ni en la remesa al centro.

Las subcuentas (`21.gas`, etc.) exigen `codigo_maestro` existente en ese plan.
El código es `maestro` + `.` + etiqueta alfanumérica (máx. 20).

## Entrada

`RegistrarMovimientoPersonal`: sentido `ingreso` | `gasto` | `traspaso`.
Fecha de imputación opcional (D13); un traspaso caja↔banco no admite fechas distintas.

En un **gasto**, la pill «Personal / Generales» marca el movimiento para que,
al aceptar la remesa, el centro anote también P/21, G/11 y el gasto G elegido
(p. ej. 204 Gas). Disponible para todas las personas. P/212 (vivienda personal) es un gasto
propio, como ordinarios, y no cuadra con generales.
Columnas `asientos.gasto_generales` y `concepto_generales` (`0027_gasto_generales.sql`).
API `GET /api/yo/conceptos-generales`.

La numeración de asientos es por `(ejercicio_id, libro)`: X tiene serie propia.
El ejercicio es el **abierto del centro** de la persona.

## Fecha de cierre mensual

Cada persona define en `/yo/cierre` cómo acota el mes en resumen, lista, remesa y saldos:

- **Regla por defecto:** día del mes (p. ej. 25). Vacío = último día del mes.
- **Día hábil:** si el día cae en sábado o domingo, se usa el lunes siguiente.
- **Mes concreto:** fecha explícita para un mes (anula la regla solo ese mes).

Migración `0026_personal_cierre.sql` (`personas.dia_cierre`, `personas.cierre_dia_habil`,
`personal_cierre_mes`). Lógica en `PeriodoPersonal` / `ResolverPeriodoPersonal`.

## Aislamiento

- `CalcularSaldos` y el 613 ignoran tesorería `libro=X`.
- El listado de apuntes del centro no incluye X (si no se pide `libro=X`).
- `listarTesoreria` / `imputablesDe` / `listarDeCentro` no devuelven cuentas X.
- Borrar un apunte del centro rechaza asientos X.

## Acceso

Identidad demo: alias `yo`, email `yo@secretario.local`, contraseña `APP_PASSWORD`
(por defecto `cambiar`), vinculada a la primera persona del centro. TOTP no
obligatorio. Tras el login va a `/yo`. El alta pública (`/registro` desde el login)
crea otra identidad de persona en el centro elegido (o el único) y entra igual.

Copia del libro personal (solo movimientos X): menú de usuario → **Copia personal**
(`/cuenta/copias`). JSON en `var/backups/personal/`. API `/api/yo/copias/...`.
No toca el centro ni remesas ya aceptadas. En Docker, `var/backups/personal` debe ser
escribible por PHP-FPM (`chmod 777 var/backups/personal` en desarrollo).

Pantallas: `/yo`, `/yo/movimientos`, `/yo/banco`, `/yo/categorias`, `/yo/remesas`, `/yo/cierre` (layout propio,
sin cinta del centro). API bajo `/api/yo/...`. Ámbito `persona` en `CatalogoRutas`.
Una sesión de centro que pisa `/yo` vuelve a `/`; una de persona que pisa el
centro va a `/yo`.

## Extracto CSV

En `/yo/banco` se elige el banco (hoy **N26**) y se sube el CSV. Cada fila se
identifica por una huella (fecha, importe, beneficiario, concepto, cuenta); al
volver a subir el mismo extracto no se duplica. Los movimientos nuevos van a
BANCO contra **Por categorizar** (`22.pendiente` / `113.pendiente`) hasta que se
asigna una categoría del plan, **Otra contabilidad**, **Traspaso a caja**
(desde caja en un ingreso) o una **plantilla del centro** (p. ej. Club). La
plantilla deja un solo gasto en X (`asientos.plantilla_apunte_id`); en la remesa
viaja en `detalle_json.plantillas` sin sumar al total P de la línea; al aceptar,
`RegistrarPlantillasDeRemesa` crea los apuntes del centro. Al asignar traspaso,
el asiento pasa a ser caja↔banco sin categoría de ingreso/gasto. Al asignar
categoría u otra contabilidad, las
observaciones salen rellenas con el concepto del extracto (se pueden editar o
borrar). Quedan en la glosa del asiento. Si ya hubo un movimiento parecido
(mismo beneficiario, p. ej. CAPRABO 7776 y 7851), se preselecciona esa categoría.

N26: web → cuenta → Descargas → actividad → CSV
(el extracto actual trae `Booking Date` / `Partner Name`; el clásico, `Date` / `Payee`).
CaixaBank: CaixaBankNow → Cuentas → cuenta → Extraer movimientos / Excel (.xls);
si no es CSV, guardar como CSV (`;`) antes de subirlo. Cargar todo el periodo
(«Ver más movimientos») o el fichero se queda corto.
Otros bancos se añadirán con su propio lector; más adelante se podrá detectar el
formato por la cabecera.
