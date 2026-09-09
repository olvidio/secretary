# Secretario

Programa de contabilidad P/G de un centro (equivalente al Excel *Programa Secretario* v8). Independiente de Orbix.

## Docker (recomendado)

Stack propio en `/home/dani/docker_images/secretary` (nginx + PHP 8.2-FPM + Postgres 15).

```bash
cd /home/dani/docker_images/secretary
docker compose up -d --build
docker compose exec php-fpm composer install
docker compose exec php-fpm php bin/console.php db:install
docker compose exec php-fpm php bin/console.php import:excel
```

Abrir http://127.0.0.1:8005 — usuario `scl`, contraseña `cambiar` (`.env`).

Adminer: http://127.0.0.1:8085 (servidor `db`, usuario/clave/base `secretario`).

Detalle: `docs/dev/docker.md`. Si la VPN bloquea el puerto 8005: `sudo ip route add throw 172.31.0.0/16 table 220` (Orbix ya tiene el equivalente para `172.19`).

## Arranque en el host (sin nginx)

Hace falta PHP 8.2+ con `pdo_pgsql`, bcmath, zip y simplexml. Postgres sigue siendo el del compose (puerto **5455**).

```bash
cd /home/dani/secretary
composer install
composer db:install
composer import
composer serve
```

`composer serve` queda en http://127.0.0.1:8088 como alternativa local.

## Migraciones

El esquema se versiona en `migraciones/` (nunca editando ficheros existentes). `db:install`
aplica las migraciones pendientes y siembra los datos base; `db:migrate` solo lo primero.

```bash
composer db:migrate   # aplica migraciones pendientes
composer db:status    # lista aplicadas y pendientes
```

Detalle: `docs/dev/migraciones.md`.

Ver también `PENDIENTE_USUARIO.md` y `docs/manual/usuario.md`.
