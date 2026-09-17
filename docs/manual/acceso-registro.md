# Darse de alta

- Ruta: `/registro`, `/registro-enviado`, `/confirmar-email`
- Menú: No sale en el menú: se llega desde el enlace «Registrarse» de la pantalla de entrada
- Quién: cualquier persona

## Para qué sirve

Sirve para crear una cuenta propia y llevar el libro personal: las cuentas de cada uno, separadas de las del centro. La cuenta nace dentro de un centro y, al crearla, el programa abre el libro personal y añade el nombre a la lista de personas de ese centro.

Tras registrarse, el programa envía un correo de bienvenida con un enlace para confirmar la dirección. La cuenta no permite entrar hasta confirmar el correo.

Un secretario de centro no se da de alta aquí: su cuenta la crea otro secretario desde la pantalla de centros.

## Cómo se usa

1. En la pantalla de entrada, pulsar «Registrarse». Si ya se había escrito el usuario o el correo, viene puesto.
2. Escribir el usuario: empieza por letra, de 2 a 32 caracteres, con letras, números, puntos o guiones.
3. Escribir el correo y, si se quiere, el nombre completo; si se deja vacío, se usa el usuario.
4. Si hay más de un centro, elegir el centro en el desplegable.
5. Escribir la contraseña dos veces: al menos seis caracteres.
6. Pulsar «Crear cuenta». Aparece la pantalla «Revise su correo».
7. Abrir el mensaje recibido y pulsar el enlace de confirmación (válido 48 horas).
8. Volver a la pantalla de entrada e iniciar sesión con el usuario y la contraseña elegidos.

Si no llega el correo, en «Revise su correo» se puede pulsar «Reenviar correo».

## Reglas que conviene saber

- Cada correo puede tener una sola cuenta.
- Las cuentas personales no necesitan código de seguridad de seis dígitos, aunque se puede activar después.
- Si en el programa todavía no hay ningún centro creado, no se puede registrar nadie: el botón queda desactivado.
- Hay otra forma de tener cuenta personal sin pasar por aquí: que el secretario escriba el correo en la ficha de la persona, dentro de la lista de nombres del centro (esa cuenta queda activa sin confirmación por correo).

## Problemas frecuentes

- **«Ese usuario ya existe»**: elegir otro usuario.
- **«Ese correo ya tiene una cuenta»**: entrar con ese correo desde la pantalla de entrada.
- **«Ese correo ya está asignado a un nombre»**: el secretario ya creó la cuenta personal desde el centro. Basta entrar con ese correo.
- **«Confirme su correo antes de entrar»**: abrir el enlace del correo o reenviarlo desde `/registro-enviado`.
- **«El enlace ha caducado»**: solicitar un nuevo correo desde «Reenviar correo».
- **«No se pudo enviar el correo de confirmación»**: el administrador debe revisar la configuración SMTP (`MAIL_HOST`, `MAIL_PORT`, `MAIL_FROM`, `APP_URL` en el entorno).
- **«El usuario debe empezar por letra y tener 2-32 caracteres»**: quitar espacios, acentos y signos raros.
- **«Las contraseñas no coinciden»** o **«La contraseña debe tener al menos 6 caracteres»**: repetir la contraseña con cuidado.
- **«Todavía no hay ningún centro. Pida a un secretario que lo cree.»**: hay que esperar a que exista un centro.
- **«Indique el centro»**: hay varios y no se ha elegido ninguno.
