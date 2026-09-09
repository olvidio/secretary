# Plan de ampliaciones — Secretario

Documento de trabajo para agentes. Escrito el 2026-09-08 tras auditar el estado del
repositorio (`src/`, `frontend/`, `schema/`, `bin/console.php`) y el libro origen
`moviments2026.xlsm`.

**Objetivo:** dejar decidido *ahora* lo que es barato ahora y caro después, de modo que
las cuatro ampliaciones previstas (nivel personal, más bancos, multicentro,
autenticación de dos niveles) no obliguen a rehacer el núcleo.

> Encomendamos el trabajo a S. José, patrono de las cuentas de la Casa de Nazaret.

---

## 1. Estado actual (auditoría)

Aplicación PHP 8.2 con capas DDD, ~4.800 líneas entre `src/` y `frontend/`. Funciona y
reproduce el Excel *Programa Secretario v8*. Stack propio en
`/home/dani/docker_images/secretary` (nginx 8005 + PHP-FPM + Postgres 15 en 5455 + Adminer 8085).

### Lo que está bien y debe conservarse

| Pieza | Por qué se conserva |
| --- | --- |
| `src/shared/domain/value_objects/Dinero.php` | Aritmética con `bcmath`, sin float. Correcto. |
| `src/shared/domain/value_objects/PeriodoEjercicio.php` | Año/Curso ya abstraído. |
| `src/shared/infrastructure/excel/ExcelDate.php`, `importacion/infrastructure/excel/XlsxReader.php` | Lectura de `.xlsm` sin dependencias pesadas. |
| `Kernel`, `Request`, `Response`, `ContestarJson`, FastRoute + PHP-DI | Entrada HTTP limpia. |
| `informes/domain/services/Calculadora613.php` (`estructuraP/G`) y `CalculadoraE37.php` (mapas de agrupación) | Las **definiciones de líneas** de los informes son datos valiosos; sobreviven aunque cambie de dónde salen los importes. |
| `conceptos/domain/services/CatalogoConceptos.php` | El plan de cuentas v8 completo, con descripciones. Se convierte en semilla del nuevo plan. |
| `cierre/domain/services/RepartoCierre.php` | Regla de reparto, ya sin I/O. |
| `frontend/**` | Vistas; cambios cosméticos. |

### Lo que bloquea las ampliaciones

1. **Un solo centro y un solo ejercicio, por construcción.**
   `schema/postgres.sql:2` — `configuracion (id INTEGER PRIMARY KEY CHECK (id = 1))`.
   No existe `centro_id` ni `ejercicio_id` en ninguna tabla. Multicentro es imposible
   sin tocar todas las tablas.

2. **No hay sistema de migraciones.**
   `SchemaInstaller::install()` hace `exec()` de un `.sql` con `CREATE TABLE IF NOT EXISTS`.
   Una columna nueva sobre una base con datos no se aplica nunca. Es el bloqueo más
   barato de resolver y condiciona todo lo demás.

3. **El modelo es una lista de movimientos, no una contabilidad.**
   `apuntes(fecha, cuenta P|G, origen A|B|C, iniciales, concepto_codigo, cantidad)`.
   Consecuencias medibles:
   - El **signo** no está en el dato: se deduce de listas de códigos escritas a mano en
     `src/apuntes/domain/services/SignoTesoreria.php:11-23` (cuatro arrays con ~35
     códigos cada uno, duplicados entre caja y banco). Cada concepto nuevo obliga a
     tocar cuatro listas y no hay nada que avise si se olvida una.
   - Los **traspasos** 41/42 se resuelven duplicando la fila y enlazándola con `par_id`
     (`src/apuntes/application/CrearApunte.php:82-104`): dos filas que representan un
     único hecho, con tres `UPDATE` de reconciliación y sin garantía transaccional de
     que el espejo exista. Es el problema que el usuario ya identificó.
   - **`origen` es un enum de tres letras.** Añadir un segundo banco exige cambiar el
     enum y las cuatro listas de `SignoTesoreria`, más `CalcularSaldos`, más el
     importador, más la UI. No hay concepto de "cuenta de tesorería".
   - Los saldos se calculan recorriendo *todos* los apuntes en PHP
     (`src/informes/application/CalcularSaldos.php:29-64`), con doble bucle anidado
     personas × apuntes. No escala y no es consultable desde SQL.

4. **La importación es destructiva, no idempotente.**
   `ImportarExcelSecretario::ejecutar()` llama a `borrarTodos()` de personas, apuntes y
   presupuesto (`src/importacion/application/ImportarExcelSecretario.php:41-46`).
   Reimportar borra también lo introducido a mano. No hay clave natural por fila de
   origen ni registro de ejecuciones.

5. **Autenticación mínima de un solo nivel.**
   `usuarios(usuario, password_hash)` + `$_SESSION['uid']`
   (`src/shared/infrastructure/http/AuthController.php`). Sin email, sin segundo factor,
   sin roles, sin vínculo con `personas`, sin límite de intentos. La lista de rutas
   públicas está incrustada en `Kernel::authorize()`.

6. **No existe nada del nivel personal.** Ni tabla, ni módulo, ni concepto de envío.

7. **El dinero se guarda como `TEXT`.** Correcto para no perder precisión, pero impide
   `SUM()` en SQL: todo agregado se hace en PHP. Con partida doble esto deja de ser
   viable.

### Diferencia relevante entre el Excel y una contabilidad

El Excel no es partida doble, pero **admite una traducción exacta**, y ese es el hallazgo
que hace factible el cambio:

| Excel | Partida doble |
| --- | --- |
| `origen = C` | contrapartida = cuenta **Caja** del libro |
| `origen = B` | contrapartida = cuenta **Banco** del libro |
| `origen = A` ("no afecta a caja ni banco") | contrapartida = **cuenta corriente de la persona** (libro P) o **deudores por vivienda** (libro G) |
| concepto `32` "Disponible a 1 de enero" | asiento de **apertura** contra Patrimonio |
| conceptos `41`/`42` + fila espejo | **un** asiento: Debe Caja / Haber Banco |
| concepto `9` "saldo c/c personales" | ya *es* el saldo de la cuenta de la persona: deja de ser un apunte y pasa a ser un saldo calculado |

Es decir: el `origen A` del Excel es precisamente la contrapartida que falta, y el
concepto 9 es el saldo de esa contrapartida. La contabilidad tradicional no se
*superpone* al modelo actual: lo *explica*.

---

## 2. Decisiones de arquitectura

Cada decisión lleva la alternativa descartada, porque un agente posterior debe poder
discrepar con conocimiento de causa.

### D1 — Libro diario de partida doble (`asientos` + `movimientos`)

Se sustituye `apuntes` por un asiento con N líneas de debe/haber sobre cuentas.

- El signo deja de ser conocimiento incrustado: está en la línea.
- `SignoTesoreria` **se elimina**.
- El traspaso es un asiento con dos líneas. Desaparece `par_id`.
- Los saldos son `SUM(debe) - SUM(haber)` por cuenta, en SQL.
- Añadir un banco es insertar una fila en `cuentas`. Cero código.

