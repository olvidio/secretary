# Docker de Secretario

El stack no comparte nada con Orbix. Vive en `/home/dani/docker_images/secretary` (nginx + PHP 8.2-FPM + Postgres 15 + Adminer). El código se monta desde `/home/dani/secretary`.

| Servicio | URL / puerto |
| --- | --- |
| Aplicación | http://127.0.0.1:8005 |
| Postgres (host) | `127.0.0.1:5455` |
| Adminer | http://127.0.0.1:8085 |

Dentro de PHP-FPM el DSN es `pgsql:host=db;port=5432;dbname=secretario` (lo inyecta Compose). Desde el host (Composer, PHPUnit) se usa el `.env`: `127.0.0.1:5455`.

```bash
cd /home/dani/docker_images/secretary
docker compose up -d --build
docker compose exec php-fpm php bin/console.php db:install
docker compose exec php-fpm php bin/console.php import:excel
```

No citar los valores `POSTGRES_*` en el `docker-compose.yml` (las comillas acaba metiéndolas en el usuario).

La VPN del equipo (tabla de rutas 220) ya excluye `172.17.0.0/16` y `172.19.0.0/16` (Orbix). Este stack usa `172.31.0.0/16`. Si tras un reconectado de VPN no abre http://127.0.0.1:8005:

```bash
sudo ip route add throw 172.31.0.0/16 table 220
```
