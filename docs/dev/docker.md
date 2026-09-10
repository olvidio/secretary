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

El Excel de un segundo centro se carga en **Centros** (al crearlo o después). `--centro=CODIGO` en CLI no pisa la configuración del primero. Nginx admite hasta 32 MB (`client_max_body_size`); PHP-FPM, `upload_max_filesize`/`post_max_size` 32M en `web/php.ini`.

No citar los valores `POSTGRES_*` en el `docker-compose.yml` (las comillas acaba metiéndolas en el usuario).

## VPN (FortiClient) y Docker

FortiClient usa la **tabla de rutas 220**. Sin un `throw`, el tráfico a `172.x` (redes Docker) entra en el túnel y `http://127.0.0.1:8005` deja de responder.

| Red | Uso |
| --- | --- |
| `172.17.0.0/16` | docker0 |
| `172.19.0.0/16` | Orbix |
| `172.31.0.0/16` | Secretario |

**Arreglo permanente (FortiClient):** instala el timer que reaplica los throws al arranque y cada minuto (FortiClient regenera la tabla al reconectar):

```bash
cd /home/dani/docker_images/secretary
sudo ./install-vpn-throw.sh
```

Atajo puntual: `sudo ./vpn-throw.sh`

**Si también usas strongSwan** (`/etc/swanctl/conectar-dlb*.sh`), añade en `/etc/swanctl/swanctl.conf` un bypass igual que Orbix (bloque `bypass-orbix-net`) para `172.31.0.0/16`, luego:

```bash
sudo swanctl --load-conns
```
