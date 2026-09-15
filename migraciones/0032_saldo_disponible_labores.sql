-- Saldo disponible operativo, aparcamiento DISP, desgravación y propuestas 7x.

ALTER TABLE personas
    ADD COLUMN IF NOT EXISTS puede_desgravar BOOLEAN NOT NULL DEFAULT TRUE;

ALTER TABLE centro_partidas_labores
    ADD COLUMN IF NOT EXISTS desgrava BOOLEAN NOT NULL DEFAULT FALSE;

UPDATE centro_partidas_labores
   SET desgrava = TRUE
 WHERE codigo IN ('72', '73', '75', '76')
   AND desgrava IS DISTINCT FROM TRUE;

ALTER TABLE centros
    ADD COLUMN IF NOT EXISTS desgravacion_tramos_json JSONB NOT NULL DEFAULT
    '[{"hasta_cents":25000,"porcentaje":80},{"hasta_cents":null,"porcentaje":40}]'::jsonb;

ALTER TABLE remesas
    ADD COLUMN IF NOT EXISTS saldo_tesoreria_cents BIGINT NULL;

ALTER TABLE asientos DROP CONSTRAINT IF EXISTS asientos_origen_check;
ALTER TABLE asientos ADD CONSTRAINT asientos_origen_check
    CHECK (origen IN ('manual', 'import', 'cierre', 'remesa', 'banco', 'asignacion'));

CREATE TABLE IF NOT EXISTS saldos_disponibles (
    centro_id BIGINT NOT NULL REFERENCES centros(id) ON DELETE CASCADE,
    persona_id BIGINT NOT NULL REFERENCES personas(id) ON DELETE CASCADE,
    saldo_cents BIGINT NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (centro_id, persona_id)
);

CREATE TABLE IF NOT EXISTS saldos_disponibles_mov (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    centro_id BIGINT NOT NULL REFERENCES centros(id) ON DELETE CASCADE,
    persona_id BIGINT NOT NULL REFERENCES personas(id) ON DELETE CASCADE,
    ejercicio_id BIGINT NULL REFERENCES ejercicios(id),
    fecha DATE NOT NULL,
    origen TEXT NOT NULL CHECK (origen IN ('remesa', 'tesoreria', 'ajuste', 'asignacion')),
    importe_cents BIGINT NOT NULL,
    remesa_id BIGINT NULL REFERENCES remesas(id) ON DELETE SET NULL,
    asignacion_id BIGINT NULL,
    nota TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS saldos_disponibles_mov_persona_idx
    ON saldos_disponibles_mov (centro_id, persona_id, created_at DESC);
CREATE INDEX IF NOT EXISTS saldos_disponibles_mov_remesa_idx
    ON saldos_disponibles_mov (remesa_id) WHERE remesa_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS asignaciones_labores (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    centro_id BIGINT NOT NULL REFERENCES centros(id) ON DELETE CASCADE,
    ejercicio_id BIGINT NOT NULL REFERENCES ejercicios(id),
    estado TEXT NOT NULL CHECK (estado IN ('borrador', 'confirmada')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    confirmada_at TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS asignaciones_labores_lineas (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    asignacion_id BIGINT NOT NULL REFERENCES asignaciones_labores(id) ON DELETE CASCADE,
    persona_id BIGINT NOT NULL REFERENCES personas(id),
    codigo_maestro TEXT NOT NULL,
    importe_cents BIGINT NOT NULL CHECK (importe_cents > 0),
    pendiente_cents BIGINT NOT NULL CHECK (pendiente_cents >= 0),
    UNIQUE (asignacion_id, persona_id, codigo_maestro)
);

CREATE INDEX IF NOT EXISTS asignaciones_labores_centro_idx
    ON asignaciones_labores (centro_id, estado, id DESC);
CREATE INDEX IF NOT EXISTS asignaciones_labores_lineas_persona_idx
    ON asignaciones_labores_lineas (persona_id);

CREATE TABLE IF NOT EXISTS asignacion_labores_consumos (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    linea_id BIGINT NOT NULL REFERENCES asignaciones_labores_lineas(id) ON DELETE CASCADE,
    remesa_id BIGINT NOT NULL REFERENCES remesas(id) ON DELETE CASCADE,
    importe_cents BIGINT NOT NULL CHECK (importe_cents > 0),
    UNIQUE (linea_id, remesa_id)
);

ALTER TABLE saldos_disponibles_mov
    DROP CONSTRAINT IF EXISTS saldos_disponibles_mov_asignacion_id_fkey;
ALTER TABLE saldos_disponibles_mov
    ADD CONSTRAINT saldos_disponibles_mov_asignacion_id_fkey
    FOREIGN KEY (asignacion_id) REFERENCES asignaciones_labores(id) ON DELETE SET NULL;

-- Cuentas DISP.<INICIALES> (aparcamiento del sobrante; no consolidan en P/9).
INSERT INTO cuentas (
    centro_id, persona_id, cuenta_fisica_id, padre_id, libro, codigo,
    nombre, descripcion, tipo, naturaleza, codigo_maestro, imputable, orden, activo
)
SELECT
    p.centro_id,
    p.id,
    NULL,
    NULL,
    'P',
    'DISP.' || upper(p.iniciales),
    'Disponible pendiente de labores de ' || trim(p.nombre || ' ' || p.apellidos),
    'Aparcamiento del sobrante de remesa hasta asignarlo a partidas 7',
    'personal',
    'deudora',
    'DISP',
    TRUE,
    1,
    TRUE
FROM personas p
WHERE p.centro_id IS NOT NULL
  AND NOT EXISTS (
        SELECT 1 FROM cuentas c
         WHERE c.centro_id = p.centro_id
           AND c.persona_id = p.id
           AND c.libro = 'P'
           AND c.codigo_maestro = 'DISP'
    );
