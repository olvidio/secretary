# Preferencias de la cuenta

- Ámbito: todos
- Ruta: `/cuenta/personal`, `/cuenta/mail`, `/cuenta/password`, `/cuenta/totp`, `/cuenta/layout`, `/cuenta/idioma`, `/cuenta/copias`, `/cuenta/centro`, `/cuenta/ambito`, `/cuenta/persona`, `/cuenta/tipo`, `/cuenta/baja`, `/confirmar-baja`
- Menú: menú del nombre (esquina de arriba) → Mensajes, **Ámbito** (si es secretario y tiene libro personal) o Centro (solo secretario sin libro personal), **Personal** (correo, contraseña, 2FA, layout, idioma y, en el libro personal, la copia), Remanente (libro personal), y solo si aplica Persona activa. Una línea separa **Salir**.
- Quién: cualquier persona; el apartado del centro, solo el secretario del centro

## Para qué sirve

**Personal** (`/cuenta/personal`) reúne el correo, la contraseña, el segundo factor, el aspecto de los menús, el idioma y, en el libro personal, el centro vinculado y la copia. Debajo del título, unas pestañas muestran un apartado cada vez. Los enlaces antiguos (`/cuenta/mail`, `/cuenta/password`, etc.) abren la pestaña correspondiente. El centro o el ámbito con el que se trabaja siguen en su propia entrada del menú.

## Cómo se usa

### Mail

Cambia el correo de la cuenta, que sirve para entrar igual que el usuario. Se escribe el nuevo y se pulsa **Guardar**: llega un mensaje al **nuevo** buzón con un enlace (48 h). Hasta confirmarlo, el login sigue siendo con el correo anterior. Si ya había un cambio pendiente, la pantalla lo indica.

### Contraseña

Se escribe la contraseña actual, la nueva dos veces y se guarda. La nueva debe tener al menos seis caracteres.

### Layout

Elige la disposición de los menús del centro: la cinta «tipo excel», con los grupos arriba, o el menú «burger», con las categorías a la izquierda. El libro propio no cambia.

### Idioma

Guarda la preferencia entre español y catalán. Al guardar, la pantalla se recarga con el idioma elegido.

### Centro

Elige el centro con el que se trabaja en esta sesión. Solo aparecen aquellos de los que la cuenta es secretaria. Si la misma identidad también tiene libro personal, en el menú verá **Ámbito** en lugar de Centro y Tipo por separado.

### Ámbito

Solo si **la misma identidad** es secretaria de algún centro **y** tiene libro personal o vínculo de persona. En un solo desplegable puede elegir **Mis cuentas (individual)** o cualquiera de sus centros. Al entrar, si aplica este caso, la pantalla de elección también lista el libro personal junto a los centros (no solo los centros).

### Persona activa

Solo sale en el menú si hay **más de un** vínculo aprobado a centros tipo n (caso excepcional). Con la regla actual de un solo centro por cuenta personal, normalmente no hace falta: el programa usa el único vínculo automáticamente.

### Tipo

Pantalla heredada: si tiene **Ámbito** en el menú, el enlace antiguo redirige allí. Las cuentas separadas (registro personal frente a registro de centro) deben usarse entrando con cada una desde «Elegir cuenta».

### Dar de baja la cuenta

Solo en **cuentas personales** (registro desde el login, sin rol de secretario). Resume qué se borrará y qué se conserva (remesas ya enviadas al centro, consentimientos legales). Tras confirmar en pantalla, llega un **correo con enlace** (48 h); hasta abrirlo la cuenta sigue activa. El enlace ejecuta el borrado definitivo y cierra la sesión si estaba abierta.

## Reglas que conviene saber

- Solo se puede pasar a secretario del centro si la cuenta está dada de alta como tal y tiene activado el código de seguridad de seis dígitos.
- Solo se puede pasar al libro personal si la identidad tiene al menos un vínculo de persona (incluido el ámbito del libro propio creado al registrarse).
- Si solo hay un modo posible, **Ámbito** no aparece en el menú (solo **Centro** o el libro personal, según el caso).
- Al cambiar el layout o el idioma, la pantalla se recarga para aplicar el cambio.

## Problemas frecuentes

- **«La contraseña actual no es correcta»**: hay que escribir la contraseña con la que se acaba de entrar.
- **«Ese correo ya tiene una cuenta personal»**: otra cuenta personal usa ese correo; hay que elegir otro.
- **No llega el correo de confirmación del cambio**: revisar spam o la configuración SMTP del servidor (en desarrollo local puede usarse `REGISTRO_AUTO_CONFIRMA_EMAIL=1`).
- **«Esta cuenta no es secretario de ningún centro»**: la opción de secretario queda desactivada.
- **«Sesión caducada»**: volver a entrar en el programa y repetir el cambio.
