-- Flag por persona: si el gasto P/21 (vivienda) debe tener contrapartida G/11.
-- No es n/agd del centro: en un mismo libro puede haber quien aporta y quien no.
-- El valor inicial sigue el tipo de cierre del centro (vivienda = sí, necesidades = no).
ALTER TABLE personas
    ADD COLUMN vivienda_aporta_generales BOOLEAN NOT NULL DEFAULT TRUE;

UPDATE personas p
SET vivienda_aporta_generales = COALESCE((
    SELECT c.tipo_cierre = 'vivienda'
    FROM centros c
    WHERE c.id = p.centro_id
), TRUE);

INSERT INTO rutas_acceso (clase, metodo_php, ambito)
VALUES
    ('src\informes\infrastructure\http\InformeController', 'comprobaciones', 'centro')
ON CONFLICT (clase, metodo_php) DO UPDATE SET ambito = EXCLUDED.ambito;
