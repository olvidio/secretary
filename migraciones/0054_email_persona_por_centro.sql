-- El correo de un nombre es único dentro de su centro.
-- El libro personal (otro centro, tipo p) puede llevar el mismo correo de la cuenta:
-- al aprobar una solicitud hay que copiarlo al nombre del centro sin chocar con el índice global.
DROP INDEX IF EXISTS personas_email_uidx;

CREATE UNIQUE INDEX IF NOT EXISTS personas_email_centro_uidx
    ON personas (centro_id, lower(email))
    WHERE centro_id IS NOT NULL AND email IS NOT NULL AND email <> '';
