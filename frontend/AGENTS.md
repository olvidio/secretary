# Guía frontend — Secretario

Pantallas HTML + JS mínimo. Sin reglas de negocio: la lógica vive en `src/` y el frontend solo pinta y llama a `/api/...`.

## Estructura

```text
frontend/<modulo>/view/*.php     # fragmentos incluidos en el layout
frontend/shared/
  config/routes.php              # rutas GET de pantalla → PageController::page
  config/CatalogoMenus.php       # ítems y grupos por layout (excel / burger)
  http/PageController.php        # login, registro, TOTP, páginas del centro y /yo
  view/layout.php                # cinta «tipo excel»
  view/layout_burger.php         # menú hamburguesa (categorías a la izquierda)
  view/layout_yo.php             # nivel 1 (persona)
  view/_menu_usuario.php         # desplegable del nombre (preferencias de cuenta)
frontend/acceso/view/cuenta_*.php  # Mail, Contraseña, 2FA, Layout, Idioma, Centro, Tipo
public/
  css/app.css
  js/app.js                      # api(), esc(), formObj(), fmtFecha()
  js/yo.js                       # pantallas /yo (resumen, lista, banco CSV, categorías, remesa)
  js/layout_burger.js            # cambio de categoría en el layout burger
```

Las APIs REST están en `src/shared/config/routes.php` (controladores en `src/.../infrastructure/http/`).

El layout del centro lo elige cada usuario (`identidades.layout`: `excel` o `burger`) en **usuario → Layout**. El nombre de la esquina abre Mail, Layout, Idioma, Centro y Tipo. `/yo` sigue con `layout_yo.php` y el mismo menú de usuario.

## Añadir una pantalla nueva

1. Crear `frontend/<modulo>/view/<pantalla>.php` (solo HTML + `<script>` inline si hace falta).
2. Registrar la ruta en `frontend/shared/config/routes.php` (path, vista, clave `nav`).
3. Añadir el ítem en `frontend/shared/config/CatalogoMenus.php` (y al grupo de cada layout, o a `pantallasSinMenu()` si no debe salir en el menú).
4. Si la pantalla usa API nueva: rutas en `src/shared/config/routes.php`, filas en `CatalogoRutas`, `db:migrate` (AccesoSeeder).
5. Crear `docs/manual/<clave>.md` (o añadir la ruta a uno existente) con el esqueleto: para qué sirve, cómo se usa, reglas, problemas frecuentes. La ayuda de IA solo lee ese corpus (`docs/dev/ayuda_ia.md`). El test `ManualCubrePantallasTest` falla si la ruta GET no aparece en una línea «Ruta:».

Parámetros extra de la misma vista (p. ej. cuenta P/G): ver el array `$pages` y el bloque `if ($nav === '...')` en `routes.php`.

## Llamadas a la API

Usar siempre `api(url, opts)` de `public/js/app.js`:

- Añade cabecera `Accept: application/json` y `X-CSRF-Token`.
- Objetos en `body` se serializan a JSON con `_csrf` incluido.
- `FormData` (subida de ficheros): pasar directamente en `body`; no usar `Content-Type` manual.

Patrón habitual:

```javascript
const r = await api('/api/...');
if (!r.ok) return alert(r.error || 'Error');

const s = await api('/api/...', { method: 'POST', body: { campo: valor } });
if (!s.ok) return alert(s.error);
```

## Errores visibles para el usuario

**Nunca** debe llegar al navegador un fatal PHP en una petición `/api/...`.

En el controlador HTTP (`src/.../Controller.php`):

- Errores previstos (`InvalidArgumentException`, `RuntimeException`) → `ContestarJson::error($e->getMessage())` (código 400/404 si procede).
- Respuesta JSON: `{ "ok": false, "error": "mensaje legible" }`.

En la vista / JS:

- Tras cada `api(...)`, comprobar `!r.ok` y mostrar el mensaje con **`alert(r.error)`** o un párrafo `.ok` / `.muted` en pantalla.
- Texto de éxito no crítico: `#msg-*` con clase `ok`, no `alert`.
- Acciones destructivas: `confirm(...)` antes del POST.
- Si la respuesta no es JSON (p. ej. error 500 sin capturar), `api()` devuelve `{ ok: false, error: 'Respuesta no JSON' }` — tratarlo igual con `alert`.

Descargas de ficheros (`GET` binario, p. ej. volcados): enlace directo `<a href="/api/...">` o `window.location`; no usar `api()`.

## Seguridad en vistas

- Datos del servidor en HTML: `htmlspecialchars(..., ENT_QUOTES)`.
- Datos insertados desde JS en el DOM: función `esc()` de `app.js`.
- Mutaciones siempre por POST/PUT/DELETE con CSRF (automático vía `api()`).

## Estilo y UX

- Reutilizar clases existentes: `grid-form`, `muted`, `ok`, `peligro`, tablas sin framework.
- Carga inicial en `DOMContentLoaded`; botones deshabilitar durante POST largos.
- Sin librerías JS externas salvo que el proyecto ya las use.

## Qué no hacer

- SQL, PDO, reglas contables o validación de dominio en `.php` de `frontend/`.
- Confiar en que PHP mostrará excepciones al usuario en APIs.
- Duplicar helpers que ya están en `app.js`.

## Docs relacionadas

- Acceso y CSRF: `docs/dev/acceso.md`
- Arquitectura general: `docs/dev/arquitectura.md`
- Backend / DDD: `AGENTS.md` en la raíz del repo
