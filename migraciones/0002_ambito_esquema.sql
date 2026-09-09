-- Fase 2 (docs/dev/plan_ampliaciones.md: D2, D3, D10, D11) — ámbito centro/ejercicio
-- y plan de cuentas jerárquico.
--
-- Esta migración es EXCLUSIVAMENTE esquema (CREATE/ALTER). No siembra ni rellena
-- ninguna fila: el backfill del centro/ejercicio actuales, el alta de las cuentas
-- físicas y la semilla del plan de cuentas los hace en PHP
-- `src\ambito\infrastructure\persistence\AmbitoSeeder::sembrar()`, invocada tanto
-- desde `db:migrate` (bin/console.php) como desde `SchemaInstaller::install()`
-- (db:install). El motivo es de orden: en una instalación nueva, la fila de
-- `configuracion` no existe todavía cuando se aplican las migraciones (la siembra
-- `SchemaInstaller::seedConfig()` es posterior), así que un backfill en SQL dentro
-- de esta migración vería la tabla `configuracion` vacía. Ver docs/dev/ambito.md.
--
-- Principio de esta fase: es ADITIVA. No se toca `apuntes`, `conceptos`,
-- `presupuesto_lineas` ni `arqueos`; nada pasa todavía a estas tablas nuevas (eso es
-- la Fase 3). Solo se añade `centro_id` a `personas`, en columna nueva y opcional.

CREATE TABLE IF NOT EXISTS centros (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo TEXT NOT NULL UNIQUE,
    nombre TEXT NOT NULL,
    tipo_cierre TEXT NOT NULL DEFAULT 'vivienda',
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Ejercicios de período libre (D11). `fecha_fin` es el fin REAL del ejercicio
-- (deducido de anio+modo en el `configuracion` legado); `fecha_corte` es la fecha
-- de corte de los informes (lo que hasta ahora era `configuracion.fecha_cierre`).
-- Son campos distintos a propósito: confundirlos es la trampa documentada en
-- `PeriodoEjercicio` y en docs/dev/ambito.md. `estado` no admite más asientos
-- cuando está `cerrado`; hoy nada escribe todavía en asientos de esta tabla (eso
-- es la Fase 3), así que por ahora es solo informativo.
CREATE TABLE IF NOT EXISTS ejercicios (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    centro_id BIGINT NOT NULL REFERENCES centros(id),
    etiqueta TEXT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    fecha_corte DATE NOT NULL,
    estado TEXT NOT NULL DEFAULT 'abierto' CHECK (estado IN ('abierto', 'cerrado')),
    ejercicio_anterior_id BIGINT NULL REFERENCES ejercicios(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (centro_id, fecha_inicio),
    CHECK (fecha_fin > fecha_inicio),
    CHECK (fecha_corte >= fecha_inicio AND fecha_corte <= fecha_fin)
);

CREATE INDEX IF NOT EXISTS ejercicios_centro_idx ON ejercicios (centro_id);

-- Tesorería física compartida entre libros (D10): la caja o la cuenta bancaria del
-- mundo real. No pertenece a un libro; sus cuentas de mayor por libro viven en
-- `cuentas` (columna `cuenta_fisica_id`).
CREATE TABLE IF NOT EXISTS cuentas_fisicas (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    centro_id BIGINT NOT NULL REFERENCES centros(id),
    tipo TEXT NOT NULL CHECK (tipo IN ('caja', 'banco')),
    nombre TEXT NOT NULL,
    iban TEXT NULL,
    orden INTEGER NOT NULL DEFAULT 0,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (centro_id, nombre)
);

-- Plan de cuentas jerárquico (D2). `codigo_maestro` es el código del plan
-- principal al que consolida esta cuenta (una subcuenta personal, una cuenta de
-- mayor de tesorería por libro, etc. siempre declaran su codigo_maestro).
-- Solo las cuentas hoja (`imputable = true`) admiten movimientos (todavía no hay
-- movimientos contra esta tabla: eso es la Fase 3).
--
-- `centro_id` es NOT NULL (a diferencia del esquema orientativo de la sección 3
-- del plan, que lo dejaba NULL para un "plan maestro compartido"): en este
-- proyecto de un solo despliegue multi-centro cada cuenta -incluidas las del plan
-- maestro- pertenece a un centro concreto; no hay hoy un plan verdaderamente
-- global sin dueño. Ajuste menor documentado, no la estructura.
CREATE TABLE IF NOT EXISTS cuentas (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    centro_id BIGINT NOT NULL REFERENCES centros(id),
    persona_id BIGINT NULL REFERENCES personas(id),
    cuenta_fisica_id BIGINT NULL REFERENCES cuentas_fisicas(id),
    padre_id BIGINT NULL REFERENCES cuentas(id),
    libro TEXT NOT NULL CHECK (libro IN ('P', 'G', 'X')),
    codigo TEXT NOT NULL,
    nombre TEXT NOT NULL,
    descripcion TEXT NOT NULL DEFAULT '',
    tipo TEXT NOT NULL CHECK (tipo IN ('ingreso', 'gasto', 'tesoreria', 'personal', 'patrimonio', 'puente')),
    naturaleza TEXT NOT NULL CHECK (naturaleza IN ('deudora', 'acreedora')),
    codigo_maestro TEXT NOT NULL,
    imputable BOOLEAN NOT NULL DEFAULT TRUE,
    orden INTEGER NOT NULL DEFAULT 0,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (centro_id, persona_id, libro, codigo)
);
-- Nota sobre la UNIQUE anterior: Postgres trata cada NULL de `persona_id` como
-- distinto a efectos de unicidad, así que esa restricción NO basta para hacer
-- idempotente (vía ON CONFLICT) la siembra de cuentas sin persona (plan maestro,
-- tesorería, "deudores por vivienda"). `AmbitoSeeder` no usa ON CONFLICT para
-- estas filas: hace SELECT-antes-de-INSERT tratando `persona_id IS NULL`
-- explícitamente. Ver docs/dev/ambito.md.

CREATE INDEX IF NOT EXISTS cuentas_centro_idx ON cuentas (centro_id);
CREATE INDEX IF NOT EXISTS cuentas_maestro_idx ON cuentas (centro_id, codigo_maestro);

-- `personas` gana ámbito. Nullable: en un `db:install` en limpio no hay personas
-- todavía, y en la base real las 10 existentes se backfillean por
-- `AmbitoSeeder::sembrar()` (columna ya presente, tabla ya poblada). No se pone
-- NOT NULL porque `ImportarExcelSecretario` sigue creando personas sin ámbito
-- explícito (esta fase no lo toca, ver docs/dev/ambito.md); un futuro repaso podrá
-- endurecerla cuando la importación estampe centro_id.
ALTER TABLE personas ADD COLUMN IF NOT EXISTS centro_id BIGINT NULL REFERENCES centros(id);
CREATE INDEX IF NOT EXISTS personas_centro_idx ON personas (centro_id);
