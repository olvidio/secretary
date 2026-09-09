# Nivel 1 — contabilidad personal (D5, Fase 7)

El nivel personal es el **mismo** despliegue y el mismo motor de asientos. Cambia
el ámbito de sesión (`nivel=persona`, `persona_id`) y el libro: **`X`**.

La persona cierra el mes y envía una **remesa** al centro (`/yo/remesas`). Ver
`docs/dev/remesas.md`. Hasta que el centro acepta, el libro P del centro no
cambia. La tesorería del nivel 1 no viaja.

## Cuentas

`AsegurarPlanPersonal` (y `Nivel1Seeder`) copia el plan maestro **P** salvo el
concepto 9 (saldo de c/c) a cuentas `libro=X` de esa persona, más:

- `CAJA` y `BANCO` propias (`cuenta_fisica_id` NULL: no son la tesorería del centro, D10).
- `PUENTE.PERIODIFICACION` para D13.

Las subcuentas (`21.gas`, etc.) exigen `codigo_maestro` existente en ese plan.
El código es `maestro` + `.` + etiqueta alfanumérica (máx. 20).

## Entrada

`RegistrarMovimientoPersonal`: sentido `ingreso` | `gasto` | `traspaso`.
Fecha de imputación opcional (D13); un traspaso caja↔banco no admite fechas distintas.

La numeración de asientos es por `(ejercicio_id, libro)`: X tiene serie propia.
El ejercicio es el **abierto del centro** de la persona.

## Aislamiento

- `CalcularSaldos` y el 613 ignoran tesorería `libro=X`.
- El listado de apuntes del centro no incluye X (si no se pide `libro=X`).
- `listarTesoreria` / `imputablesDe` / `listarDeCentro` no devuelven cuentas X.
- Borrar un apunte del centro rechaza asientos X.

## Acceso

Identidad demo: alias `yo`, email `yo@secretario.local`, contraseña `APP_PASSWORD`
(por defecto `cambiar`), vinculada a la primera persona del centro. TOTP no
obligatorio. Tras el login va a `/yo`.

Pantallas: `/yo`, `/yo/movimientos`, `/yo/categorias`, `/yo/remesas` (layout propio,
sin cinta del centro). API bajo `/api/yo/...`. Ámbito `persona` en `CatalogoRutas`.
Una sesión de centro que pisa `/yo` vuelve a `/`; una de persona que pisa el
centro va a `/yo`.
