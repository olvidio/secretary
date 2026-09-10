# Contexto para agentes

- Repo: `/home/dani/secretary`. No está dentro de Orbix. Docker propio: `/home/dani/docker_images/secretary` (http://127.0.0.1:8005).
- Origen: Programa Secretario v8 (`moviments2026.xlsm`).
- Capas: ver `AGENTS.md`.
- Login: email o alias (`scl`) + contraseña + TOTP de centro. Ver `docs/dev/acceso.md`.
  Hace falta `APP_KEY` en `.env`.
- Importación: `php bin/console.php import:excel [ruta.xlsm] [--dry-run] [--centro=CODIGO] [--ejercicio=ETIQUETA]`. Idempotente por fila (D8, `docs/dev/importacion.md`). Desde `/centros` se sube el Excel a un centro concreto; `--centro` no toca `configuracion` ni el libro de los demás.
- Libro diario: `asientos` + `movimientos` (céntimos, cuadre estricto). La entrada Excel (A/B/C) se traduce detrás (`TraductorApuntesAAsientos`). `SignoTesoreria` ya no existe.
- Saldos de caja/banco: `SUM(debe − haber)` de cuentas de tesorería (`CAJA` / `BANCO`). Saldos personales: cuentas `CC.*` del libro P.
- Tesorería física compartida P+G (`docs/dev/tesoreria.md`): alta en `/tesoreria`, traspasos y préstamos en `/traspasos`. El arqueo se cuadra contra el saldo físico (P+G). Si la diferencia es múltiplo de 9, **Buscar capuchinos** localiza apuntes de caja con cifras invertidas.
- 613 previsto = presupuesto × mesesTranscurridos / mesesTotales (D11).
- Plan de ampliaciones (dos niveles, partida doble, multicentro, auth): `docs/dev/plan_ampliaciones.md`. Leerlo antes de tocar el modelo de datos.
- Cierre de ejercicio y apertura automática (D12): `docs/dev/cierre_ejercicio.md`; UI `/ejercicios`.
- Periodificación (D13): `asientos.fecha` es imputación y `fecha_operacion` es ejecución. Si difieren (caja/banco), dos asientos enlazados contra `PUENTE.PERIODIFICACION`. UI: campo opcional en Entrada P/G. Ver `docs/dev/periodificacion.md`.
- Entrada G: un gasto con iniciales crea P/111, P/21, G/11 y el gasto (`ContrapartidasGastoGeneral`), si la persona aporta vivienda a generales.
- Comprobaciones P/G: cuadre P/21↔G/11 y meses sin movimiento hasta la fecha de cierre (`MesesSinMovimiento`; la exención de Nombres excluye meses). El cierre de vivienda automática solo reparte entre quien aporta a G.
- Editar apunte: PUT `/api/apuntes/{id}` (`ActualizarApunte`). UI en Apuntes y Por concepto; foco en observaciones; confirmación si cambia otro campo.
- Nivel 1 (D5): libro `X` por persona, pantallas `/yo`. TOTP no obligatorio. Demo `yo` / `cambiar`. Ver `docs/dev/personal.md`.
- Remesas (D6, Fase 8): envío mensual X → P del centro, versionado, detalle bajo petición. Ver `docs/dev/remesas.md`.
