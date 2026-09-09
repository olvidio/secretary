-- Fase 9 (parcial): vincular identidades a un centro y correos personales a un nombre.
-- Las iniciales dejan de ser únicas en toda la base: cada centro tiene las suyas.
-- Un correo en Nombres identifica a la persona de ese centro (D7: identidad_persona).

ALTER TABLE personas ADD COLUMN IF NOT EXISTS email TEXT NULL;

ALTER TABLE personas DROP CONSTRAINT IF EXISTS personas_iniciales_key;

CREATE UNIQUE INDEX IF NOT EXISTS personas_centro_iniciales_uidx
    ON personas (centro_id, lower(iniciales))
    WHERE centro_id IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS personas_email_uidx
    ON personas (lower(email))
    WHERE email IS NOT NULL AND email <> '';

CREATE UNIQUE INDEX IF NOT EXISTS identidad_persona_persona_uidx
    ON identidad_persona (persona_id);
