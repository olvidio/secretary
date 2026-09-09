# Ámbito, ejercicios de período libre y plan de cuentas (Fase 2)

Documenta lo implementado en la **Fase 2** de `docs/dev/plan_ampliaciones.md`
(decisiones D2, D3, D10, D11). Léelo junto con `docs/dev/migraciones.md` (cómo se
aplican las migraciones) y `docs/dev/golden_master.md` (por qué esta fase no debe
mover ni un céntimo el golden master).

**Principio de la fase, y no lo olvides si vienes a tocar esto:** es **aditiva**.
`apuntes`, `conceptos`, `presupuesto_lineas` y `arqueos` siguen funcionando
exactamente igual que antes de la Fase 2. Las tablas nuevas (`centros`,
`ejercicios`, `cuentas_fisicas`, `cuentas`) se crean y se rellenan en paralelo,
pero **nada contabiliza contra ellas todavía**: convertir a partida doble es la
Fase 3.

---

## 1. La trampa fecha_fin / fecha_corte

Esta es la trampa que **hay que seguir respetando** en cualquier desarrollo
posterior sobre esta fase.

`configuracion.fecha_cierre` (el campo legado, singleton, de antes de la Fase 2)
**no es el final del ejercicio fiscal**: es la fecha de corte hasta la que se han
introducido apuntes y hasta la que calculan los informes hoy. El final real del
ejercicio se deduce de `configuracion.anio` + `configuracion.modo_ejercicio`
(`ConfiguracionCentro::finEjercicioLegado()`: 31 de diciembre para `Año`, 31 de
agosto del año siguiente para `Curso`).

Ejemplo real (verificado contra la base de producción `secretario` el
2026-09-08): centro Montagut, `anio=2026`, `modo_ejercicio=Año`,
`fecha_inicio=2026-01-01`, `fecha_cierre=2026-06-30`. El ejercicio fiscal
**completo** es 2026-01-01 .. 2026-12-31; 2026-06-30 es solo el corte de hoy.
`PeriodoEjercicio::contiene()` valida contra el ejercicio **completo**, no contra
el corte: un apunte de diciembre de 2026 es válido hoy aunque el corte esté en
junio, y **esto debe seguir siendo así**.

Esta fase traduce esa distinción a un modelo persistente explícito:

- `ejercicios.fecha_fin` = fin real del ejercicio fiscal.
- `ejercicios.fecha_corte` = lo que antes era `configuracion.fecha_cierre`.
- Nunca son la misma columna. Confundirlas revive exactamente el bug que esta
  fase existe para evitar.

`PeriodoEjercicio` (`src/shared/domain/value_objects/PeriodoEjercicio.php`) quedó
reescrito con tres fechas (`fechaInicio`, `fechaFin`, `fechaCorte`) y dos métodos:

- `mesesTotales()` — meses de `fechaInicio` a `fechaFin`, ambos inclusive. Para un
  ejercicio enero-diciembre da 12, igual que siempre.
- `mesesTranscurridos()` — meses de `fechaInicio` a `fechaCorte`, ambos inclusive.
  Es el antiguo `mesesHasta()` (que en realidad ya calculaba contra el corte,
  aunque el campo se llamara `fechaCierre`).

El prorrateo del 613 (`Calculadora613`) pasa de `previsto × meses / 12` a
`previsto × mesesTranscurridos / mesesTotales`. Para un ejercicio enero-diciembre
el resultado es idéntico al de antes — por eso el golden master no se mueve — pero
la fórmula ya funciona para cualquier período libre (junio-mayo, etc.), cubierto
por `AmbitoEjercicioTest::testEjercicioLibreJunioAMayoProrrateaCorrectamente`.

`ConfiguracionCentro::toArray()` **no se ha tocado**: sigue exponiendo
`meses => mesesTranscurridos()` con la misma clave y el mismo valor que antes
(comparado byte a byte contra `tests/golden/configuracion.json`). La distinción
fin/corte vive en el modelo nuevo (`Ejercicio`), no en `ConfiguracionCentro`.

---

## 2. Modelo de ámbito

