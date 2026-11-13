-- Listados del club: agrupar por texto de la glosa. Lista JSON de {nombre, patron};
-- el patrón es una expresión regular (gas = Gas Natural | NATURGY).
ALTER TABLE listados ADD COLUMN IF NOT EXISTS grupos_glosa TEXT NOT NULL DEFAULT '[]';