**Invariante:** todo asiento cuadra (`SUM(debe) = SUM(haber)`) **dentro de su libro**.
P y G siguen siendo dos contabilidades separadas; un asiento pertenece a un libro y no
cruza. El cierre de mes genera **dos** asientos (uno en P, uno en G), igual que hoy
genera dos apuntes.

**Confirmado por el usuario (2026-09-08): cuadre estricto.** Un asiento descuadrado no se
guarda nunca, y *no* se ofrece cuenta de «diferencias por regularizar»: si no cuadra, es
que falta un dato. A cambio, la pantalla de entrada mantiene el flujo rápido del Excel y
construye el asiento por detrás; el secretario no ve debe/haber salvo que lo pida.

*Alternativa descartada:* mantener la lista plana y añadir una columna `signo`. Arregla
el signo pero no los traspasos, ni los bancos múltiples, ni la consolidación desde el
nivel 1. Coste similar, beneficio mucho menor.

### D2 — Plan de cuentas jerárquico con `codigo_maestro`

Una sola tabla `cuentas` en árbol. Cada cuenta declara a qué **código del plan principal**
consolida:

```
21  Vivienda                 codigo_maestro = '21'   (cuenta del plan maestro)
 └ 21.gas  Gas               codigo_maestro = '21'   (etiqueta personal de nivel 1)
BANCO        Bancos                        codigo_maestro = 'BANCO'
 ├ BANCO.1/P  La Caixa · parte de P         codigo_maestro = 'BANCO'   (ver D10)
 ├ BANCO.1/G  La Caixa · parte de G         codigo_maestro = 'BANCO'
 └ BANCO.2/G  Banc Sabadell · parte de G    codigo_maestro = 'BANCO'
```

`codigo_maestro` es **la pieza que resuelve el requisito del usuario**: "sus etiquetas o
cuentas secundarias, pero dentro del sistema principal, para que al mandar las cuentas al
nivel superior no haya conflictos". La persona abre las subcuentas que quiera; al
consolidar se agrupa por `codigo_maestro` y el resultado siempre encaja en el plan del
centro. La misma mecánica sirve para los bancos múltiples y para futuros desgloses del
centro. Solo las cuentas hoja (`imputable = true`) admiten movimientos.

*Alternativa descartada:* una tabla `etiquetas` aparte, libre. Genera exactamente el
conflicto que se quiere evitar y obliga a una tabla de mapeo etiqueta→concepto que
alguien tiene que mantener.

### D3 — Ámbito `(centro_id, ejercicio_id)` desde el principio

`centros` y `ejercicios` como tablas propias; todo lo transaccional cuelga de
`ejercicio_id`; `personas` y las cuentas del centro cuelgan de `centro_id`.

Se introduce **en la misma fase que la partida doble**, porque ya se van a reescribir las
tablas. Hacerlo en dos pasadas cuesta el doble.

Repositorios con ámbito inyectado (`ContextoActual { centroId, ejercicioId, personaId? }`)
para que ninguna consulta pueda olvidar el filtro.

*Alternativa descartada:* una base de datos por centro. Simplifica el aislamiento pero
imposibilita la consolidación y multiplica el mantenimiento del Docker.

### D4 — Importes en céntimos (`BIGINT`)

`Dinero` gana `fromCents()` / `toCents()`; la BD guarda enteros. Exacto en Postgres y en
SQLite, sumable en SQL, sin cambiar la API del dominio. Sin esto, la partida doble sigue
obligando a agregar en PHP.

*Alternativa descartada:* `NUMERIC(14,2)`. Correcto, pero obliga a vigilar el casting de
PDO (devuelve string, y cualquier paso por float rompe la exactitud) y a decidir el
redondeo en cada agregado. Los céntimos como entero no admiten ese error.

### D5 — Los dos niveles son el mismo despliegue, distinto ámbito

Nivel 1 (persona) y nivel 2 (centro) comparten código, base de datos y plan de cuentas.
Lo que cambia es el ámbito de la sesión y las cuentas visibles. La persona tiene su
propio libro (`libro = 'X'`, personal) con las cuentas del plan maestro más sus
subcuentas.

Ventajas: un solo motor contable, un solo juego de informes, la consolidación es una
operación interna y no una API entre sistemas.

*Alternativa descartada:* una app independiente para el nivel 1 que envíe por API.
Duplica el núcleo contable y las reglas de validación.

### D6 — El envío mensual es una **remesa** versionada e idempotente

La persona cierra un mes y envía. Se materializa una `remesa` con líneas agregadas por
`codigo_maestro`. El centro la acepta y eso genera asientos en su libro P, marcados con
`remesa_id`.

Reglas:
- Reenviar el mismo mes crea `version + 1` y marca la anterior `sustituida`.
- Aceptar una remesa **borra en transacción** los asientos de la versión anterior y crea
  los nuevos. Nunca duplica.
- Un asiento con `remesa_id` no es editable a mano en el centro: se corrige reenviando.
- **Grano confirmado por el usuario (2026-09-08): total por concepto, con detalle bajo
  petición.** La remesa lleva una línea por `codigo_maestro` y mes. El desglose por
  subcuentas y los movimientos que lo componen quedan en el nivel 1; el centro puede
  **solicitar** el detalle de una línea concreta y la persona lo autoriza o lo deniega.
  Toda solicitud y su resolución quedan registradas (`remesa_solicitudes_detalle`).
- El detalle autorizado se muestra en modo lectura y **nunca** se convierte en cuentas ni
  asientos del centro: no hay forma de que las etiquetas de la persona contaminen su plan.

### D7 — Autenticación por identidad, con 2FA obligatorio en nivel 2

Tabla `identidades` única (email + hash), con vínculo a `persona_id` (nivel 1) o a uno o
varios `centro_id` con rol (nivel 2). TOTP obligatorio y confirmado para entrar como
centro; opcional para persona. Códigos de recuperación. Límite de intentos y bloqueo
temporal. La autorización sale de una tabla de rutas/permisos, no de la lista incrustada
en `Kernel::authorize()`.

*Alternativa descartada:* segundo factor por email. Menos operativo en un centro con
correo compartido, y ya se pide "doble factor" explícitamente.

### D8 — Importación idempotente por fila de origen

`import_ejecuciones` (fichero, sha256, resumen) e `import_filas`
(`ejercicio_id, hoja, fila` → `hash_contenido`, `asiento_id`).

Algoritmo por fila: hash nuevo → crear; hash igual → saltar; hash distinto → actualizar
el asiento existente. Al terminar, las filas registradas que no aparecieron en esta
ejecución se anulan **solo si su asiento tiene `origen = 'import'`**. Lo manual, lo de
cierre y lo de remesas nunca se toca. Desaparece el `borrarTodos()`.

### D9 — Migraciones versionadas

`migraciones/NNNN_descripcion.sql` + tabla `schema_migrations` + `php bin/console.php db:migrate`.
`schema/postgres.sql` pasa a ser la migración `0001`. Es requisito de todo lo demás.

**PostgreSQL como único motor (decisión del usuario, 2026-09-08).** Se retira el soporte
de SQLite: el PHP del host no tiene `pdo_sqlite`, Docker va con Postgres y el golden
master también. Mantener dos dialectos duplicaría el coste de las 15+ migraciones que
quedan, y varias restricciones que vienen (cuadre de asientos, índices parciales,
constraints diferidas) no tienen equivalente razonable en SQLite.

