# Mantener el manual y las preguntas pendientes

## 1. Comprobar que todas las pantallas tienen ficha

Cada ruta GET de pantalla debe aparecer en una línea `- Ruta:` de `docs/manual/*.md`.

```bash
php vendor/bin/phpunit tests/unit/ManualCubrePantallasTest.php
php vendor/bin/phpunit tests/unit/ManualPorAmbitoTest.php
```

Al añadir una pantalla (`frontend/shared/config/routes.php`), crear o ampliar el `.md` correspondiente (ver `frontend/AGENTS.md`).

Tras cambios grandes de producto, repasar manualmente las fichas de las pantallas tocadas: la ayuda **no** lee `docs/dev/`.

## 2. Preguntas por repasar

Hay dos formas de que una pregunta entre en la lista (`ayuda_consultas`, `origen = 'ia'`, `repaso = 'nueva'`):

- **Sin respuesta:** el modelo responde `SIN_RESPUESTA` o no cita fuentes válidas (`resuelta = false`).
- **No sirvió:** el modelo contestó, pero un usuario pulsó **No** en «¿Le ha servido?» (fila en `ayuda_valoraciones` con `util = false`, con comentario opcional). Esa respuesta deja además de reutilizarse: la siguiente persona vuelve a consultar al modelo.

Cada export incluye la pregunta literal, el motivo, **quién la hizo** (alias, correo e identificador), cuántas veces la repitió cada persona, fechas, ámbitos (`centro-n`, `persona`, …) y, en las de «no sirvió», la respuesta que se dio y lo que cada usuario esperaba encontrar. Sirve también para contestar al usuario mientras el manual no cubre el tema.

### Ciclo completo

1. **Descargar de producción:** entrar como admin de plataforma, **Administración → Manual / ayuda** (`/admin/ayuda`), **Descargar Markdown** (o **JSON**).
2. **Dejarlo en local** en `var/ayuda/` (p. ej. `var/ayuda/ayuda-pendientes-20261115-101500.md`). La carpeta está en `.gitignore`: el fichero lleva correos de usuarios y **no debe subirse al repositorio**.
3. **Repasar:** para cada pregunta, editar o crear ficheros en `docs/manual/` con lenguaje de usuario final. Si hay rutas nuevas, `ManualCubrePantallasTest`.
4. **Desplegar.** Al cambiar el manual, `DocumentacionEnDisco::version()` cambia y las respuestas guardadas dejan de reutilizarse.
5. **Cerrar en producción:** en `/admin/ayuda`, **Cubierta** (el manual ya lo explica) o **Descartada** (no procede) en cada fila, o **Marcar todas como cubiertas**. Las cerradas no vuelven a exportarse.

Una pregunta cerrada reaparece sola si alguien vuelve a hacerla y sigue sin respuesta, o si recibe otro «No»: es la señal de que el manual todavía no la cubre.

### Sin navegador (en la máquina donde está la base)

```bash
php bin/console.php ayuda:pendientes-export --formato=md > var/ayuda/pendientes.md
php bin/console.php ayuda:pendientes-export --formato=json > var/ayuda/pendientes.json
php bin/console.php ayuda:pendientes-cerrar --estado=cubierta   # o descartada; cierra todas las pendientes
```

## 3. Documentación relacionada

- Arquitectura de la ayuda: `docs/dev/ayuda_ia.md`
- Corpus: `docs/manual/`
- Admin interno: `docs/manual/_administracion.md`
