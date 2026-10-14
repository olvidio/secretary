# Mantener el manual y las preguntas pendientes

## 1. Comprobar que todas las pantallas tienen ficha

Cada ruta GET de pantalla debe aparecer en una línea `- Ruta:` de `docs/manual/*.md`.

```bash
php vendor/bin/phpunit tests/unit/ManualCubrePantallasTest.php
php vendor/bin/phpunit tests/unit/ManualPorAmbitoTest.php
```

Al añadir una pantalla (`frontend/shared/config/routes.php`), crear o ampliar el `.md` correspondiente (ver `frontend/AGENTS.md`).

Tras cambios grandes de producto, repasar manualmente las fichas de las pantallas tocadas: la ayuda **no** lee `docs/dev/`.

## 2. Preguntas que la IA no pudo contestar

Cuando el modelo responde `SIN_RESPUESTA` o no cita fuentes válidas, la consulta se guarda en `ayuda_consultas` con `resuelta = false` y `origen = 'ia'`.

Cada export incluye la pregunta literal, **quién la hizo** (alias, correo e identificador), cuántas veces la repitió cada persona, fechas, ámbitos (`centro-n`, `persona`, …) y plantilla para indicar qué `docs/manual/*.md` tocar. Sirve también para contestar al usuario mientras el manual no cubre el tema.

### Exportar desde admin

1. Entrar como admin de plataforma.
2. **Administración → Manual / ayuda** (`/admin/ayuda`).
3. **Descargar Markdown** (para trabajar en Cursor) o **JSON** (para otras herramientas).

### Exportar en local (sin navegador)

```bash
php bin/console.php ayuda:pendientes-export --formato=md > docs/dev/ayuda-pendientes-export.md
php bin/console.php ayuda:pendientes-export --formato=json > /tmp/ayuda-pendientes.json
```

### Procesar el Markdown en Cursor

1. Abrir el `.md` exportado en el repo (o pegarlo en el chat).
2. Para cada pregunta, editar o crear ficheros en `docs/manual/` con lenguaje de usuario final.
3. Ejecutar de nuevo `ManualCubrePantallasTest` si añadiste rutas.
4. Desplegar; al cambiar el manual, `DocumentacionEnDisco::version()` cambia y las huellas antiguas dejan de reutilizarse.

Las entradas históricas en la base siguen siendo útiles como registro; nuevas preguntas iguales volverán a pasar por el modelo hasta que el manual las cubra.

## 3. Documentación relacionada

- Arquitectura de la ayuda: `docs/dev/ayuda_ia.md`
- Corpus: `docs/manual/`
- Admin interno: `docs/manual/_administracion.md`