**Datos de desarrollo desechables por ahora (decisión del usuario, 2026-09-08).** Los 423
apuntes de la base `secretario` vienen del Excel y se pueden reimportar, así que hasta la
Fase 5 una migración puede recrear tablas si eso la simplifica. **El usuario avisará
cuando empiece a haber datos reales**; a partir de ese momento toda migración debe
preservar los datos y probarse contra una copia. Dejar constancia aquí de esa fecha.

### D10 — Tesorería: cuentas físicas compartidas entre libros

*Decisión del usuario (2026-09-08).* El manual del Excel recomienda una única caja física
y una única cuenta bancaria, aunque contablemente existan Caja P y Caja G. Se modela con
dos niveles:

- **`cuentas_fisicas`** — la caja o la cuenta bancaria del mundo real (nombre, IBAN,
  saldo conciliable con el extracto). Pertenece al centro, no a un libro.
- **Cuentas de mayor por (física × libro)** — al dar de alta una cuenta física se crean
  automáticamente sus cuentas de mayor: `BANCO.1/P` y `BANCO.1/G`. Un asiento del libro P
  solo puede usar `BANCO.1/P`.

Con esto:

- **D1 sigue intacto:** cada asiento cuadra dentro de su libro.
- El **saldo por libro** es el saldo de su cuenta de mayor; el **saldo físico** (el que
  hay que cuadrar contra el extracto o el arqueo) es la suma de los libros. La
  conciliación bancaria y el arqueo se hacen contra la cuenta física.
- Un préstamo entre libros sobre la misma cuenta física (G adelanta efectivo a P) se
  registra como **dos asientos enlazados** contra una cuenta de tipo `puente`, uno en cada
  libro. Es el único caso en que el dinero cruza de libro, y queda explícito y auditable.

*Alternativa descartada:* una sola cuenta de mayor compartida por P y G. Rompería el
cuadre por libro y haría imposible separar los informes 613 P y 613 G.

### D11 — Ejercicios de período libre y multianualidad

*Decisión del usuario (2026-09-08).* Se descarta el enum `Año` / `Curso` del Excel. Un
ejercicio se define por **`fecha_inicio` y `fecha_fin` arbitrarias**: enero-diciembre,
junio-mayo, septiembre-agosto o cualquier otro tramo. Un centro tiene N ejercicios
históricos consultables y solo los `abiertos` admiten asientos.

Consecuencias que hay que respetar al implementar:

- `PeriodoEjercicio` se reescribe: expone `mesesTotales()` (del ejercicio completo) y
  `mesesTranscurridos()` (hasta la fecha de corte).
- El prorrateo del 613 pasa de `previsto × meses / 12` a
  **`previsto × mesesTranscurridos / mesesTotales`**. Para un ejercicio enero-diciembre da
  exactamente lo mismo que hoy, así que el golden master no debe moverse.
- Los ejercicios de un mismo centro **no pueden solaparse** y no pueden dejar huecos si se
  quiere apertura automática (D12). Validar al crear.
- La etiqueta del ejercicio (`"2026"`, `"2026-27"`) es un campo de presentación, no la
  clave. Nada de deducir el año desde una fecha.

### D12 — Apertura automática de ejercicio

*Decisión del usuario (2026-09-08).* Al abrir un ejercicio, la aplicación genera el
asiento de apertura a partir de los saldos de cierre del ejercicio anterior:

- Se arrastran los saldos de **tesorería** (por cuenta física y libro), de las **cuentas
  personales** y de **patrimonio**. Las cuentas de ingreso y gasto **no** se arrastran:
  empiezan a cero.
- La contrapartida es una cuenta de patrimonio (`Resultado de ejercicios anteriores`).
- El concepto `32` «Disponible a 1 de enero» deja de teclearse: pasa a ser el
  `codigo_maestro` bajo el que la apertura aparece en el 613, y su importe es calculado.
- La apertura es **regenerable e idempotente** mientras el ejercicio esté abierto: se
  borran los asientos `tipo = 'apertura'` y se recrean en una transacción. Si el ejercicio
  anterior se corrige, basta con regenerar.
- La apertura debe arrastrar también `PUENTE.LIBROS` y `PUENTE.PERIODIFICACION` (D10, D13).
- El primer ejercicio de un centro (o uno importado del Excel) no tiene anterior: ahí la
  apertura se introduce a mano, como hoy con el concepto 32.
- Tras generar la apertura, se **mueven** al ejercicio nuevo los asientos `tipo=periodificacion`
  del anterior cuya fecha cae en el período nuevo (D13: tesorería aparcada después de `fecha_fin`).

*Nota para quien lo implemente:* la apertura debe cuadrar por libro igual que cualquier
otro asiento (D1). Si no cuadra, el ejercicio anterior estaba descuadrado y hay que
pararse, no compensar.

### D13 — Fecha de operación y fecha de imputación (periodificación)

*Decisión del usuario (2026-09-09).* Un asiento tiene **una** fecha. Cuando la operación
se ejecuta un día y hay que contarla en otro (p. ej. sale el dinero el 8/01 y el gasto
va al 31/12) no se recicla «fecha de registro» ni «fecha de devengo»: son **fecha de
operación** y **fecha de imputación**.

- `asientos.fecha` es la **fecha de imputación** (diario, 613, ejercicio del concepto).
- `asientos.fecha_operacion` es cuándo se ejecutó. Si coincide con la imputación, un
  solo asiento de dos movimientos, como hasta ahora.
- Si no coinciden y hay tesorería (origen B/C): dos asientos enlazados contra
  `PUENTE.PERIODIFICACION` (concepto en la imputación, caja/banco en la operación).
  El secretario sigue viendo **un** formulario; no piensa en debe/haber.
- Origen A, traspasos 41/42 y cierre de mes no admiten imputación distinta.
- No hay tercer campo: `created_at` ya es la entrada en el sistema.
- **Excepción a «solo el ejercicio abierto admite asientos»:** la pata de imputación
  puede escribirse en el ejercicio anterior cerrado si la operación vive en el abierto;
  entonces se regenera la apertura. Si el siguiente ejercicio aún no existe, la pata de
  tesorería se aparca en el abierto con fecha posterior a `fecha_fin` y D12 la mueve al
  crear el siguiente.

Detalle: `docs/dev/periodificacion.md`.

---

## 3. Modelo de datos objetivo

DDL orientativo (Postgres). El agente que lo implemente puede ajustar nombres, no la
estructura.

