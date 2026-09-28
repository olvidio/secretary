-- Cantidad fija que la persona deja en su caja o banco y no envía al centro.
-- El disponible de la remesa es el saldo menos este remanente.

ALTER TABLE personas
    ADD COLUMN IF NOT EXISTS remanente_cents BIGINT NOT NULL DEFAULT 0;
