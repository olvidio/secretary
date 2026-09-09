# Pendiente de tu parte

Decisiones tomadas por defecto al implementar. Corrige aquí o en el chat si algo no encaja.

| Tema | Valor actual |
| --- | --- |
| Nombre | Secretario (carpeta `secretary`) |
| Docker | `/home/dani/docker_images/secretary` (nginx **8005**, Postgres **5455**, Adminer **8085**). Independiente de Orbix. |
| Base de datos | PostgreSQL `secretario` / `secretario`. Desde el host: puerto **5455**. Dentro del contenedor PHP: host `db`. |
| Usuario de la app | `scl` / `cambiar` (cámbialo en `.env`: `APP_USER`, `APP_PASSWORD`). Tras Fase 6 el alias `scl` es una identidad de centro: hay que confirmar TOTP la primera vez. `APP_KEY` cifra el secreto. Otro centro: en **Centros** crea la entidad y su secretario (`scl2`). Nivel 1: pon el correo en **Nombres**, o el demo `yo` / `cambiar` en `/yo`. |
| Idioma | Español |
| Tipo de cierre de mes | `vivienda` (concepto P 21 + G 11). El centro del Excel es **Montagut** (no empieza por `agd` ni `sss+`). Si fuera agd/sss+, pon `tipo_cierre=necesidades` en Configuración |
| Excel | `moviments2026.xlsm` no se versiona. Importar desde **Centros** (al crear o en este centro) o con `composer import` / `docker compose exec php-fpm php bin/console.php import:excel`. En pruebas: **Vaciar datos** en Centros y volver a importar. |

## Cómo arrancar

```bash
cd /home/dani/docker_images/secretary
docker compose up -d --build
docker compose exec php-fpm php bin/console.php db:install
docker compose exec php-fpm php bin/console.php import:excel
```

Login: usuario `scl` (o `scl@secretario.local`), contraseña `cambiar`, más TOTP de una app de autenticación. URL: http://127.0.0.1:8005

Nivel personal (Fase 7): alias `yo` / `yo@secretario.local`, misma contraseña, **sin** TOTP. Entra en `/yo`. Se siembra al haber al menos una persona con centro (tras `import:excel` o al crear nombres). Remesas (Fase 8): desde `/yo/remesas` se envía el mes; el centro las ve en `/remesas`.

