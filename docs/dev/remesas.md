# Remesas (D6, Fase 8)

Envío mensual del libro personal (`X`) al libro P del centro. La tesorería del
nivel 1 **no viaja**. El grano es el total por `codigo_maestro`; el desglose de
subcuentas queda en el nivel 1 y el centro solo lo ve si la persona autoriza.

## Flujo

1. La persona previsualiza el mes (`GET /api/yo/remesas`) y envía
   (`POST /api/yo/remesas`). Nace una remesa `enviada`, versión 1, 2, …
2. El centro ve la bandeja en `/remesas`. Puede aceptar o rechazar.
3. Aceptar **borra en transacción** los asientos de la versión aceptada previa
   del mismo mes y persona, y crea **un** asiento P (`tipo`/`origen` = `remesa`)
   contra la cuenta `CC.<INICIALES>`. Cero duplicados.
4. Rechazar una `enviada` no deja asientos. Rechazar una `aceptada` borra los
   que hubiera generado (devolver).
5. Reenviar el mismo mes: si hay una `enviada` pendiente, pasa a `sustituida` y
   nace `version+1`. Una `aceptada` sigue hasta que se acepte la nueva.

Un asiento con `remesa_id` no se edita ni se borra a mano en el centro: se
corrige reenviando.

## Agregación

Por `asientos.fecha` (imputación). Se ignoran `traspaso` y `periodificacion`.
Gasto = debe − haber de cuentas `gasto`; ingreso = haber − debe de `ingreso`.
La fecha del asiento en P es el último día del mes (recortado al `fecha_fin`
del ejercicio, D11).

`hash_contenido` es SHA-256 del JSON canónico `(codigo, importe)` ordenado. Un
segundo envío con el mismo hash que la `enviada` pendiente no crea versión
nueva.

## Esquema

`migraciones/0010_remesas.sql`: `remesas`, `remesa_lineas`,
`remesa_solicitudes_detalle`, FK `asientos.remesa_id`.

`UNIQUE (persona_id, ejercicio_id, anio, mes, version)`: el plan omitía `anio`;
un ejercicio D11 largo puede tener dos eneros.

## Detalle bajo petición

El centro solicita el desglose de una línea. La persona autoriza o deniega
(`GET/POST /api/yo/remesas/solicitudes`). El detalle autorizado es solo lectura
y **nunca** crea cuentas ni asientos en el centro.

## Informes

613 P y E37 del centro incluyen lo aceptado, recortados por `fecha_cierre` /
`fecha_corte`. Una remesa de un mes posterior al corte no sale hasta actualizar
la fecha de cierre.

## Pantallas

- Persona: `/yo/remesas` (cuarta pestaña).
- Centro: `/remesas` (cinta Personales).
