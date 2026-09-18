-- Conceptos del plan contable (plantilla). El cap. VII (7x en P) es editable por centro.

CREATE TABLE IF NOT EXISTS plan_conceptos (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    plan_contable_id BIGINT NOT NULL REFERENCES planes_contables(id) ON DELETE CASCADE,
    codigo TEXT NOT NULL,
    cuenta TEXT NOT NULL CHECK (cuenta IN ('P', 'G')),
    nombre TEXT NOT NULL,
    descripcion TEXT NOT NULL DEFAULT '',
    naturaleza TEXT NOT NULL,
    orden INTEGER NOT NULL DEFAULT 0,
    UNIQUE (plan_contable_id, cuenta, codigo)
);

CREATE INDEX IF NOT EXISTS plan_conceptos_plan_idx ON plan_conceptos (plan_contable_id, cuenta, orden);
