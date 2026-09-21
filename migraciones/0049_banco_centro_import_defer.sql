-- Import banco G: no crear asiento hasta categorizar; opción libro personal.

ALTER TABLE banco_centro_import_filas
    ALTER COLUMN asiento_id DROP NOT NULL;

ALTER TABLE banco_centro_import_filas
    ADD COLUMN IF NOT EXISTS persona_id BIGINT NULL REFERENCES personas(id) ON DELETE SET NULL;
