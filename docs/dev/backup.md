# Copias de seguridad PostgreSQL

Secretario guarda todos los datos en PostgreSQL (`secretario`). Las copias nuevas usan
**SQL plano** (`pg_dump -Fp`): texto legible, restaurable con `psql`, más portable entre
versiones cercanas que el formato custom (`.dump`).

Al generar o restaurar se eliminan automáticamente `SET` de parámetros que el servidor no
conoce (p. ej. `transaction_timeout` de clientes PG 17 sobre servidor PG 15). Las copias
antiguas en formato `.dump` siguen pudiendo restaurarse.

## Pantalla web

Menú **Inicio → Copias** (`/copias`): crear copia, descargar las guardadas en el servidor
y restaurar (desde el servidor o subiendo un `.sql` / `.dump` local). Requiere sesión de
centro con TOTP confirmado. En el servidor hay un tope de 5 copias; al superarlo la API
responde `codigo: limite_copias` y la pantalla ofrece borrar la más antigua.

## CLI

Desde el host (`.env` con `127.0.0.1:5455`) o dentro del contenedor PHP:

```bash
php bin/console.php db:backup
php bin/console.php db:backup --output=/ruta/mia/copia.sql

php bin/console.php db:restore --file=var/backups/secretario_20260912_104500.sql --force
```

Por defecto las copias se guardan en `var/backups/secretario_YYYYMMDD_HHMMSS.sql`
(contenido gitignored; el directorio existe en el repo). La restauración exige `--force`
porque sobrescribe la base del DSN.

En Docker, PHP-FPM (`www-data`) debe poder escribir en `var/backups`:

```bash
chmod 775 var/backups    # si el grupo del contenedor coincide con el del host
# o, en desarrollo:
chmod 777 var/backups
```

Atajos Composer:

```bash
composer db:backup
composer db:restore -- var/backups/secretario_20260912_104500.sql --force
```

Opcional en `.env`: `PG_DUMP`, `PG_RESTORE`, `PG_PSQL` con rutas concretas del cliente.

## Scripts del stack Docker

Alternativa sin PHP, ejecutando herramientas en el contenedor `secretary-db` (PG 15):

```bash
cd /home/dani/docker_images/secretary
./backup.sh
./backup.sh /tmp/mi_copia.sql
./restore.sh backups/secretario_20260912_104500.sql --force
```

Las copias por defecto van a `/home/dani/docker_images/secretary/backups/`.

## Comprobar tras restaurar

```bash
php bin/console.php db:status
```

El volcado incluye la tabla `schema_migrations`. Si el backup es anterior a una
migración reciente del código, `db:status` puede mostrar pendientes; en ese caso
aplica `db:migrate` solo si procede (ver `docs/dev/migraciones.md`).

## Qué no incluye la copia

- Ficheros Excel (`.xlsm`) — están fuera de la base; archívalos aparte si los necesitas.
- `.env` — la clave `APP_KEY` cifra secretos TOTP en la base; al restaurar en otro
  entorno necesitas la misma `APP_KEY` o habrá que reconfigurar el segundo factor.

## Copia a nivel de volumen (opcional)

Parar el contenedor `db` y archivar `/home/dani/docker_images/secretary/db/database/`
copia el directorio de datos de Postgres tal cual. Es menos portable que `pg_dump`; úsalo
solo para recuperación rápida en la misma máquina y versión de Postgres.
