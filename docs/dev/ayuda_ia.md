# Ventana de ayuda con IA

Pantalla `/ayuda` (y `/yo/ayuda` en el nivel 1): el usuario escribe una pregunta
y se le contesta **usando solo el manual de `docs/manual/`**. No es un chat de
propósito general y no ve datos contables: al proveedor solo viajan el manual,
que es público dentro del programa, y la pregunta.

## Por qué no hay RAG

El manual completo son unos pocos miles de palabras (`wc -w docs/manual/*.md`),
del orden de 15.000 tokens. Cabe entero en el contexto de cualquier modelo
actual, así que va íntegro en cada consulta. Ni embeddings, ni base vectorial,
ni troceado. Si algún día el manual creciera hasta no caber, el sitio donde
recortar ya existe: `BuscadorDocumentacion` puntúa documentos contra la
pregunta y bastaría con mandar los mejores.

## El corpus

Un fichero por pantalla en `docs/manual/`, y **el nombre del fichero es la
clave que la IA debe citar**. `DocumentacionEnDisco` lee `docs/manual/*.md`
(ignora los que empiezan por `_`) y saca el título del primer `#`.

Reglas del corpus, que son las que hacen que la ayuda funcione:

- Lenguaje de usuario final. `docs/dev/` **no** entra: habla de PDO, asientos y
  migraciones, y el usuario no pregunta en esos términos.
- Cada fichero autocontenido, con el mismo esqueleto: para qué sirve, cómo se
  usa, reglas que conviene saber, problemas frecuentes.
- Los mensajes de error que el usuario puede ver deberían estar en «problemas
  frecuentes» del fichero de su pantalla. Es la pregunta más habitual.

`version()` es el hash del contenido de todos los documentos. Al tocar el
manual cambia, y con él caducan las respuestas guardadas.

## Cómo se acota la invención

La instrucción del sistema (`ConstructorPromptAyuda`) exige dos cosas: que si
la respuesta no está en el manual conteste `SIN_RESPUESTA`, y que termine con
una línea `FUENTES: clave1, clave2`.

`InterpreteRespuestaIA` hace de candado: separa esa línea, valida las claves
contra las que existen de verdad y **descarta la respuesta si no queda ninguna
válida**. Una respuesta inventada no suele poder citar un documento existente,
así que acaba en «eso no está en el manual». No es una garantía absoluta —
ningún prompt lo es— pero nada llega al usuario sin respaldo declarado.

## Proveedor

`ClienteChatCompatibleOpenAI` habla el formato *chat completions* de OpenAI,
que es el que hablan Gemini, Groq, OpenRouter, Mistral, DeepSeek, Cloudflare
Workers AI y Ollama.

**Por defecto, en local: Gemini 3.5 Flash-Lite, nivel gratuito.** URL y modelo
van en el código; en el `.env` solo hace falta `AYUDA_IA_CLAVE` (se saca en
https://aistudio.google.com/apikey). Sin clave, la pantalla sigue y responde
con los apartados del manual. El `Kernel` carga el `.env` del proyecto: en
Docker no hay que repetirlo en `docker-compose.yml`.

Otro proveedor se elige con `AYUDA_IA_URL` y `AYUDA_IA_MODELO`. Groq gratuito
no sirve: su límite de tokens por minuto no admite el manual entero de una vez.

Google puede entrenar con el tráfico del nivel gratuito. Cuando el servidor
esté en internet hay que pasar a una clave de **pago** (unos 0,10 $ por millón
de tokens de entrada; 1,50–2,60 $ cada mil preguntas distintas; las repetidas
salen de la caché). El cupo diario del gratuito no aguanta un servidor público.

## Límites, reutilización y registro

Todo en `ayuda_consultas` (migración `0029_ayuda_ia.sql`):

- **Reutilización:** la huella es la pregunta normalizada más la versión del
  manual. Misma pregunta y mismo manual, respuesta guardada y coste cero.
- **Límite:** `AYUDA_IA_LIMITE_DIARIO` consultas al modelo por usuario y día.
  Imprescindible si el servidor está en internet: sin él, cualquiera con cuenta
  puede agotar la cuota o la factura.
- **Registro:** las filas con `resuelta = false` son la lista de tareas del
  manual. Son preguntas reales que el manual no sabe contestar:

  ```sql
  SELECT pregunta, COUNT(*) FROM ayuda_consultas
   WHERE NOT resuelta GROUP BY pregunta ORDER BY 2 DESC;
  ```

Solo se guardan las consultas que han llegado al modelo. Las respuestas
reutilizadas no cuentan para el límite.

## Degradación

Si no hay clave configurada, o el proveedor falla, o tarda más de
`AYUDA_IA_TIMEOUT`, `ResponderPreguntaAyuda` no revienta: devuelve los
apartados del manual que mejor encajan con la pregunta, marcados con origen
`busqueda`, y la pantalla lo dice. La ayuda nunca queda inservible por un
problema de red o de cuota.

## Añadir un documento al manual

1. Crear `docs/manual/<clave>.md` con `# Título` en la primera línea.
2. Respetar el esqueleto de las demás y el lenguaje de usuario.
3. Nada más: el corpus se lee del disco y la versión se recalcula sola.

## Docs relacionadas

- Manual de usuario (el corpus): `docs/manual/`
- Pantallas y JS: `frontend/AGENTS.md`
- Acceso y ámbitos de ruta: `docs/dev/acceso.md`
