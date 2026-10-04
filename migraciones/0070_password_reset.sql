-- Enlace de un solo uso para elegir una contraseña nueva.
-- Se guarda la huella sha256 del token, nunca el token en claro.

ALTER TABLE identidades
    ADD COLUMN IF NOT EXISTS password_reset_token_hash TEXT NULL;

ALTER TABLE identidades
    ADD COLUMN IF NOT EXISTS password_reset_expira TIMESTAMPTZ NULL;

CREATE UNIQUE INDEX IF NOT EXISTS identidades_password_reset_token_idx
    ON identidades (password_reset_token_hash)
    WHERE password_reset_token_hash IS NOT NULL;
