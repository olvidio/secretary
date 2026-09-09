-- Cada línea de plantilla puede ser del libro P o G (p. ej. cuota club).

ALTER TABLE plantilla_lineas_apunte
    ADD COLUMN IF NOT EXISTS cuenta CHAR(1) NOT NULL DEFAULT 'P' CHECK (cuenta IN ('P', 'G'));

DELETE FROM plantillas_apunte WHERE lower(nombre) = 'club';
