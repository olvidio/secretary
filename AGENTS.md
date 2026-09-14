# Guía DDD — Secretario (secretary)

Programa nuevo, independiente de Orbix. Mismas capas y criterios: dominio sin I/O, application sin SQL ni HTML, infrastructure con PDO/HTTP/Excel.

## Comunicación

Al empezar o terminar un trabajo se pueden hacer referencias sobrenaturales breves (S. José, etc.), sin sustituir la explicación técnica.

## Estructura por módulo

```text
src/<modulo>/
  application/
  domain/
    contracts/
    entity/
    value_objects/
  infrastructure/
    http/
    persistence/
  config/
    dependencies.php
    routes.php
```

Frontend (HTML): `frontend/<modulo>/view`. Los controladores de pantalla no contienen reglas de negocio: pintan HTML y el JS llama a `/api/...`. **Para pantallas, menú, JS y errores visibles al usuario, leer `frontend/AGENTS.md`.**

## Capas

- **Domain:** sin `$_GET`/`$_POST`/`$_SESSION`, sin PDO, sin HTML.
- **Application:** casos de uso; inyección por constructor; no SQL ni vistas.
- **Infrastructure:** PDO, Excel, controladores HTTP. Fechas hacia BD siempre `Y-m-d` (ver `ConverterDate`).
- **Frontend:** layout, formularios, listados (ver `frontend/AGENTS.md`).

## Persistencia

PostgreSQL en el stack `/home/dani/docker_images/secretary` (`DB_DRIVER=pgsql`). **Único motor soportado**: el soporte de SQLite se retiró (D9 del plan de ampliaciones). Cambios de esquema mediante migraciones versionadas, nunca editando `schema/`. Columnas `date` se leen y escriben con `src\shared\infrastructure\persistence\ConverterDate`.

## Tests y docs

- Tests en `tests/unit` y `tests/integration`.
- Documentación: `docs/dev` (agentes), `docs/manual` (corpus de la ayuda: un fichero por pantalla; `docs/dev/ayuda_ia.md`), `docs/ai`.
- No dejar archivos vacíos de relleno.

## Ampliaciones futuras

Antes de modificar el esquema o el módulo `apuntes`, leer `docs/dev/plan_ampliaciones.md`: contiene las decisiones D1-D14 (partida doble con cuadre estricto, plan de cuentas jerárquico, ámbito centro/ejercicio, tesorería física compartida entre P y G, ejercicios de período libre y multianuales, apertura automática, fecha de operación vs imputación, remesas del nivel personal, autenticación de dos niveles, adscripción de personas por ejercicio) y el fasado con su criterio de aceptación.
