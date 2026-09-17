-- Verificación de correo en el alta pública (registro desde login).
ALTER TABLE identidades
    ADD COLUMN IF NOT EXISTS email_verificado_at TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS email_verificacion_token TEXT NULL,
    ADD COLUMN IF NOT EXISTS email_verificacion_expira TIMESTAMPTZ NULL;

-- Cuentas ya existentes (seed, secretarios): consideradas verificadas.
UPDATE identidades
SET email_verificado_at = COALESCE(email_verificado_at, created_at)
WHERE email_verificado_at IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS identidades_email_verificacion_token_idx
    ON identidades (email_verificacion_token)
    WHERE email_verificacion_token IS NOT NULL;
