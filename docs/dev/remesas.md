# Remesas (D6, Fase 8)

Envío mensual del libro personal (`X`) al libro P del centro. La tesorería del
nivel 1 **no viaja como movimientos**. Sí puede enviarse el **saldo** de
caja+banco (a la fecha de cierre del mes) para que el centro actualice el
disponible operativo. El grano es el total por `codigo_maestro`; el desglose de
subcuentas queda en el nivel 1 y el centro solo lo ve si la persona autoriza.

## Flujo

1. La persona previsualiza el mes (`GET /api/yo/remesas`) y envía
   (`POST /api/yo/remesas`), con el saldo de tesorería. Nace una remesa
   `enviada`, versión 1, 2, …
2. El centro ve la bandeja en `/remesas`. Puede aceptar o rechazar.
3. Aceptar **borra en transacción** los asientos de la versión aceptada previa
   del mismo mes y persona, y crea **dos** asientos P (`tipo`/`origen` = `remesa`)
   contra `CC.<INICIALES>`: el de conceptos y el de aparcamiento del sobrante
   (CC → `DISP.<INICIALES>`), para dejar la c/c a cero. Cero duplicados.
4. Si se marca **Sustituir el disponible**, `saldos_disponibles` pasa a ser
   exactamente esa tesorería; si no, se le suma el sobrante.
5. Rechazar una `enviada` no deja asientos. Rechazar una `aceptada` borra los
   que hubiera generado (devolver) y deshace el disponible de esa remesa.
6. Reenviar el mismo mes: si hay una `enviada` pendiente, pasa a `sustituida` y
   nace `version+1`. Una `aceptada` sigue hasta que se acepte la nueva.

Un asiento con `remesa_id` no se edita ni se borra a mano en el centro: se
corrige reenviando. Lo mismo los de origen `asignacion` (destinos 7).

Las 7 que la persona ya anotó viajan en la remesa. Las propuestas se apuntan
al confirmar (`docs/dev/disponible.md`); al aceptar, esas 7 pendientes se
restan de las líneas para no duplicar el 73.

## Agregación

Por `asientos.fecha` (imputación). Se ignoran `traspaso` y `periodificacion`.
Gasto = debe − haber de cuentas `gasto`; ingreso = haber − debe de `ingreso`.
La fecha del asiento en P es el último día del mes (recortado al `fecha_fin`
del ejercicio, D11).

Los gastos personales marcados como **generales** (`gasto_generales` en X) van
en `detalle_json.generales` por concepto G. Al aceptar, además del asiento P de
remesa, `RegistrarGastosGeneralesDeRemesa` crea los apuntes G con
contrapartidas (P/111, P/21, G/11, G/concepto), enlazados con el mismo
`remesa_id` para poder revertir al sustituir o rechazar.

Los movimientos con **plantilla del centro** (`plantilla_apunte_id` en X) van en
`detalle_json.plantillas` y no incrementan el importe de la línea P de remesa.
Al aceptar, `RegistrarPlantillasDeRemesa` expande la plantilla (como en la
entrada de apuntes del centro) con el mismo `remesa_id`.

`hash_contenido` es SHA-256 del JSON canónico `(codigo, importe)` ordenado más
la tesorería enviada. Un segundo envío con el mismo hash que la `enviada`
pendiente no crea versión nueva.

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
