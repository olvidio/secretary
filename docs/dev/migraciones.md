# Migraciones versionadas (Fase 1 del plan de ampliaciones)

Implementa la decisión D9 de `docs/dev/plan_ampliaciones.md`: los cambios de esquema
ya no se hacen editando `schema/` (ese directorio ha desaparecido); se hacen añadiendo
un fichero nuevo en `migraciones/`.

## Cómo crear una migración

1. Crea `migraciones/NNNN_descripcion.sql`, con `NNNN` el siguiente número de 4
   dígitos en orden (`0002`, `0003`, ...) y `descripcion` en minúsculas con guiones
   bajos. El orden de aplicación es el orden lexicográfico del nombre de fichero, así
   que respeta el número.
2. Escribe SQL de Postgres normal. Cada migración se ejecuta **en su propia
   transacción**: si una sentencia falla, se revierte entera y no se marca como
   aplicada. Postgres soporta DDL transaccional, así que esto cubre `CREATE TABLE`,
   `ALTER TABLE`, índices, etc.
3. Aplica con `php bin/console.php db:migrate` (o `composer db:migrate`).
4. Comprueba el estado con `php bin/console.php db:status` (o `composer db:status`).

**Nunca edites una migración ya aplicada en algún entorno.** Si hace falta corregir
algo, se añade una migración nueva. La regla de checksum (siguiente sección) existe
precisamente para que ese error no pase desapercibido.

## La tabla `schema_migrations` y la regla de checksum

El runner (`src\shared\infrastructure\persistence\MigrationRunner`) mantiene una
tabla:

```sql
schema_migrations (version TEXT PRIMARY KEY, aplicada_at TIMESTAMPTZ, checksum TEXT)
```

Al calcular qué falta por aplicar, recalcula el sha256 de cada fichero **ya
aplicado** y lo compara con el que se guardó al aplicarlo. Si no coincide, el runner
lanza una excepción y no continúa — ni reaplica la migración, ni la ignora. Es la
protección contra editar a mano una migración que alguien ya aplicó en otro entorno
(su base habría quedado con un esquema distinto del que describe el fichero, y sin
esta comprobación nadie se enteraría).

`php bin/console.php db:status` hace la misma comprobación, así que también sirve
para detectar el problema sin intentar migrar.

## Bloqueo de concurrencia

`migrar()` toma un `pg_advisory_lock` (clave fija de la aplicación,
`MigrationRunner::ADVISORY_LOCK_KEY`) antes de mirar qué falta por aplicar, y lo
libera al terminar (incluso si algo falla). Si dos procesos ejecutan `db:migrate` a
la vez, el segundo espera a que el primero termine en vez de aplicar la misma
migración dos veces en paralelo.

## El baseline de una base preexistente

`secretario` (la base de desarrollo) tenía ya sus tablas (`apuntes`, `personas`,
etc.) antes de que existiera este runner, creadas directamente por
`SchemaInstaller` a partir de `schema/postgres.sql`. Para esa base, ejecutar la
migración `0001_inicial.sql` de verdad no tiene sentido: su contenido son
`CREATE TABLE IF NOT EXISTS` que no harían nada, pero además una migración futura
podría no ser idempotente y sí causar daño si se reejecutase sobre datos reales.

Regla aplicada por el runner: si la tabla `apuntes` ya existe **y** no hay ninguna
fila en `schema_migrations`, la migración `0001` se marca como aplicada
directamente (un `INSERT` en `schema_migrations` con su checksum), sin ejecutar su
SQL. A partir de ahí se comporta como cualquier otra migración: si alguien edita
`0001_inicial.sql`, el checksum guardado en el baseline dejará de coincidir y
`db:migrate`/`db:status` fallarán igual que en cualquier otra base.

Esta regla solo se evalúa dentro de `migrar()` (aplica la marca), no dentro de
`estado()` (solo lee): un `db:status` ejecutado antes del primer `db:migrate` en una
base con este historial mostrará la `0001` como "pendiente" hasta que se ejecute
`db:migrate` una vez.

Comprobado en la base real: `secretario` tenía 423 apuntes antes de aplicar esta
fase; tras `php bin/console.php db:migrate` (ejecutado dos veces seguidas, la
segunda sin ningún efecto) la tabla `schema_migrations` queda con la fila
`0001` marcada como aplicada y los 423 apuntes siguen intactos.

