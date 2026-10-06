# Administración de plataforma (solo admin)

> Este fichero lleva prefijo `_`: **no entra en la ayuda con IA** del centro ni del libro personal. Documentación interna para quien entra con el usuario admin de plataforma.

- Ruta: `/admin`, `/admin/planes`, `/admin/centros`, `/admin/usuarios`, `/admin/legal`, `/admin/copias`
- Menú: Administración (solo usuario admin)
- Quién: administrador de plataforma (`APP_ADMIN_USER` / `APP_ADMIN_PASSWORD` en `.env`)

## Para qué sirve

Gestiona la plataforma sin entrar en la contabilidad de ningún centro: planes contables, altas y bajas de centros, y eliminación de cuentas de usuario.

## Cómo se usa

1. Entrar con el usuario admin (por defecto alias `admin`, contraseña `admin` si no se cambia en `.env`).

### Planes contables (`/admin/planes`)

2. En la tabla de planes, por cada fila:
   - **Editar** carga código y nombre en el formulario «Nuevo plan».
   - **Conceptos** abre el editor de códigos de ese plan.
   - **Borrar** elimina el plan (pide confirmación; falla si algún centro lo usa).
3. Formulario «Nuevo plan»: rellenar código y nombre, **Guardar** (o **Limpiar** para vaciar el formulario).
4. Editor de conceptos (tras **Conceptos**):
   - Pestañas **P — Personal** y **G — General** cambian de libro.
   - **Añadir concepto** agrega una fila; **Quitar** en una fila la elimina.
   - **Guardar conceptos** persiste los cambios del libro activo.
   - **Exportar** descarga un JSON; **Importar** lo sustituye (mismo formato: objeto con `plan` y `conceptos`, o solo la lista `conceptos`).
   - Si un plan no tenía conceptos cargados, al abrir el editor se rellenan con el catálogo H16n. Los códigos 7x son plantilla del capítulo VII.

### Entidades (`/admin/centros`)

5. Rellenar el formulario «Nueva entidad» (sigla, nombre, plan, tipo de cierre, tipo de entidad, fechas del ejercicio, alias y nombre del secretario, y contraseña; Excel opcional) y pulsar **Crear entidad**. El tipo es **centro n**, **centro sg**, **asociación** o **fundación**. Al cambiar el tipo, el plan propuesto pasa a H16n, H16s o Club.
6. La tabla muestra sigla, nombre, tipo de entidad, plan y tipo de cierre. **Borrar** elimina la entidad y todos sus datos (pide confirmación). El libro personal (tipo interno `p`) no sale en esta lista.

### Usuarios (`/admin/usuarios`)

7. Listado de cuentas: alias, correo, nombre, y listas de **Centros** (secretario, con rol) y **Personas** (nombre en cada entidad y, si existe, «Libro personal (Mis cuentas)»). **Quitar** en un centro quita solo ese acceso de secretario (no borra la contabilidad; falla si es el único secretario activo del centro). **Quitar** en una persona del centro desvincula la cuenta del nombre (el histórico en la entidad se conserva); el libro personal no se quita desde aquí. **Reiniciar 2FA** (por usuario, salvo la fila del admin de plataforma y la suya propia) borra el TOTP y los códigos de recuperación; el usuario deberá activar de nuevo el segundo factor con un QR en el próximo acceso. **Borrar** en cuentas **personales** (borrado inmediato; correo al usuario), en cuentas **sin libro ni entidad** (borrado inmediato; correo al usuario) o de **secretario** (baja en standby 60 días: se desactiva el acceso, se desvincula del centro sin borrar contabilidad, se avisa por correo al secretario y a las cuentas personales vinculadas a ese centro). Si era el único secretario, el resumen lo advierte. **Reactivar** aparece mientras dure el standby. Tras el plazo, un job (`php bin/console.php cuentas:purga-bajas-centro`) elimina credenciales; si la identidad tenía también libro personal, queda solo como cuenta personal. No aparece **Borrar** en el admin de plataforma.
8. **Correos con varias cuentas (legacy, D15):** si el mismo correo tiene más de una identidad activa, aparece un bloque para **fusionar**: elija la cuenta a **conservar** (alias principal); el resto se elimina tras pasar sus mandatos de centro y vínculos de persona a la principal. No toca apuntes ni centros. Consola: `php bin/console.php cuentas:duplicados-correo` y `cuentas:fusionar-correo --email=... --principal=ID --confirmar`.

### Legal (`/admin/legal`)

8. Buscar por correo, alias o identificador numérico de usuario.
9. **Ver expediente** muestra la cronología de aceptaciones (registro, confirmación de correo, declaraciones de responsable de nombres).
10. **Descargar PDF** genera un expediente con fechas, textos aceptados, hashes SHA-256, IP, navegador y anexos con las condiciones y la privacidad vigentes en cada momento. **Descargar HTML** obtiene el mismo contenido en un fichero imprimible.

## Reglas que conviene saber

- El admin no ve menús de contabilidad ni puede operar en un centro concreto.
- Los secretarios crean usuarios de su centro en Parámetros → Centros, pero no pueden crear entidades nuevas ni borrar usuarios globales. Una asociación o una fundación no tiene esa pantalla de Centros: los usuarios se gestionan en Configuración.
- En el centro, solo el secretario puede cambiar las partidas **7x** (cap. VII); el resto de conceptos vienen del plan asignado al centro.
- La contraseña del admin se sincroniza desde `.env` al ejecutar `db:migrate`.

## Problemas frecuentes

- **No entra con admin**: compruebe `APP_ADMIN_USER` y `APP_ADMIN_PASSWORD` en `.env` y vuelva a migrar.
- **No se borra un plan**: algún centro lo tiene asignado; cámbielo antes en Configuración del centro.
