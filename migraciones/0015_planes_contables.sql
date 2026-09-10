-- Plan contable por centro (H16n) y partidas variables del cap. VII del 613 P.

CREATE TABLE IF NOT EXISTS planes_contables (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo TEXT NOT NULL UNIQUE,
    nombre TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS centro_partidas_labores (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    centro_id BIGINT NOT NULL REFERENCES centros(id) ON DELETE CASCADE,
    codigo TEXT NOT NULL,
    etiqueta TEXT NOT NULL,
    orden INTEGER NOT NULL DEFAULT 0,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (centro_id, codigo)
);

CREATE INDEX IF NOT EXISTS centro_partidas_labores_centro_idx
    ON centro_partidas_labores (centro_id, orden);

ALTER TABLE centros ADD COLUMN IF NOT EXISTS plan_contable_id BIGINT NULL
    REFERENCES planes_contables(id);
