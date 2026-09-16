-- Plantilla del centro asociada a un movimiento personal (se ejecuta al aceptar la remesa).

ALTER TABLE asientos
    ADD COLUMN IF NOT EXISTS plantilla_apunte_id BIGINT NULL
        REFERENCES plantillas_apunte(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS asientos_plantilla_apunte_idx ON asientos (plantilla_apunte_id)
    WHERE plantilla_apunte_id IS NOT NULL;
