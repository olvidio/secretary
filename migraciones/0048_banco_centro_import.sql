-- Extractos CSV del libro G del centro: idempotencia por huella.

ALTER TABLE centros ADD COLUMN IF NOT EXISTS banco_csv TEXT NULL;

CREATE TABLE banco_centro_import_filas (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    centro_id BIGINT NOT NULL REFERENCES centros(id) ON DELETE CASCADE,
    cuenta_fisica_id BIGINT NULL REFERENCES cuentas_fisicas(id) ON DELETE SET NULL,
    banco TEXT NOT NULL,
    huella TEXT NOT NULL,
    asiento_id BIGINT NOT NULL REFERENCES asientos(id) ON DELETE CASCADE,
    fecha DATE NOT NULL,
    importe TEXT NOT NULL,
    concepto TEXT NOT NULL,
    concepto_asignado TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX banco_centro_import_uq ON banco_centro_import_filas (
    centro_id, COALESCE(cuenta_fisica_id, 0), banco, huella
);

CREATE INDEX banco_centro_import_centro_idx ON banco_centro_import_filas (centro_id, banco);
