-- Plantillas universales: solo movimientos; cantidad e iniciales van en la cabecera al usarlas.

DELETE FROM plantillas_apunte WHERE lower(nombre) = 'club';

ALTER TABLE plantilla_lineas_apunte ALTER COLUMN cantidad DROP NOT NULL;

DROP INDEX IF EXISTS plantillas_apunte_nombre_uidx;
ALTER TABLE plantillas_apunte DROP COLUMN IF EXISTS iniciales;
CREATE UNIQUE INDEX plantillas_apunte_nombre_uidx
    ON plantillas_apunte (centro_id, cuenta, lower(nombre));
