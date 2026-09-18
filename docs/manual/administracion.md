# Administración de plataforma

- Ruta: `/admin`, `/admin/planes`, `/admin/centros`, `/admin/usuarios`
- Menú: Administración (solo usuario admin)
- Quién: administrador de plataforma (`APP_ADMIN_USER` / `APP_ADMIN_PASSWORD` en `.env`)

## Para qué sirve

Gestiona la plataforma sin entrar en la contabilidad de ningún centro: planes contables, altas y bajas de centros, y eliminación de cuentas de usuario.

## Cómo se usa

1. Entrar con el usuario admin (por defecto alias `admin`, contraseña `admin` si no se cambia en `.env`).
2. **Planes contables**: listar, crear, editar y borrar planes (no se borra uno en uso por algún centro). Con «Conceptos» se editan **todos** los códigos del plan (P y G), incluidos los 7x como plantilla por defecto del capítulo VII.
3. **Centros**: crear un centro con su secretario y plan; borrar un centro vacía antes sus datos contables.
4. **Usuarios**: ver todas las identidades y borrar las que no sean el admin de plataforma.

## Reglas que conviene saber

- El admin no ve menús de contabilidad ni puede operar en un centro concreto.
- Los secretarios crean usuarios de su centro en Parámetros → Centros, pero no pueden crear centros nuevos ni borrar usuarios globales.
- En el centro, solo el secretario puede cambiar las partidas **7x** (cap. VII); el resto de conceptos vienen del plan asignado al centro.
- La contraseña del admin se sincroniza desde `.env` al ejecutar `db:migrate`.

## Problemas frecuentes

- **No entra con admin**: compruebe `APP_ADMIN_USER` y `APP_ADMIN_PASSWORD` en `.env` y vuelva a migrar.
- **No se borra un plan**: algún centro lo tiene asignado; cámbielo antes en Configuración del centro.
