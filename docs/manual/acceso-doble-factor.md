# Código de seguridad de seis dígitos

- Ruta: `/cuenta/totp`, `/totp-activar`, `/totp-verificar`, `/totp-codigos`
- Menú: menú del nombre (esquina de arriba) → 2FA. Las pantallas de activación y de verificación salen solas al entrar cuando hacen falta
- Quién: cualquier persona; obligatorio para el secretario del centro

## Para qué sirve

Añade una comprobación más al entrar, además de la contraseña: un código de seis dígitos que genera una aplicación del móvil y cambia cada treinta segundos. Así, quien sepa la contraseña tampoco entra sin el móvil. Es obligatorio en las cuentas de secretario del centro y opcional en las personales.

## Cómo se usa

1. Entrar en «2FA» desde el menú del nombre y pulsar «Activar segundo factor». En las cuentas de secretario, esta pantalla aparece sola la primera vez.
2. Escanear el código QR con la aplicación (Google Authenticator, Aegis y similares). Si la cámara no va, abrir «Introducir clave manualmente» y teclear la clave.
3. Escribir el código de seis dígitos que muestra la aplicación y pulsar «Confirmar».
4. Aparecen ocho códigos de recuperación con el formato `XXXX-XXXX`. Guardarlos en un sitio seguro, fuera del móvil.
5. En los accesos siguientes, tras la contraseña, el programa pide el código de seis dígitos; en esa casilla vale también un código de recuperación.

## Reglas que conviene saber

- Los códigos de recuperación se muestran **una sola vez** y cada uno sirve una única vez.
- El código de seis dígitos vale mientras está en pantalla; se admiten también los treinta segundos anteriores y posteriores, por si el reloj del móvil va desajustado.
- Cinco códigos erróneos seguidos bloquean la cuenta durante quince minutos.
- Una vez activado, la pantalla solo informa de que está activo: no hay opción de desactivarlo por cuenta propia.
- Si se pierden el móvil y los códigos de recuperación, no hay forma de entrar por cuenta propia: hay que pedir ayuda a quien administra el programa.

## Problemas frecuentes

- **«Código TOTP incorrecto» o «Código incorrecto»**: el código ha caducado o el reloj del móvil está desajustado. Esperar al siguiente y comprobar la hora automática del teléfono.
- **«No hay un secreto TOTP pendiente de confirmar»**: se recargó la pantalla antes de confirmar. Volver a pulsar «Activar segundo factor» y escanear el QR nuevo.
- **«Debe confirmar el segundo factor para entrar como secretario»**: hay que activarlo antes de cambiar a secretario del centro.