```sql
-- Ámbito -------------------------------------------------------------------
centros(id, codigo UNIQUE, nombre, tipo_cierre, activo, created_at)
ejercicios(id, centro_id FK, etiqueta,          -- '2026', '2026-27': presentación
           fecha_inicio, fecha_fin,             -- período libre (D11)
           estado,                              -- abierto|cerrado
           ejercicio_anterior_id FK NULL,       -- para la apertura automática (D12)
           UNIQUE(centro_id, fecha_inicio),
           CHECK (fecha_fin > fecha_inicio))
-- Regla de dominio: los ejercicios de un centro no se solapan.

-- Tesorería física (D10) ---------------------------------------------------
cuentas_fisicas(id, centro_id FK, tipo /* caja|banco */, nombre, iban NULL,
                activo, orden, UNIQUE(centro_id, nombre))

-- Plan de cuentas ----------------------------------------------------------
cuentas(id,
        centro_id FK NULL,       -- NULL = plan maestro compartido
        persona_id FK NULL,      -- NULL salvo subcuentas del nivel 1
        cuenta_fisica_id FK NULL,-- solo tesorería: la caja/banco real (D10)
        libro,                   -- 'P' | 'G' | 'X' (personal nivel 1)
        codigo, padre_id FK NULL, nombre, descripcion,
        tipo,                    -- ingreso|gasto|tesoreria|personal|patrimonio|puente
        naturaleza,              -- deudora|acreedora
        codigo_maestro,          -- código del plan principal al que consolida
        imputable BOOL, orden, activo,
        UNIQUE(centro_id, persona_id, libro, codigo))

-- Libro diario -------------------------------------------------------------
asientos(id, ejercicio_id FK, libro, numero, fecha, fecha_operacion,
         glosa,
         tipo,       -- normal|apertura|traspaso|cierre|remesa|periodificacion
         origen,     -- manual|import|cierre|remesa
         persona_id FK NULL, remesa_id FK NULL,
         creado_por FK NULL, created_at, updated_at, anulado_at NULL,
         UNIQUE(ejercicio_id, libro, numero))
-- fecha = imputación (D13); fecha_operacion = ejecución. Si difieren y hay tesorería,
-- dos asientos enlazados (normal + periodificacion) contra PUENTE.PERIODIFICACION.

movimientos(id, asiento_id FK ON DELETE CASCADE, orden,
            cuenta_id FK, persona_id FK NULL,
            debe BIGINT NOT NULL DEFAULT 0,    -- céntimos
            haber BIGINT NOT NULL DEFAULT 0,
            CHECK (debe >= 0 AND haber >= 0 AND (debe = 0) <> (haber = 0)))
-- Invariante de dominio: SUM(debe) = SUM(haber) por asiento.

-- Presupuesto y arqueo (reescritos sobre cuenta_id) -------------------------
presupuesto_lineas(id, ejercicio_id FK, cuenta_id FK, previsto BIGINT,
                   UNIQUE(ejercicio_id, cuenta_id))
arqueos(id, ejercicio_id FK, cuenta_fisica_id FK, fecha, desglose_json,
        total BIGINT, UNIQUE(ejercicio_id, cuenta_fisica_id, fecha))
-- El arqueo se cuadra contra el saldo FÍSICO = suma de los saldos por libro (D10).

-- Identidad y acceso -------------------------------------------------------
identidades(id, email UNIQUE, password_hash, nombre, activo,
            intentos_fallidos, bloqueado_hasta, ultimo_acceso, created_at)
identidad_totp(identidad_id PK FK, secret_cifrado, confirmado_at)
identidad_recovery(id, identidad_id FK, code_hash, usado_at NULL)
identidad_centro(identidad_id FK, centro_id FK, rol, PRIMARY KEY(identidad_id, centro_id))
identidad_persona(identidad_id FK, persona_id FK, PRIMARY KEY(identidad_id, persona_id))

-- Nivel 1 → nivel 2 --------------------------------------------------------
remesas(id, persona_id FK, centro_id FK, ejercicio_id FK, anio, mes, version,
        estado,  -- borrador|enviada|aceptada|rechazada|sustituida
        hash_contenido, enviada_at, resuelta_at, nota,
        UNIQUE(persona_id, ejercicio_id, mes, version))
remesa_lineas(id, remesa_id FK, codigo_maestro, importe BIGINT, detalle_json)
remesa_solicitudes_detalle(id, remesa_linea_id FK, solicitada_por FK, solicitada_at,
                           estado /* pendiente|autorizada|denegada */,
                           resuelta_at NULL, motivo NULL)

-- Importación --------------------------------------------------------------
import_ejecuciones(id, ejercicio_id FK, fichero, sha256, iniciada, terminada, resumen_json)
import_filas(id, ejercicio_id FK, hoja, fila, hash_contenido,
             asiento_id FK NULL, ultima_ejecucion_id FK,
             UNIQUE(ejercicio_id, hoja, fila))
```

`personas` sobrevive con `centro_id` añadido. `conceptos` desaparece: sus datos alimentan
`cuentas` (el contenido de `CatalogoConceptos` es la semilla del plan maestro).

---

## 4. Fases

Cada fase es un encargo cerrado, con criterio de aceptación verificable. **No empezar una
fase sin la anterior en verde.** El tipo de agente y el modelo son sugerencia de coste.

### Fase 0 — Red de seguridad (golden master)
**Agente:** `general-purpose`, modelo `sonnet`. Media jornada.

Antes de tocar nada: test de integración que importe `moviments2026.xlsm` contra una BD
limpia y vuelque a `tests/golden/` el JSON de 613 P, 613 G, E 37, E 37 Resumen, saldos y
arqueo. Ese fichero es el contrato: **todas las fases posteriores deben mantenerlo
idéntico**, salvo las diferencias documentadas en la Fase 3.

*Aceptación:* `composer test` falla si cualquier informe cambia un céntimo.

### Fase 1 — Migraciones versionadas y Postgres único
**Agente:** `claude`, modelo `sonnet`. 1 jornada. Depende de: F0.

1. Runner de migraciones, tabla `schema_migrations`, comando `db:migrate`,
   `schema/postgres.sql` convertido en `0001_inicial.sql`. `db:install` pasa a ser
   `db:migrate` + semillas.
2. Retirada de SQLite (D9): fuera `schema/sqlite.sql` y la rama sqlite de
   `ConnectionFactory`, con error claro si `DB_DRIVER` no es `pgsql`.
3. **Arreglo de `tests/integration/SchemaInstallerTest.php`**, que hoy corre contra la
   base de desarrollo `secretario` porque no encuentra `pdo_sqlite`. Debe usar
   `secretario_test`, como el golden master.

*Aceptación:* migrar dos veces seguidas no produce cambios ni errores; el golden master
sigue en verde; ningún test toca la base de desarrollo.

### Fase 2 — Ámbito, ejercicios de período libre y plan de cuentas
**Agente:** `claude`, modelo `sonnet`. 2-3 jornadas. Depende de: F1.

1. Migraciones que crean `centros`, `ejercicios`, `cuentas_fisicas` y `cuentas`; backfill
   del centro y del ejercicio actuales desde `configuracion`. `personas` gana `centro_id`.
2. **Ejercicios de período libre (D11):** fuera el enum `Año`/`Curso`. Reescribir
   `PeriodoEjercicio` con `mesesTotales()` y `mesesTranscurridos()`, y cambiar el
   prorrateo del 613 a `previsto × mesesTranscurridos / mesesTotales`. Validar que los
   ejercicios de un centro no se solapan. UI de alta de ejercicio con fechas libres.
3. Semilla del plan maestro desde `CatalogoConceptos`; alta de las cuentas físicas
   actuales (una caja, un banco) con sus cuentas de mayor `/P` y `/G` (D10); una cuenta
   personal por persona.
4. `ContextoActual { centroId, ejercicioId, personaId? }` inyectado, y repositorios que
   filtran por ámbito para que ninguna consulta pueda olvidarlo.

