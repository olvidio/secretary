-- Cambio de correo: nuevo email en email_pendiente hasta confirmar el enlace.
ALTER TABLE identidades ADD COLUMN IF NOT EXISTS email_pendiente TEXT NULL;
