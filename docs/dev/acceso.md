# Acceso de dos niveles (D7, Fase 6)

Identidades con email (y alias opcional), TOTP RFC 6238 obligatorio para el centro,
códigos de recuperación, CSRF y autorización por tabla.

## Flujo

1. `GET /login` — usuario o email + contraseña. El alias `scl` sigue valiendo.
   Si el usuario no existe, se invita a `GET /registro`. El enlace **Registrarse**
   del login inicia el mismo alta: identidad de **persona** (nivel 1) en un centro
   (el único, o el elegido si hay varios). Tras crear la cuenta entra en `/yo`.
   Un secretario de centro no se auto-registra: se da de alta en `/centros`.
   El alta pública exige aceptar las Condiciones de uso (casilla + correo de
   confirmación). Ver `docs/dev/legal.md`.
2. Identidad de **centro** sin TOTP confirmado → `GET /totp-activar` (clave e URI
   `otpauth://`). Confirmar con 6 dígitos. Se muestran **una vez** 8 códigos
   `XXXX-XXXX`.
3. Logins posteriores → `GET /totp-verificar` (TOTP o un código de recuperación).
4. Si hay varios centros, `GET /elegir-centro`.
5. Identidad solo de **persona**: TOTP opcional; sin él entra al completar la
   contraseña y cae en `/yo` (libro X). Las APIs y pantallas del centro contestan 403
   (redirección a `/yo`). Una sesión de centro no entra en `/yo`.

El usuario `usuarios.scl` se migra a `scl@secretario.local` con alias `scl` y vínculo
de `admin` al centro de `configuracion`. Cada secretario extra (`scl2`, …) se da de
alta en `/centros` y queda vinculado **solo** a ese centro (`identidad_centro`): no
ve las cuentas ni los nombres de los demás. Si hay personas con centro, se siembra
también `yo@secretario.local` (alias `yo`) vinculada a la primera persona
(`AccesoSeeder`, después de `AmbitoSeeder`).

El correo de un nombre (pantalla Nombres) crea o enlaza una identidad de **persona**
a esa fila. Esa persona ya pertenece al centro de la sesión (`personas.centro_id`);
el login con ese correo entra en `/yo` y no puede leer el libro del centro.

## TOTP y cifrado

- HMAC-SHA1, 30 s, 6 dígitos, ventana ±1 (`TotpRfc6238`).
- El secreto se guarda cifrado (AES-256-GCM) con material derivado de `APP_KEY`
  (`hash('sha256', APP_KEY, true)`). Sin `APP_KEY` la aplicación no arranca las
  pantallas de login.
- Los códigos de recuperación se almacenan como `sha256(codigo + '|' + APP_KEY)`.

Añade a `.env` (32+ caracteres aleatorios, distintos en cada entorno):

```bash
APP_KEY=$(php -r 'echo bin2hex(random_bytes(32)), "\n";')
```

## Sesión y CSRF

- Cookie: `HttpOnly`, `SameSite=Lax`, `Secure` solo si HTTPS.
- `session_regenerate_id(true)` tras la contraseña correcta y al completar el 2FA.
- Mutaciones `POST`/`PUT`/`PATCH`/`DELETE`: campo `_csrf` o cabecera `X-CSRF-Token`.
- `GET /api/csrf` es público (el token vive en la sesión). El JS de `public/js/app.js`
  envía la cabecera desde `meta[name=csrf-token]`.

## Autorización

`CatalogoRutas` es la fuente de `rutas_acceso`. Lo que no está en la tabla se
deniega (default deny). Ámbitos: `publico`, `pendiente` (contraseña ok, 2FA no),
`autenticado`, `centro`, `persona`.

El centro de la sesión (`$_SESSION['centro_id']`) tiene preferencia en
`ResolverAmbitoActual`; si falta, se usa `configuracion.centro` como hasta ahora.

Cada identidad elige disposición de menús (`identidades.layout`: `excel` o `burger`)
e idioma (`identidades.idioma`: `es` o `ca`) en el menú del nombre (esquina).
Páginas: `/cuenta/mail`, `/cuenta/password`, `/cuenta/totp`, `/cuenta/layout`,
`/cuenta/idioma`, `/cuenta/centro`, `/cuenta/tipo`. APIs bajo `/api/preferencias`
(ámbito `autenticado`), incluidas `POST .../password` y `POST .../totp/preparar`
+ `.../totp/confirmar` para activar el segundo factor desde la cuenta ya iniciada.

**Tipo:** nivel 2 = secretario de centro; nivel 1 = libro personal. Solo se puede
pasar al otro nivel si la identidad tiene el vínculo correspondiente
(`identidad_centro` o `identidad_persona`).

## Bloqueo

5 fallos de contraseña o de 2FA → 15 minutos. Un acierto de contraseña limpia el
contador; el 2FA vuelve a contar por su lado.

## API de login

`POST /api/login` y `POST /login` (formulario). JSON necesita CSRF previo
(`GET /api/csrf` o la cookie de sesión de un `GET /login`).

`POST /api/registro` y `POST /registro`: usuario (alias), correo, contraseña
(mínimo 6, repetida), nombre opcional y `centro_id` si hay más de un centro.