**`apuntes` sigue intacto**: esta fase no cambia comportamiento contable.

*Aceptación:* golden master intacto (el cambio de prorrateo debe ser neutro para un
ejercicio enero-diciembre; si no lo es, hay un bug). Se puede crear un ejercicio
junio-mayo y un segundo centro de prueba sin interferir con el primero.

### Fase 3 — Partida doble ★ fase crítica
**Agente:** `claude`, modelo `opus`. 3-5 jornadas. Depende de: F2.

1. `asientos` + `movimientos` con importes en céntimos; entidades `Asiento`/`Movimiento`
   con la invariante de cuadre en el dominio.
2. Migración de datos `apuntes` → `asientos` con la tabla de traducción de la sección 1
   (A → cuenta personal / deudores; B → banco; C → caja; 32 → apertura; 41-42 → un solo
   asiento fusionando los pares `par_id`).
3. Reescritura de: `CrearApunte` → `RegistrarAsiento`, `CalcularSaldos` (a SQL),
   `CerrarMes`, `Calculadora613`, `CalculadoraE37`, `ArqueoController`,
   `PresupuestoRepository`.
4. **Borrado** de `SignoTesoreria` y de `par_id`.
5. **Cuadre estricto (D1):** el repositorio rechaza cualquier asiento descuadrado. No se
   crea cuenta de «diferencias por regularizar».
6. UI: el formulario de entrada mantiene el flujo rápido del Excel (fecha, libro,
   tesorería, iniciales, concepto, importe) y construye el asiento por detrás. **El
   secretario no debe tener que pensar en debe y haber.**

*Aceptación:* golden master intacto **salvo** estas diferencias, que hay que documentar
como cambios esperados y revisar una a una con el usuario:
- los traspasos 41/42 dejan de contarse dos veces en cualquier listado por concepto;
- el concepto 9 pasa de apunte a saldo calculado de la cuenta personal;
- `saldo_a` de `CalcularSaldos` debe coincidir con el saldo de las cuentas personales;
- **los saldos por persona cambian mucho, y es una corrección, no una regresión** (ver
  abajo).

#### Defecto conocido que la Fase 3 corrige sola: `por_persona` mezcla los libros

Detectado el 2026-09-08 al auditar los datos reales. `CalcularSaldos::ejecutar()` recorre
los apuntes con `origen = 'A'` filtrando por iniciales **pero no por libro**, así que resta
del saldo personal los apuntes de la contabilidad **General** que llevan esas iniciales.
Caso comprobado (Antoni Companys, `ac`):

| Libro | Concepto | Suma | Efecto hoy sobre su saldo |
| --- | --- | --- | --- |
| P | 111 sus ingresos | 7.500 | +7.500 |
| P | 21 su vivienda | 7.500 | −7.500 |
| G | 11 **ingreso del centro** | 7.500 | −7.500 ← incorrecto |
| G | 211 **gasto del centro** | 7.500 | −7.500 ← incorrecto |

Resultado: −15.000 € cuando su saldo personal real es 0. Lo mismo afecta a `pp` (−9.600),
`fb` (−8.000), `rd` (−4.000) y `jmg` (−6.172).

En partida doble el defecto **desaparece por construcción**: la cuenta corriente de la
persona vive en el libro P y ningún asiento de G puede tocarla. Por tanto:

- **Espera que `saldos.json` cambie mucho en la Fase 3.** Es la corrección, no un fallo.
- **No reintroduzcas el comportamiento antiguo** para «mantener el golden verde».
- Comprueba que los saldos nuevos salen de la suma de movimientos de cada cuenta
  personal, y contrasta un par de personas a mano con el Excel antes de aceptarlos.

Test añadido: ningún asiento descuadra; suma de saldos de tesorería = suma de
movimientos de tesorería.

### Fase 4 — Tesorería: cuentas físicas compartidas y multibanco
**Agente:** `claude`, modelo `sonnet`. 1-2 jornadas. Depende de: F3.

Implementación completa de D10. Alta/baja de cuentas físicas desde la UI, con creación
automática de sus cuentas de mayor por libro. Traspaso genérico entre dos cuentas de
tesorería del mismo libro (sustituye a 41/42, que quedan como códigos maestros a los que
consolidan). Traspaso **entre libros** sobre la misma cuenta física mediante cuenta
`puente`, con los dos asientos enlazados. Arqueo y conciliación contra la cuenta **física**
(saldo físico = suma de los saldos por libro).

*Aceptación:* con dos bancos y una caja compartida, saldos por libro e informes correctos
sin tocar código; el arqueo cuadra contra la suma de libros; un préstamo G→P deja los dos
asientos enlazados y ambos libros cuadrados.

### Fase 4b — Cierre de ejercicio y apertura automática
**Agente:** `claude`, modelo `sonnet`. 1-2 jornadas. Depende de: F4.

Implementación de D12. Cierre del ejercicio (`estado = cerrado`, sin más asientos) y
generación del asiento de apertura del siguiente a partir de los saldos de tesorería,
cuentas personales y patrimonio. Regenerable e idempotente mientras el ejercicio esté
abierto. El concepto `32` pasa a ser calculado.

*Aceptación:* cerrar 2026 y abrir 2027 arrastra caja, bancos y c/c personales al céntimo;
la apertura cuadra por libro; regenerarla dos veces no duplica nada; corregir el ejercicio
anterior y regenerar actualiza la apertura.

### Fase 4c — Fecha de operación e imputación (periodificación)
**Agente:** `claude`, modelo `sonnet`. 1 jornada. Depende de: F4b.

Implementación de D13. `fecha_operacion` en `asientos`; cuenta `PUENTE.PERIODIFICACION`;
entrada con fecha de imputación opcional; dos asientos enlazados solo si las fechas
difieren y hay tesorería; arrastre del puente en la apertura; traslado de patas
`periodificacion` posteriores a `fecha_fin` al crear el ejercicio siguiente.

*Aceptación:* misma fecha → un asiento; fechas distintas → par enlazado, 613 en la
imputación y caja en la operación; borrar uno borra el par; origen A con dos fechas se
rechaza; aparcar un 8/01 en un 31/12 y abrir el año siguiente deja el puente en la
apertura y la tesorería en el año nuevo.

### Fase 5 — Importación idempotente
**Agente:** `claude`, modelo `sonnet`. 1-2 jornadas. Depende de: F3 + F4c.

`import_ejecuciones` / `import_filas`, algoritmo de D8, `--dry-run` con informe de
altas/cambios/bajas, importación a un `(centro, ejercicio)` indicado por parámetro.
Eliminar `borrarTodos()`. El Excel tiene **una** fecha: `fecha_operacion = fecha`;
nunca partir al importar. Si más adelante se añade columna de imputación en el Excel,
respetar D13.

*Aceptación:* importar el mismo fichero tres veces deja exactamente los mismos asientos;
un asiento manual intercalado sobrevive; modificar una fila del Excel y reimportar
actualiza solo ese asiento.

### Fase 6 — Autenticación de dos niveles
**Agente:** `claude`, modelo `sonnet`, seguido de una pasada de `/security-review`.
2-3 jornadas. Depende de: F2 (no necesita F3).