```
centros(id, codigo UNIQUE, nombre, tipo_cierre, activo, created_at)

ejercicios(id, centro_id FK, etiqueta,
           fecha_inicio, fecha_fin, fecha_corte,
           estado ('abierto'|'cerrado'),
           ejercicio_anterior_id FK NULL,
           UNIQUE(centro_id, fecha_inicio),
           CHECK (fecha_fin > fecha_inicio),
           CHECK (fecha_corte BETWEEN fecha_inicio AND fecha_fin))

cuentas_fisicas(id, centro_id FK, tipo ('caja'|'banco'), nombre, iban NULL,
                orden, activo, UNIQUE(centro_id, nombre))

cuentas(id, centro_id FK, persona_id FK NULL, cuenta_fisica_id FK NULL,
        padre_id FK NULL, libro ('P'|'G'|'X'), codigo, nombre, descripcion,
        tipo, naturaleza, codigo_maestro, imputable, orden, activo,
        UNIQUE(centro_id, persona_id, libro, codigo))

personas.centro_id  -- columna nueva, nullable, backfilleada
```

Migración `migraciones/0002_ambito_esquema.sql`. Es puro DDL: no siembra ninguna
fila (ver más abajo por qué). No se ha tocado ninguna migración ya aplicada.

**Ajuste sobre el DDL orientativo de la sección 3 de `plan_ampliaciones.md`:**
`cuentas.centro_id` es `NOT NULL` aquí (el documento orientativo lo dejaba `NULL`
para un "plan maestro compartido" global). En este despliegue no existe hoy un
plan verdaderamente sin dueño: cada cuenta, incluidas las del plan maestro,
pertenece a un centro concreto. Es un ajuste de detalle, no de estructura; se
puede revisar si en la Fase 9 aparece un plan maestro realmente compartido entre
centros.

### `ContextoActual`

`src/ambito/domain/value_objects/ContextoActual.php` — `{centroId, ejercicioId,
personaId?}`. Se resuelve en el contenedor DI
(`src/shared/config/dependencies.php`) asumiendo, de momento, un único centro
activo (el primero devuelto por `CentroRepository::listar()`) y su ejercicio
abierto más reciente (`EjercicioRepository::abiertoDe()`). La selección explícita
de centro en sesión, para multicentro operativo de verdad, es la **Fase 9**.

**Qué usa ya `ContextoActual` y qué no (inventario, para que nadie lo dé por
hecho):**

- Ningún repositorio existente de `apuntes`, `personas`, `conceptos` o
  `presupuesto_lineas` filtra todavía por `ContextoActual`. Sus tablas no tienen
  columna de ámbito (salvo `personas.centro_id`, que existe pero no se usa como
  filtro en ninguna consulta) porque esta fase es aditiva y no debía cambiar su
  comportamiento.
- Los repositorios nuevos (`CentroRepository`, `EjercicioRepository`,
  `CuentaFisicaRepository`, `CuentaRepository`) sí reciben `centroId` explícito en
  cada método relevante (`listarDeCentro()`, `buscar()`, `imputablesDe()`), pero
  como parámetro directo, no vía `ContextoActual` inyectado: hoy solo hay un
  centro real, así que inyectarlo habría sido prematuro. Queda disponible en el
  contenedor para que la Fase 3 en adelante lo use según convenga (por ejemplo,
  para que `RegistrarAsiento` no necesite recibir `ejercicio_id` en cada llamada).

---

## 3. Por qué `AmbitoSeeder` se invoca desde dos sitios

`src/ambito/infrastructure/persistence/AmbitoSeeder.php` puebla centro, ejercicio,
cuentas físicas, ámbito de personas y plan de cuentas a partir de la fila de
`configuracion`. Se llama desde:

1. `bin/console.php`, rama `db:migrate`, justo después de `$runner->migrar()`.
   Cubre la base real (`secretario`), que **ya tiene** una fila de `configuracion`
   con datos reales antes de que exista ninguna tabla de ámbito.
2. `SchemaInstaller::install()`, justo después de `seedConfig()`. Cubre
   `db:install` desde cero: aquí la fila de `configuracion` la crea el propio
   `seedConfig()` con datos placeholder, y `AmbitoSeeder` corre inmediatamente
   después sobre esos datos placeholder.

La migración `0002` es deliberadamente solo DDL (no siembra nada) porque, en una
instalación nueva, las migraciones se aplican **antes** de que exista la fila de
`configuracion` — un backfill en SQL dentro de la propia migración la vería vacía.
Por eso el backfill vive en PHP y se invoca dos veces, en el orden correcto en
cada camino, en lugar de una sola vez dentro del runner de migraciones.

`AmbitoSeeder::sembrar()` es **completamente idempotente**: se puede llamar en
cada `db:migrate` sin duplicar filas ni reabrir un ejercicio cerrado a mano (el
`estado` no se toca en el `UPDATE` de sincronización, solo `etiqueta`, `fecha_fin`
y `fecha_corte`). Nunca escribe en `apuntes`, `conceptos`, `presupuesto_lineas` ni
`arqueos`.

