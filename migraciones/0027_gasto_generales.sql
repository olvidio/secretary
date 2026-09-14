-- Gasto personal imputable a generales (G/11 + concepto G) al aceptar la remesa.

ALTER TABLE asientos
    ADD COLUMN IF NOT EXISTS gasto_generales BOOLEAN NOT NULL DEFAULT FALSE;

ALTER TABLE asientos
    ADD COLUMN IF NOT EXISTS concepto_generales TEXT NULL;
