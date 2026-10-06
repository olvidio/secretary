-- Presupuesto por ejercicio (como previsión personal): P/G en presupuesto_lineas, G del sg en presupuesto_sg.

ALTER TABLE presupuesto_lineas
    ADD COLUMN IF NOT EXISTS ejercicio_id BIGINT REFERENCES ejercicios(id) ON DELETE CASCADE;

UPDATE presupuesto_lineas pl
SET ejercicio_id = sub.id
FROM (
    SELECT e.id
    FROM ejercicios e
    WHERE e.estado = 'abierto'
    ORDER BY e.id
    LIMIT 1
) AS sub
WHERE pl.ejercicio_id IS NULL;

DELETE FROM presupuesto_lineas WHERE ejercicio_id IS NULL;

ALTER TABLE presupuesto_lineas
    ALTER COLUMN ejercicio_id SET NOT NULL;

ALTER TABLE presupuesto_lineas DROP CONSTRAINT IF EXISTS presupuesto_lineas_pkey;
ALTER TABLE presupuesto_lineas
    ADD PRIMARY KEY (ejercicio_id, cuenta, concepto_codigo);

ALTER TABLE presupuesto_sg
    ADD COLUMN IF NOT EXISTS ejercicio_id BIGINT REFERENCES ejercicios(id) ON DELETE CASCADE;

UPDATE presupuesto_sg ps
SET ejercicio_id = e.id
FROM ejercicios e
WHERE ps.ejercicio_id IS NULL
  AND e.centro_id = ps.centro_id
  AND e.estado = 'abierto';

DELETE FROM presupuesto_sg WHERE ejercicio_id IS NULL;

ALTER TABLE presupuesto_sg
    ALTER COLUMN ejercicio_id SET NOT NULL;

ALTER TABLE presupuesto_sg DROP CONSTRAINT IF EXISTS presupuesto_sg_pkey;
ALTER TABLE presupuesto_sg
    ADD PRIMARY KEY (centro_id, ejercicio_id, concepto_codigo);
