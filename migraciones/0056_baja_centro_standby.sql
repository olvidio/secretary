-- Baja de cuentas de secretario: standby antes de purga (60 días por defecto en aplicación).

ALTER TABLE identidades
    ADD COLUMN IF NOT EXISTS baja_centro_programada_at TIMESTAMPTZ NULL;

ALTER TABLE identidades
    ADD COLUMN IF NOT EXISTS baja_centro_ejecutar_at TIMESTAMPTZ NULL;

CREATE TABLE IF NOT EXISTS identidad_baja_centro_respaldo (
    identidad_id BIGINT PRIMARY KEY REFERENCES identidades(id) ON DELETE CASCADE,
    centros_json JSONB NOT NULL
);