La Fase 6 añade `migraciones/0008_identidades.sql` (identidades, TOTP, `rutas_acceso`).
La siembra de rutas e identidad `scl` es `AccesoSeeder`, no SQL de la migración, y se
invoca después de `AmbitoSeeder` (hace falta un centro al que vincular).

La Fase 9 (parcial) añade `migraciones/0011_vinculo_usuario_centro.sql`: `personas.email`,
únicas `(centro_id, iniciales)` en vez de iniciales globales, y una identidad por persona.

## `db:install`

`db:install` pasa a ser `db:migrate` + semillas (`SchemaInstaller::install()`):
aplica las migraciones pendientes y luego siembra `conceptos` (desde
`CatalogoConceptos`), el usuario de aplicación (`APP_USER`/`APP_PASSWORD`) y la fila
inicial de `configuracion`. Es lo que usan `composer db:install` y
`php bin/console.php import:excel` antes de importar.

## Datos de desarrollo desechables (recordatorio de D9)

Mientras no haya datos reales (el usuario avisará), una migración puede recrear
tablas si eso la simplifica. A partir de ese aviso, toda migración debe preservar
los datos existentes y probarse contra una copia antes de aplicarla. Esta fase no
ha necesitado ninguna migración destructiva: `0001_inicial.sql` es exactamente el
contenido anterior de `schema/postgres.sql`.

## Por qué ya no hay SQLite (D9)

El soporte de SQLite se ha retirado por completo:

- El PHP del host (8.5.10) no tiene `pdo_sqlite`.
- El stack Docker del proyecto y el golden master (Fase 0) solo usan Postgres.
- Mantener dos dialectos habría duplicado el coste de cada migración futura, y
  varias restricciones previstas (cuadre de asientos, índices parciales,
  constraints diferidas de la Fase 3) no tienen equivalente razonable en SQLite.

Cambios concretos:

- `schema/` ha desaparecido (era `postgres.sql` + `sqlite.sql`); su contenido vive
  ahora en `migraciones/0001_inicial.sql`.
- `ConnectionFactory::fromEnv()` ya no tiene rama SQLite: si `DB_DRIVER` no es
  `pgsql`, lanza `RuntimeException` con un mensaje explícito.
- `.env.example` ya no menciona `DB_PATH`/`sqlite`.
- `tests/integration/SchemaInstallerTest.php` ya no intenta usar `pdo_sqlite` como
  alternativa: corre siempre contra `secretario_test` (ver más abajo).

## Aislamiento de los tests que usan una base real

`GoldenMasterTest`, `SchemaInstallerTest` y `MigrationRunnerTest` comparten el mismo
mecanismo de aislamiento, factorizado en el trait
`Tests\Soporte\BaseDeDatosAislada` (`tests/Soporte/BaseDeDatosAislada.php`):

- `saltarSiNoHayPgsql()` — `markTestSkipped` si no hay driver `pdo_pgsql`.
- `prepararBaseDeTestVacia(string $dbName = 'secretario_test')` — crea la base si no
  existe y deja el esquema `public` recién creado (`DROP SCHEMA public CASCADE` +
  `CREATE SCHEMA public`). Lanza si se le pide `secretario` explícitamente.

Antes de esta fase, `SchemaInstallerTest` cayó en la rama `pgsql` porque el PHP del
host no tiene `pdo_sqlite`, y con eso **corría `SchemaInstaller::install()` contra la
base de desarrollo `secretario`**, sembrando conceptos/usuario/configuración ahí en
cada `composer test`. Ahora usa el mismo trait que `GoldenMasterTest` y opera
siempre sobre `secretario_test`.

`MigrationRunnerTest` usa el mismo trait pero con directorios de migraciones
sintéticos y temporales (no `migraciones/` de producción), para poder mutar el
contenido de un fichero y comprobar la detección de checksum sin tocar la migración
real `0001_inicial.sql`.

## Comandos

```bash
php bin/console.php db:migrate   # aplica pendientes (composer db:migrate)
php bin/console.php db:status    # lista aplicadas y pendientes (composer db:status)
php bin/console.php db:install   # db:migrate + semillas (composer db:install)
```