### Por qué no usa `INSERT ... ON CONFLICT`

La restricción `UNIQUE(centro_id, persona_id, libro, codigo)` de `cuentas` no
sirve como objetivo de `ON CONFLICT` para las filas sin persona (plan maestro,
tesorería, "deudores por vivienda"): Postgres trata cada `NULL` de `persona_id`
como distinto a efectos de unicidad, así que dos filas con el mismo
`(centro_id, libro, codigo)` y `persona_id = NULL` **no** disparan el conflicto.
`AmbitoSeeder::upsertCuenta()` resuelve esto a mano: `SELECT ... WHERE persona_id
IS NULL` explícito cuando corresponde, antes de decidir `INSERT` o `UPDATE`.

### La trampa PDO/PostgreSQL de los booleanos

`PDOStatement::execute(array $params)` liga todos los parámetros como
`PDO::PARAM_STR` por defecto. El cast implícito de PHP `(string) false` es `''`
(cadena vacía) — que PostgreSQL **rechaza** para una columna `BOOLEAN`
(`invalid input syntax for type boolean: ""`) — mientras que `(string) true` es
`'1'`, que sí es un literal válido. El bug real: `imputable => false` para la
cuenta `saldo` (P/9) del plan maestro disparaba justo esa excepción.

**Arreglo aplicado en cuatro sitios** (buscar el comentario "PDOStatement::execute()
... invalid input syntax for type boolean" para localizarlos exactamente):
castear explícitamente a `(int)` antes de ligar — `'0'`/`'1'` son literales
booleanos válidos en Postgres. Afecta a `AmbitoSeeder::upsertCuenta()`
(`:imputable`), `PdoCentroRepository` (`:activo`), `PdoCuentaFisicaRepository`
(`:a`) y `PdoCuentaRepository::params()` (`:imputable`, `:activo`). Si añades una
nueva columna booleana a cualquier repositorio PDO de este proyecto que ligue por
array asociativo, aplica el mismo cast.

---

## 4. Plan de cuentas: mapeo concepto → cuenta

`AmbitoSeeder::sembrarPlanMaestro()` recorre `CatalogoConceptos::todos()` (26
códigos de P, 23 de G) y crea una cuenta por código, con `codigo_maestro` = su
propio código. La `naturaleza` del catálogo decide el `tipo` contable de la
cuenta:

| `naturaleza` del catálogo | Ejemplo | `tipo` de cuenta | `naturaleza` contable | `imputable` |
| --- | --- | --- | --- | --- |
| `ingreso` | P/111 "Trabajo", G/11 "Vivienda / local" | `ingreso` | acreedora | sí |
| `gasto` | P/21 "Vivienda", G/201 "Agua" | `gasto` | deudora | sí |
| `saldo` (solo P/9) | "Saldo en las c/c personales" | `personal` | deudora | **no** — es el agregado de las cuentas personales individuales (ver §5), ningún apunte real lo usa directamente |
| `disponible` (solo G/32) | "Disponible a 1 de enero" | `patrimonio` | acreedora | sí — contrapartida del asiento de apertura (D12); hoy sigue siendo un importe tecleado a mano |
| `transferencia` (G/41, G/42) | "Banco a Caja" / "Caja a banco" | `puente` | deudora | sí — preparado para D10, ningún apunte real usa hoy 41/42 |

Además del plan maestro, `AmbitoSeeder` crea cuentas sintéticas que no vienen de
`CatalogoConceptos` porque no son conceptos del 613, sino contrapartidas:

- **Tesorería (D10):** `CAJA.1/P`, `CAJA.1/G`, `BANCO.1/P`, `BANCO.1/G` — una
  cuenta de mayor por cada combinación de cuenta física × libro. `codigo_maestro`
  = `CAJA` / `BANCO`.
- **Cuentas personales (libro P):** una por persona del centro,
  `codigo = 'CC.' . INICIALES`, `codigo_maestro = '9'` (consolida en el agregado
  "Saldo en las c/c personales" del plan maestro P).
- **Deudores por vivienda (libro G):** una única cuenta colectiva,
  `codigo = 'DEUDORES.VIV'`, `codigo_maestro = 'DEUDORES-VIV'` (ver §5).
- **Puente de periodificación (D13):** `PUENTE.PERIODIFICACION` en P y G. Ver
  `docs/dev/periodificacion.md`.

Verificado en producción (`secretario`, 2026-09-08, tras `db:migrate`): 54 filas
en `cuentas` — P: 4 ingreso + 21 gasto + 11 personales (10 `CC.*` + la agregada
`'9'` no imputable) + 2 tesorería; G: 5 ingreso + 15 gasto + 1 patrimonio + 1
personal (`DEUDORES.VIV`) + 2 puente + 2 tesorería.

### Cómo se resuelve la contrapartida de `origen = A`

El Excel marca un apunte con `origen = A` cuando "no afecta a caja ni banco": es
un movimiento entre la persona y el centro que no mueve tesorería física. La
traducción a partida doble (sección 1 de `plan_ampliaciones.md`) dice que su
contrapartida es "la cuenta corriente de la persona (libro P) o deudores por
vivienda (libro G)". Esta fase no construye todavía el asiento (eso es la Fase 3),
pero sí deja lista la cuenta contrapartida:

- **Libro P:** la cuenta personal `CC.<INICIALES>` de cada persona
  (`codigo_maestro = '9'`), una por persona.
- **Libro G:** como G no distingue personas, hace falta **una única cuenta
  colectiva** que agregue "lo que se debe/deben los residentes en conjunto":
  `DEUDORES.VIV`. Es sintética (no viene de un concepto real del 613) por la
  misma razón que `CAJA`/`BANCO`: no es un concepto, es la contrapartida agregada
  de muchos movimientos.

---

## 5. Código huérfano `P / 11` (resuelto 2026-09-08)

**Histórico.** Verificado directamente contra la base real (`secretario`) y
cubierto por `tests/integration/PlanDeCuentasCoberturaTest.php`: existía
exactamente un par `(cuenta, concepto_codigo)` en los 423 apuntes reales que
**no** tenía cuenta imputable correspondiente en el plan maestro sembrado:
`cuenta = 'P'`, `concepto_codigo = '11'` (el apunte real con `id = 159`).

La razón: el plan maestro de P (`CatalogoConceptos::todos()`) tiene los códigos
`111`, `112`, `113` pero **no** un código bare `'11'`. El código `'11'` sí existe,
pero en G, como "Vivienda / local" (ingreso). Es decir: el apunte 159 tenía un
error de tecleo en el Excel origen — faltaba un dígito de `111` (nómina de
Agustí Fontarnau, "1. Trabajo"), no un código legítimamente distinto.

**Resolución (Fase "2b Reparación importador",
`docs/dev/plan_ampliaciones.md`).** Se corrigió el dato de origen: la celda E160
de la hoja "Talonarios" en `moviments2026.xlsm` pasó de `11` a `111`
(copia de seguridad del fichero anterior en
`/home/dani/moviments2026.xlsm.bak-20260908`). Al reimportar, el huérfano
desaparece sin necesidad de inventar ningún mapeo ni cuenta de absorción.
`tests/integration/PlanDeCuentasCoberturaTest.php` ya no tolera ninguna lista
fija de anomalías conocidas: exige **cero** huérfanos y falla ante cualquiera
nuevo que aparezca en el futuro (un error de tecleo distinto, o un código
realmente nuevo que necesite alta explícita en el plan maestro). El cambio en
los informes que este dato movía (613 P, saldos, E37) está documentado en
`docs/dev/golden_master.md`, sección "Regeneraciones documentadas".

---

## 6. UI de alta de ejercicios

`frontend/ambito/view/ejercicios.php`, ruta `/ejercicios` (enlace en el grupo
"Utilidades" del nav, `frontend/shared/view/layout.php`). Formulario mínimo:
`etiqueta`, `fecha_inicio`, `fecha_fin`, `fecha_corte` (los tres últimos como
`<input type="date">` libres, sin enum `Año`/`Curso`), que llama a
`POST /api/ejercicios` (`EjercicioController::create` →
`CrearEjercicio::ejecutar()`, que valida el no-solapamiento de D11 y lanza
`InvalidArgumentException` si lo hay). Tabla de listado contra
`GET /api/ejercicios` con `etiqueta`, fechas, `meses_totales`,
`meses_transcurridos` y `estado`. El texto de ayuda del formulario explica en
lenguaje llano la diferencia entre `fecha_fin` (fin del ejercicio) y
`fecha_corte` (hasta dónde se ha contabilizado), para que quien dé de alta un
ejercicio no repita el error de fondo que motivó este documento.

---

## 7. Tests de esta fase

| Test | Qué cubre |
| --- | --- |
| `tests/integration/GoldenMasterTest.php` (ya existía) | Que nada de esta fase mueve el golden master. Corre **sin** `GOLDEN_UPDATE=1`. |
| `tests/integration/PlanDeCuentasCoberturaTest.php` | Que todo par `(cuenta, concepto_codigo)` de los 423 apuntes reales tiene cuenta imputable en el plan sembrado, sin excepciones (cero huérfanos; ver §5). |
| `tests/integration/ImportacionIdempotenteTest.php` (Fase "2b Reparación importador") | Que reimportar el Excel dos veces seguidas sobre la MISMA base ya sembrada (con `cuentas.persona_id` ya poblado) no falla y no duplica personas ni cuentas — el camino real que `GoldenMasterTest` y `PlanDeCuentasCoberturaTest` no ejercitan porque ambos recrean el esquema en cada ejecución. |
| `tests/integration/AmbitoEjercicioTest.php::testEjercicioLibreJunioAMayoProrrateaCorrectamente` | Ejercicio junio-mayo persistido y releído: `mesesTotales=12`, `mesesTranscurridos=6`, `contiene()` correcto, y que la persistencia no confunde `fecha_fin` con `fecha_corte`. |
| `tests/integration/AmbitoEjercicioTest.php::testNoSePuedenSolaparDosEjerciciosDelMismoCentro` | Regla de no-solapamiento de D11 (`CrearEjercicio` lanza `InvalidArgumentException`). |
| `tests/integration/AmbitoEjercicioTest.php::testDosCentrosNoInterfierenEntreSi` | Aislamiento: un segundo centro no ve los ejercicios ni las cuentas del primero. |

Todos usan `Tests\Soporte\BaseDeDatosAislada` (base `secretario_test`, esquema
recreado en cada test) — ninguno toca la base de desarrollo `secretario`.

---

## 8. Verificación contra datos reales (2026-09-08)

- `php bin/console.php db:migrate` contra `secretario`: los 423 apuntes y las 10
  personas siguieron intactos; `centros` quedó con 1 fila (Montagut); `ejercicios`
  con 1 fila con `fecha_inicio=2026-01-01`, `fecha_fin=2026-12-31` (el año
  **completo**, no el corte) y `fecha_corte=2026-06-30`; `cuentas_fisicas` con
  Caja y Banco; `personas.centro_id` backfilleado en las 10 filas; `cuentas` con
  54 filas con el desglose de §4.
- `php bin/console.php db:install` contra una base nueva de comprobación
  (`secretario_install_check`, creada y destruida solo para esta verificación):
  centro placeholder, ejercicio con `fecha_fin` y `fecha_corte` correctamente
  distintas incluso para los datos placeholder, 54 cuentas (49 del plan maestro +
  4 de tesorería + 1 `DEUDORES.VIV`, sin cuentas personales porque no hay
  personas en una instalación en limpio), 49 conceptos.

---

## 9. Obstáculos y decisiones abiertas para la Fase 3

1. ~~**El huérfano `P/11`** (§5) necesita una decisión antes de migrar `apuntes` a
   `asientos`: corregir el dato de origen o decidir su mapeo explícito.~~ Resuelto
   2026-09-08 (Fase "2b Reparación importador"): se corrigió el dato de origen.
   `PlanDeCuentasCoberturaTest` exige ahora cero huérfanos, así que la Fase 3
   heredará ese requisito ya cumplido en vez de tener que decidirlo ella misma.
2. **Ningún repositorio de `apuntes`/`personas`/`conceptos`/`presupuesto_lineas`
   filtra todavía por ámbito.** La Fase 3, al reescribirlos contra `asientos`, es
   el momento natural de inyectarles `ContextoActual` en vez de asumir un único
   centro/ejercicio implícito.
3. **El orden de siembra `configuracion` → `AmbitoSeeder`** es un artefacto real
   de cómo arranca la aplicación (§3), no solo de los tests: cualquier código
   futuro que dependa de que el ámbito ya esté poblado debe tenerlo en cuenta si
   se ejecuta antes de que exista la fila de `configuracion`.
4. **`cuentas.centro_id NOT NULL`** (el ajuste de §2) puede necesitar revisarse si
   la Fase 9 introduce un plan verdaderamente compartido entre centros.
5. **La cuenta `personal` no imputable (P/9)** y la colectiva `DEUDORES.VIV` (G)
   existen ya, pero ningún asiento las usa todavía: la Fase 3 es quien las
   ejercitará de verdad al traducir los apuntes `origen = A`.