Tablas de D7. Login por email; TOTP (`RFC 6238`, sin dependencias pesadas o con una
librería auditada) obligatorio para identidades de centro; códigos de recuperación;
límite de intentos con bloqueo temporal; cookies de sesión `HttpOnly`+`SameSite=Lax`,
regeneración de id al entrar; CSRF en formularios. Autorización por tabla, retirando la
lista incrustada de `Kernel::authorize()`. Migración del usuario `scl` actual a una
identidad de centro. Selector de centro cuando la identidad tiene varios.

*Aceptación:* una identidad de centro sin TOTP confirmado no puede operar; una identidad
de persona no puede leer datos del centro; test de que toda ruta no declarada pública
exige sesión.

### Fase 7 — Nivel 1: contabilidad personal
**Agente:** `claude`, modelo `opus` para el diseño del módulo, `sonnet` para la UI.
3-4 jornadas. Depende de: F3 + F6.

Libro `X` por persona sobre el mismo motor de asientos. Cuentas: plan maestro (códigos
111-113, 12, 21-28, 4, 51-52, 6, 71-79) más las subcuentas propias, siempre con
`codigo_maestro` válido. Entrada simplificada (fecha, fecha de imputación opcional según D13, cuenta, importe, nota), su caja y
su banco, saldo, resumen mensual. Nada de 613 ni E 37 en este nivel: es sencillo a
propósito.

*Aceptación:* una persona puede llevar un mes completo sin ver jamás una cuenta del
centro; no puede crear una subcuenta cuyo `codigo_maestro` no exista en el plan maestro.

### Fase 8 — Remesas: envío mensual nivel 1 → nivel 2
**Agente:** `claude`, modelo `opus`. 2-3 jornadas. Depende de: F7.

Tablas y reglas de D6. Pantallas: en nivel 1, "cerrar y enviar mes" con previsualización;
en nivel 2, bandeja de remesas pendientes con aceptar/rechazar y diferencia contra la
versión anterior.

*Aceptación:* enviar → aceptar → reenviar corregido → aceptar deja los importes correctos
y **cero** asientos duplicados; rechazar no deja rastro contable; los informes 613 P y
E 37 del centro incluyen lo aceptado.

### Fase 9 — Multicentro operativo
**Agente:** `claude`, modelo `sonnet`. 1-2 jornadas. Depende de: F5 + F6.

Alta de centros desde la UI, importación de su Excel, cambio de centro en sesión,
copia del plan maestro. Opcional: informe consolidado entre centros.

*Aceptación:* dos centros con datos reales conviven sin fuga de datos entre ámbitos
(test explícito de aislamiento).

### Orden y paralelismo

```
F0 ─ F1 ─ F2 ─┬─ F3 ─┬─ F4 ─ F4b ─ F4c ─ F5 ─┐
              │      └─ F7 ─ F8 ─────────────┤
              └─ F6 ─────────────────────────┴─ F9
```

F6 (autenticación) puede ir en paralelo a F3/F4/F5 con agentes distintos: no comparten
ficheros. F4b puede aplazarse hasta que haga falta cerrar el primer ejercicio; **F4c no:**
hay que tenerla antes de la Fase 5. La remesa (F8) agrega conceptos por `asientos.fecha`
(imputación); la tesorería del nivel 1 no viaja en la remesa.

---

## 5. Sobre la revisión del código actual

El usuario planteaba revisar lo ya hecho. Recomendación de coste: **no revisar a fondo lo
que la Fase 3 va a reescribir.** Se reescriben `src/apuntes/**`, `SignoTesoreria`,
`CalcularSaldos`, `CerrarMes` (parcialmente), `ImportarExcelSecretario`, `AuthController`
y `schema/`. Eso es cerca del 40 % del código.

Revisión que sí conviene hacer ahora, porque sobrevive intacta:

- `Dinero` — casos límite de redondeo en `mulRatio` y `divInt` (reparto de céntimos: hoy
  `divInt` puede perder el resto del reparto entre residentes).
- `PeriodoEjercicio` — modo Curso y `mesesHasta()`.
- `ExcelDate` / `XlsxReader` — fechas 1900/1904, celdas compartidas, hojas ausentes.
- Las definiciones de `estructuraP/G` y los mapas de `CalculadoraE37` contra el Excel.
- `Kernel` — manejo de errores y cabeceras.

**Agente sugerido para esa revisión:** `/code-review` con `--effort medium` limitado a
esos ficheros, o `Explore` en `haiku` si solo se quiere el inventario.

---

## 6. Preguntas

### Resueltas por el usuario el 2026-09-08

| # | Pregunta | Respuesta | Recogida en |
| --- | --- | --- | --- |
| 3 | Grano del envío nivel 1 → nivel 2 | Total por concepto, **con detalle bajo petición** | D6 |
| 5 | Ejercicios históricos | **Sí**, multianual, y con período contable **definible libremente** (enero-diciembre, junio-mayo u otro) | D11 |
| 6 | Bancos por libro o compartidos | **Cuentas físicas compartidas** entre P y G, con desglose por libro | D10 |
| 8 | Cuadre estricto | **Sí**, sin cuenta de ajuste; la UI construye el asiento por detrás | D1 |
| — | Arrastre entre ejercicios | **Apertura automática** desde el cierre del anterior | D12 |

### Pendientes

Ninguna bloquea las Fases 0-6. Conviene resolverlas antes de la Fase 7.

1. **Alcance del nivel 1.** ¿La persona lleva *todos* sus movimientos ahí (y el centro
   solo recibe el agregado mensual), o solo lo que afecta al centro? Supuesto por defecto:
   lleva todo lo suyo; sube el agregado por concepto.
2. **Persona sin centro.** ¿Puede existir una identidad de nivel 1 no vinculada a ningún
   centro? Supuesto: sí, pero sin poder enviar remesas.
3. **Corrección tras aceptar.** ¿El centro puede editar lo recibido, o solo devolverlo
   para que la persona reenvíe? Supuesto: solo devolver — mantiene una única fuente de
   verdad.
4. **Segundo factor.** TOTP con app (Google Authenticator / Aegis) frente a código por
   correo. Supuesto: TOTP.
5. **Ejercicio y remesas.** Si el centro lleva ejercicio junio-mayo y la persona quiere el
   suyo enero-diciembre, ¿se permite? Supuesto: la remesa es mensual, así que basta con
   que el mes caiga dentro de un ejercicio abierto en cada lado; pero conviene confirmarlo
   antes de la Fase 8.

---

## 7. Cómo continuar

Encargo literal para el siguiente agente:

> Lee `docs/dev/plan_ampliaciones.md`. Implementa la **Fase N** completa, respetando las
> decisiones D1-D13 sin reabrirlas (si crees que una está mal, párate y dilo antes de
> escribir código). Cumple `AGENTS.md`: dominio sin I/O, application sin SQL ni HTML,
> infraestructura con PDO/HTTP/Excel. Al terminar, `composer test` y `composer phpstan` en
> verde, el golden master de la Fase 0 intacto (o las diferencias documentadas y
> justificadas), y actualiza la fase en este documento con lo que has hecho y lo que has
> dejado pendiente.

### Registro de fases

