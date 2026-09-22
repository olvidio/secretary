-- Solicitud de baja voluntaria de cuenta personal (confirmación por correo).

ALTER TABLE identidades
    ADD COLUMN IF NOT EXISTS baja_cuenta_token TEXT NULL;

ALTER TABLE identidades
    ADD COLUMN IF NOT EXISTS baja_cuenta_expira TIMESTAMPTZ NULL;

CREATE UNIQUE INDEX IF NOT EXISTS identidades_baja_cuenta_token_idx
    ON identidades (baja_cuenta_token)
    WHERE baja_cuenta_token IS NOT NULL;
