# Novedades (changelog)

- Ruta: `/changelog`
- Menú: No sale en el menú: enlace en la versión del pie (login, registro y pantallas autenticadas)
- Quién: cualquiera, también sin entrar

## Para qué sirve

Lista los cambios recientes del programa. La versión mostrada en el login enlaza
aquí.

## Cómo se usa

Pulsar la versión (p. ej. `v0.1.3`) en el pie de entrar, registrarse o cualquier pantalla del programa.

## Reglas que conviene saber

- El texto vive en `CHANGELOG.md` en el repositorio.
- Se actualiza al preparar un tag de despliegue, no en cada commit.
- `deploy.sh` escribe `var/version.json` con el tag desplegado; si falta, se usa
  el fichero `VERSION` del repositorio o el tag git del checkout.

## Problemas frecuentes

- **Muestra «desarrollo»**: en local aún no se ha generado `var/version.json`
  (`php tools/release/escribir_version.php`).
