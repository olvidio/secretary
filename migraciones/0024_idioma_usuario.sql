-- Idioma de interfaz preferido por identidad (es | ca).
ALTER TABLE identidades
    ADD COLUMN idioma TEXT NOT NULL DEFAULT 'es';

ALTER TABLE identidades
    ADD CONSTRAINT identidades_idioma_chk CHECK (idioma IN ('es', 'ca'));
