-- Preferencia de disposición de menús por identidad (tipo excel | burger).
ALTER TABLE identidades
    ADD COLUMN layout TEXT NOT NULL DEFAULT 'excel';

ALTER TABLE identidades
    ADD CONSTRAINT identidades_layout_chk CHECK (layout IN ('excel', 'burger'));
