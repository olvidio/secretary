-- Extractos CSV del libro personal: idempotencia por huella y origen de asiento.

ALTER TABLE asientos DROP CONSTRAINT IF EXISTS asientos_origen_check;
ALTER TABLE asientos ADD CONSTRAINT asientos_origen_check
    CHECK (origen IN ('manual', 'import', 'cierre', 'remesa', 'banco'));

CREATE TABLE banco_import_filas (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    persona_id BIGINT NOT NULL REFERENCES personas(id) ON DELETE CASCADE,
    banco TEXT NOT NULL,
    huella TEXT NOT NULL,
    asiento_id BIGINT NOT NULL REFERENCES asientos(id) ON DELETE CASCADE,
    fecha DATE NOT NULL,
    importe TEXT NOT NULL,
    concepto TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (persona_id, banco, huella)
);

CREATE INDEX banco_import_filas_persona_idx ON banco_import_filas (persona_id, banco);