| Fase | Estado | Agente | Fecha | Notas |
| --- | --- | --- | --- | --- |
| 0 Golden master | hecha | claude/sonnet | 2026-09-08 | `tests/integration/GoldenMasterTest.php` importa moviments2026.xlsm contra `secretario_test` (aislada de `secretario`, schema recreado en cada run) y vuelca 13 ficheros en `tests/golden/`: importación, configuración, personas, conceptos P/G, presupuesto P/G, resumen 613 P/G, E37 detalle y resumen, saldos, arqueo, y un agregado de apuntes (total y sumas por cuenta+origen, cuenta+concepto, cuenta+iniciales) pensado para detectar regresiones en la Fase 3. No cubre la capa HTTP/controladores ni el guardado de arqueos (la importación no los crea, quedan `null`). Regenerar con `GOLDEN_UPDATE=1`; detalle en `docs/dev/golden_master.md`. |
| 1 Migraciones | hecha | claude/sonnet | 2026-09-08 | Runner en `src/shared/infrastructure/persistence/MigrationRunner.php` (tabla `schema_migrations`, checksum sha256 por fichero, transacción por migración, `pg_advisory_lock`). `schema/postgres.sql` pasa a ser `migraciones/0001_inicial.sql`; `schema/` desaparece. Baseline: base con `apuntes` y sin `schema_migrations` marca la 0001 como aplicada sin ejecutarla (comprobado contra `secretario`: 423 apuntes intactos tras `db:migrate` dos veces). `db:install` = `db:migrate` + semillas. SQLite retirado (D9): sin rama en `ConnectionFactory`, error explícito si `DB_DRIVER != pgsql`. `SchemaInstallerTest` corregido para usar `secretario_test` (dejó de ensuciar `secretario`); mecanismo de aislamiento factorizado en `Tests\Soporte\BaseDeDatosAislada`, compartido con `GoldenMasterTest` y el nuevo `MigrationRunnerTest`. Detalle en `docs/dev/migraciones.md`. No toca `apuntes` ni ningún informe: golden master intacto (verificado por hash). Pendiente para Fase 2: decidir si `centro_id`/`ejercicio_id` se añaden con `ALTER TABLE` + backfill sobre las tablas actuales o se reescriben; el runner soporta ambas. |
| 2 Ámbito, ejercicios libres y plan de cuentas | hecha | claude/sonnet | 2026-09-08 | Migración `migraciones/0002_ambito_esquema.sql` (solo DDL): `centros`, `ejercicios` (D11, con `fecha_fin` = fin real del ejercicio y `fecha_corte` = antiguo `configuracion.fecha_cierre`, campos distintos a propósito — ver `docs/dev/ambito.md` §1), `cuentas_fisicas` (D10), `cuentas` (D2, `codigo_maestro` + `UNIQUE(centro_id, persona_id, libro, codigo)`), `personas.centro_id` (nullable). Siembra idempotente en `src/ambito/infrastructure/persistence/AmbitoSeeder.php`, invocada desde `bin/console.php` (`db:migrate`) y `SchemaInstaller::install()` (`db:install`) — no puede vivir en la propia migración porque en una instalación nueva `configuracion` todavía no existe cuando se aplican migraciones (razón detallada en `docs/dev/ambito.md` §3, junto con la trampa PDO/Postgres de bindear booleanos como string vacío, ya corregida en los 4 repositorios afectados). `PeriodoEjercicio` reescrito con `mesesTotales()`/`mesesTranscurridos()` (D11); prorrateo del 613 pasa a `previsto × mesesTranscurridos / mesesTotales`, neutro para enero-diciembre. `ContextoActual {centroId, ejercicioId, personaId?}` en el contenedor DI, resuelto asumiendo un único centro activo (multicentro real es la Fase 9); ningún repositorio de `apuntes`/`personas`/`conceptos`/`presupuesto_lineas` filtra aún por ámbito (documentado, no es un olvido). UI mínima de alta de ejercicios de fechas libres en `/ejercicios`. 5 tests nuevos (20 en total, `composer test` y `composer phpstan` en verde): golden master intacto sin regenerar, cobertura del plan de cuentas contra los 423 apuntes reales (único huérfano conocido: `P/11`, apunte id=159 — ver `docs/dev/ambito.md` §5), ejercicio de período libre junio-mayo, no-solapamiento, aislamiento entre dos centros. Verificado además contra la base real `secretario` (`db:migrate`: 423 apuntes y 10 personas intactos, ejercicio 2026 con `fecha_fin=2026-12-31`/`fecha_corte=2026-06-30` correctos, 54 cuentas) y contra una instalación en limpio (`db:install`, 54 cuentas/49 conceptos). `apuntes`/`conceptos`/`presupuesto_lineas`/`arqueos` no se han tocado: fase aditiva. Detalle completo en `docs/dev/ambito.md`. Pendiente para Fase 3: decidir el huérfano `P/11`; inyectar `ContextoActual` en los repositorios legados al reescribirlos contra `asientos`. |
| 2b Reparación importador | hecha | claude/sonnet | 2026-09-08 | Bug real reproducido contra `secretario`: la Fase 2 añadió `cuentas.persona_id` como FK a `personas`, pero `ImportarExcelSecretario::ejecutar()` seguía llamando a `personas->borrarTodos()` en cada reimportación, lo que rompía con `SQLSTATE[23503]` en cuanto una persona tenía cuenta `CC.<INICIALES>` (todas, en una base ya sembrada). `GoldenMasterTest` y `PlanDeCuentasCoberturaTest` nunca lo detectaban porque ambos recrean el esquema en cada ejecución e importan sobre una base vacía sin cuentas previas — el hueco de test era tan importante como el bug. Arreglo: `importarPersonas()` hace upsert por `iniciales` (ya UNIQUE) en vez de borrar+recrear, y sincroniza `activo` (columna nueva, migración `0003_personas_activo.sql`) con `PersonaRepository::sincronizarActivos()`: quien sigue en el Excel se actualiza/reactiva, quien ha desaparecido se marca **inactivo, nunca se borra** (preserva el histórico de informes y no rompe la FK). Adelanta mínimamente parte de la Fase 5 (idempotencia de `personas` solamente; sin `import_ejecuciones`/`import_filas` ni idempotencia de apuntes, que sigue pendiente de la Fase 5 completa). `apuntes->borrarTodos()` y `presupuesto->borrarCuenta()` se mantienen sin cambios: comprobado en las migraciones que ninguna FK de la Fase 2 apunta a esas tablas, así que no hay más víctimas de esta misma clase de bug. `bin/console.php` (`import:excel`) vuelve a llamar a `AmbitoSeeder::sembrar($pdo)` después de importar, para que las personas nuevas obtengan su `centro_id` y su cuenta `CC.<INICIALES>` (antes solo se sembraba antes de importar, con configuración placeholder). Nuevo test `tests/integration/ImportacionIdempotenteTest.php`: importa dos veces seguidas sobre la MISMA base ya sembrada (sin recrear esquema entre pasadas) y comprueba que no falla, no duplica personas ni cuentas, las `CC.*` siguen apuntando a la persona correcta y el número de apuntes no cambia — el escenario real que faltaba cubrir. Aparte, se corrigió un dato de origen descubierto por `PlanDeCuentasCoberturaTest`: la celda E160 de "Talonarios" en `moviments2026.xlsm` tenía `11` en vez de `111` (nómina de Agustí Fontarnau, backup en `/home/dani/moviments2026.xlsm.bak-20260908`); `PlanDeCuentasCoberturaTest` pasa de tolerar ese huérfano conocido a exigir cero huérfanos siempre. Golden master regenerado (`GOLDEN_UPDATE=1`) y verificado a mano fichero por fichero contra la copia previa: solo cambiaron los 5 ficheros afectados por ese apunte (613 P, saldos, E37 detalle y resumen, agregado de apuntes), con el importe exacto +1806.64 esperado en cada uno (detalle completo, incluida la explicación del salto ×2 en `saldos.json`, en `docs/dev/golden_master.md`); los otros 9 quedaron idénticos. `composer test` (21 tests, 78 assertions, incluye el nuevo) y `composer phpstan` en verde. Verificado dos veces seguidas contra `secretario` real (423 apuntes, 10 personas activas, 64 cuentas, sin duplicados) y contra una instalación en limpio (`db:install` + `import:excel` × 2, mismos recuentos). Detalle completo en `docs/dev/ambito.md` §5 y §9 (huérfano resuelto) y `docs/dev/golden_master.md` (regeneración documentada). |
| 3 Partida doble | hecha | claude/opus | 2026-09-08 | OLA 1+2: `asientos`/`movimientos` (423 asientos cuadrados tras import), `TraductorApuntesAAsientos`, conversión automática al importar (`ImportarExcelSecretario` + `ResolverAmbitoActual` por `configuracion.centro`), informes 613/E37/saldos sobre movimientos SQL, alta/listado/borrado vía proyección Excel (`ProyectorAsientoAFilaExcel`), `SignoTesoreria` eliminado. Golden actualizado: `por_persona` corrige mezcla P/G (p. ej. `ac` −15000→0), línea 9 del 613 P = saldo CC calculado, `e37_resumen.saldo_cc` y `e37.json` ids de asientos; caja/banco intactos al céntimo. Tests: `PartidaDobleConversionTest`, `PartidaDobleInformesTest`, `ImportacionIdempotenteTest` (recuento asientos). |
| 4 Tesorería compartida y multibanco | hecha | claude/sonnet | 2026-09-08 | Migración `0005_tesoreria_fisica.sql` (`asiento_par_id`, arqueo por `cuenta_fisica_id`). Semilla `PUENTE.LIBROS` P/G. Alta/baja físicas (`CrearCuentaFisica`, `DesactivarCuentaFisica`), traspaso mismo libro, préstamo enlazado, arqueo saldo físico P+G, `GET /api/informes/tesoreria`, UI `/tesoreria` y `/traspasos`. `CalcularSaldos` y golden intactos. Test `TesoreriaFisicaTest` + unit `PrestamoEntreLibrosTest`. Detalle en `docs/dev/tesoreria.md`. |
| 4b Cierre de ejercicio y apertura | hecha | claude/sonnet | 2026-09-09 | `CerrarEjercicio`, `ReabrirEjercicio`, `GenerarApertura`, `SincronizarConfiguracionConEjercicio`. Cuenta P `RESULTADO.ANT` en `AmbitoSeeder`. Apertura arrastra tesorería/CC/DEUDORES.VIV/PUENTE.LIBROS; G/32 calculado. API `POST …/cerrar|reabrir|apertura`, UI `/ejercicios`. Bloqueo asientos en ejercicio cerrado. Test `CierreEjercicioAperturaTest` + unit `ConstructorAsientoAperturaTest`. Detalle en `docs/dev/cierre_ejercicio.md`. Golden intacto. |
| 4c Periodificación | hecha | cursor-grok | 2026-09-09 | D13. Migración `0006_periodificacion.sql` (`fecha_operacion`, `tipo=periodificacion`). Semilla `PUENTE.PERIODIFICACION` P/G. Entrada: fecha de imputación opcional; mismo día = un asiento; distinto + B/C = par enlazado. UI `/entrada-p` `/entrada-g` y listado fusionado. Apertura arrastra el puente; `GenerarApertura` mueve patas `periodificacion` al ejercicio nuevo. Tests `ConstructorAsientoPeriodificadoTest`, `PeriodificacionTest`. Golden intacto (el Excel no parte). Detalle en `docs/dev/periodificacion.md`. |
| 5 Importación idempotente | hecha | cursor-grok | 2026-09-09 | D8. Migración `0007_importacion_idempotente.sql` (`import_ejecuciones`, `import_filas`). `SincronizarAsientosImportados`: alta/salto/actualización/anulación solo si `origen=import`. `--dry-run`, `--centro`, `--ejercicio`. Excel: `fecha_operacion = fecha`. `ConvertirApuntesAAsientos` ya no borra el ejercicio. Tests en `ImportacionIdempotenteTest` + `HashFilaImportacionTest`. Golden intacto (mismos recuentos en la primera importación). Detalle en `docs/dev/importacion.md`. |
| 6 Autenticación | hecha | cursor-grok | 2026-09-09 | D7. Migración `0008_identidades.sql`. Login por email/alias, TOTP RFC 6238 obligatorio en centro, recovery, bloqueo 5/15 min, CSRF, sesión HttpOnly+SameSite=Lax, `rutas_acceso` (default deny). `scl` → identidad de centro. Tests `AutenticacionTest` + TOTP/CSRF/catálogo. Detalle en `docs/dev/acceso.md`. |
| 7 Nivel 1 personal | hecha | cursor-grok | 2026-09-09 | D5. Libro `X` por persona, plan P sin el 9 + CAJA/BANCO propias + periodificación. Entrada ingreso/gasto/traspaso con D13. Pantallas `/yo` estilo Monefy. Identidad demo `yo`. Aislamiento: saldos/613/listado del centro ignoran X. Tests `Nivel1Test`, `CatalogoMaestroPersonalTest`, `ConstructorAsientoPersonalTest`. Migración `0009_libro_personal.sql` (índices). Sin remesas (Fase 8). Detalle en `docs/dev/personal.md`. |
| 8 Remesas | hecha | cursor-grok | 2026-09-09 | D6. Tablas `remesas`/`remesa_lineas`/`remesa_solicitudes_detalle`, FK `asientos.remesa_id` (`0010_remesas.sql`). Envío mensual X→P, versionado, aceptar sustituye asientos en transacción, detalle bajo petición. UNIQUE incluye `anio` (ejercicio D11 largo). Tests `RemesaTest`, `HashRemesaTest`, `AgregadorRemesaPersonalTest`, `ConstructorAsientoRemesaTest`. Pantallas `/yo/remesas` y `/remesas`. Detalle en `docs/dev/remesas.md`. |
| 9 Multicentro | parcial | cursor-grok | 2026-09-09 | Vínculo usuario↔centro. Migración `0011_vinculo_usuario_centro.sql`. Alta de centro en `/centros` con secretario propio (`scl2` no ve el libro de `scl`). Correo en Nombres → identidad personal de esa persona/centro. `listarDeCentro` en nombres, apuntes, 613, E37, cierre, remesas e importación. Importación Excel aislada (`--centro` / UI `/centros`) sin pisar `configuracion`; vaciado temporal de asientos del centro para recargar. Test `MulticentroAccesoTest`. Pendiente: presupuesto/config por centro, informe consolidado. |
