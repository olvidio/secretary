# Preferencias de la cuenta

- Ruta: `/cuenta/mail`, `/cuenta/password`, `/cuenta/layout`, `/cuenta/idioma`, `/cuenta/centro`, `/cuenta/persona`, `/cuenta/tipo`, `/cuenta/baja`
- Menú: menú del nombre (esquina de arriba) → Mail, Contraseña, 2FA, Layout, Idioma, Centro (secretario) o Copia personal (persona), y solo si aplica Persona activa o Tipo
- Quién: cualquier persona; el apartado del centro, solo el secretario del centro

## Para qué sirve

Son las pantallas donde cada uno ajusta su cuenta: el correo, la contraseña, el código de seis dígitos, el aspecto de los menús, el idioma y con qué centro o nombre trabaja. El doble factor se explica en su propio apartado; la copia del libro personal, en el de Copia personal. El resto tiene un formulario y un botón «Guardar».

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

Elige el centro con el que se trabaja en esta sesión. Solo aparecen aquellos de los que la cuenta es secretaria.

### Persona activa

Solo sale en el menú si hay **más de un** vínculo aprobado a centros tipo n (caso excepcional). Con la regla actual de un solo centro por cuenta personal, normalmente no hace falta: el programa usa el único vínculo automáticamente.

### Tipo

Solo sale en el menú si **la misma identidad** es secretaria de algún centro **y** tiene libro personal o vínculo de persona. Cambia el modo de la sesión (centro o Mis cuentas), sin borrar datos. Las cuentas separadas (registro personal frente a registro de centro) deben usarse entrando con cada una; no hace falta «Tipo».

### Dar de baja la cuenta

Solo en **cuentas personales** (registro desde el login, sin rol de secretario). Resume qué se borrará y qué se conserva (remesas ya enviadas al centro, consentimientos legales). Tras confirmar en pantalla, llega un **correo con enlace** (48 h); hasta abrirlo la cuenta sigue activa. El enlace ejecuta el borrado definitivo y cierra la sesión si estaba abierta.

## Reglas que conviene saber

- Solo se puede pasar a secretario del centro si la cuenta está dada de alta como tal y tiene activado el código de seguridad de seis dígitos.
- Solo se puede pasar al libro personal si la identidad tiene al menos un vínculo de persona (incluido el ámbito del libro propio creado al registrarse).
- Si solo hay un modo posible, **Tipo** no aparece en el menú.
- Al cambiar el layout o el idioma, la pantalla se recarga para aplicar el cambio.

## Problemas frecuentes

- **«La contraseña actual no es correcta»**: hay que escribir la contraseña con la que se acaba de entrar.
- **«Ese correo ya tiene una cuenta personal»**: otra cuenta personal usa ese correo; hay que elegir otro.
- **No llega el correo de confirmación del cambio**: revisar spam o la configuración SMTP del servidor (en desarrollo local puede usarse `REGISTRO_AUTO_CONFIRMA_EMAIL=1`).
- **«Esta cuenta no es secretario de ningún centro»**: la opción de secretario queda desactivada.
- **«Sesión caducada»**: volver a entrar en el programa y repetir el cambio.
