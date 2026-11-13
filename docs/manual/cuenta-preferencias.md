# Preferencias de la cuenta

- Ámbito: todos
- Ruta: `/cuenta/personal`, `/cuenta/mail`, `/cuenta/password`, `/cuenta/totp`, `/cuenta/layout`, `/cuenta/idioma`, `/cuenta/copias`, `/cuenta/centro`, `/cuenta/ambito`, `/cuenta/persona`, `/cuenta/tipo`, `/cuenta/baja`, `/confirmar-baja`
- Menú: menú del nombre (esquina de arriba) → Mensajes, **Ámbito** (cambiar de libro o de entidad y crear una entidad nueva), **Personal** (correo, contraseña, 2FA, layout, idioma y, en el libro personal, la copia), Remanente (libro personal), y solo si aplica Persona activa. Una línea separa **Salir**.
- Quién: cualquier persona; el apartado del centro, solo el secretario del centro

## Para qué sirve

**Personal** (`/cuenta/personal`) reúne el correo, la contraseña, el segundo factor, el aspecto de los menús, el idioma y, en el libro personal, el centro vinculado y la copia. Debajo del título, unas pestañas muestran un apartado cada vez. Los enlaces antiguos (`/cuenta/mail`, `/cuenta/password`, etc.) abren la pestaña correspondiente. El ámbito con el que se trabaja y el alta de una entidad nueva están en **Ámbito**.

## Cómo se usa

### Mail

Cambia el correo de la cuenta, que sirve para entrar igual que el usuario. Se escribe el nuevo y se pulsa **Guardar**: llega un mensaje al **nuevo** buzón con un enlace (48 h). Hasta confirmarlo, el login sigue siendo con el correo anterior. Si ya había un cambio pendiente, la pantalla lo indica.

### Contraseña

Se escribe la contraseña actual, la nueva dos veces y se guarda. La nueva debe tener al menos seis caracteres.

### Layout

Elige la disposición de los menús del centro: la cinta «tipo excel», con los grupos arriba, o el menú «burger», con las categorías a la izquierda. El libro propio no cambia.

### Idioma

Guarda la preferencia entre español y catalán. Al guardar, la pantalla se recarga con el idioma elegido.

### Ámbito

Sale siempre en el menú del nombre. Tiene dos partes:

- **Cambiar de ámbito.** En un solo desplegable se elige **Mis cuentas (individual)** o cualquiera de las entidades que lleva la cuenta (centros, asociaciones, fundaciones), y se pulsa **Cambiar**. Si la cuenta solo tiene un ámbito, el desplegable no aparece. Al entrar en el programa, quien tiene libro personal y además lleva alguna entidad ve la misma elección.
- **Nueva entidad.** Crea otro centro n, centro sg, asociación o fundación **con la misma cuenta**, sin registrar otro alias ni otro correo. Se elige el **Tipo**, se escribe la **Sigla** y el **Nombre** y se pulsa **Crear**. La cuenta queda como quien lleva la entidad (puede modificar e invitar a otras personas) y el programa abre un ejercicio para el año en curso. Si la cuenta ya tiene activado el segundo factor, la sesión pasa directamente a la entidad nueva; si no, la entidad queda creada y hay que activar antes el segundo factor en **Personal → 2FA** y elegirla después en el desplegable.
- **Eliminar entidad.** Solo aparece si la cuenta **administra** alguna entidad (quien tiene rol de solo consulta no la ve). Se elige la entidad, se escribe su **sigla** para confirmar y se pulsa **Eliminar entidad**. Se borra la entidad con todos sus datos: asientos, ejercicios, cuentas, nombres, remesas, cajas y bancos, y los accesos de quienes la llevaban. Las cuentas de usuario no se borran. Si era la entidad con la que se estaba trabajando, la sesión pasa al libro personal o a otra entidad; si no queda ninguna, se cierra la sesión.

Los enlaces antiguos `/cuenta/centro` y `/cuenta/tipo` llevan a esta pantalla.

### Persona activa

Solo sale en el menú si hay **más de un** vínculo aprobado a centros tipo n (caso excepcional). Con la regla actual de un solo centro por cuenta personal, normalmente no hace falta: el programa usa el único vínculo automáticamente.

### Dar de baja la cuenta

Solo en **cuentas personales** (registro desde el login, sin rol de secretario). Resume qué se borrará y qué se conserva (remesas ya enviadas al centro, consentimientos legales). Tras confirmar en pantalla, llega un **correo con enlace** (48 h); hasta abrirlo la cuenta sigue activa. El enlace ejecuta el borrado definitivo y cierra la sesión si estaba abierta.

## Reglas que conviene saber

- Solo se puede pasar a secretario del centro si la cuenta está dada de alta como tal y tiene activado el código de seguridad de seis dígitos.
- Solo se puede pasar al libro personal si la identidad tiene al menos un vínculo de persona (incluido el ámbito del libro propio creado al registrarse).
- La sigla de una entidad nueva no puede coincidir con la de otra que ya exista en el programa.
- Una entidad creada desde **Ámbito** es independiente de las demás: tiene sus propias cuentas, su ejercicio y sus usuarios.
- Eliminar una entidad no se puede deshacer. Conviene hacer antes una copia desde **Copias**, dentro de la entidad.
- El libro personal no se elimina desde **Ámbito**: para eso está **Dar de baja la cuenta**.
- Al cambiar el layout o el idioma, la pantalla se recarga para aplicar el cambio.

## Problemas frecuentes

- **«La contraseña actual no es correcta»**: hay que escribir la contraseña con la que se acaba de entrar.
- **«Ese correo ya tiene una cuenta personal»**: otra cuenta personal usa ese correo; hay que elegir otro.
- **No llega el correo de confirmación del cambio**: revisar spam o la configuración SMTP del servidor (en desarrollo local puede usarse `REGISTRO_AUTO_CONFIRMA_EMAIL=1`).
- **«Ya existe una entidad con ese código»**: la sigla elegida en **Nueva entidad** ya está en uso; hay que escribir otra.
- **«La sigla escrita no coincide con la de la entidad»**: para eliminar hay que escribir la sigla tal como sale entre paréntesis en el desplegable (no importan mayúsculas).
- **«Solo quien administra la entidad puede eliminarla»**: la cuenta tiene rol de solo consulta en esa entidad, o ya no tiene acceso a ella.
- **«Debe confirmar el segundo factor para entrar como secretario»**: la entidad existe, pero falta activar el código de seis dígitos en **Personal → 2FA** antes de entrar en ella.
- **«Sesión caducada»**: volver a entrar en el programa y repetir el cambio.
