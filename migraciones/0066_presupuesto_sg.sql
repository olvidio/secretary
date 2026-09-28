-- Presupuesto del centro sg. El de la casa sigue en presupuesto_lineas, que es único.
CREATE TABLE IF NOT EXISTS presupuesto_sg (
    centro_id BIGINT NOT NULL REFERENCES centros(id) ON DELETE CASCADE,
    concepto_codigo TEXT NOT NULL,
    previsto TEXT NOT NULL DEFAULT '0',
    PRIMARY KEY (centro_id, concepto_codigo)
);

-- Cifras del libro de sgMontagut (hoja Presupuesto). NUM_S es el nº de s previsto.
INSERT INTO presupuesto_sg (centro_id, concepto_codigo, previsto)
SELECT c.id, v.codigo, v.previsto
FROM centros c
CROSS JOIN (VALUES
    ('11', '19500.00'),
    ('12', '6800.00'),
    ('13', '720.00'),
    ('21', '300.00'),
    ('22', '300.00'),
    ('26', '100.00'),
    ('27', '1600.00'),
    ('28', '3000.00'),
    ('41', '6500.00'),
    ('42', '6000.00'),
    ('43', '8500.00'),
    ('45', '720.00'),
    ('NUM_S', '25')
) AS v(codigo, previsto)
WHERE c.codigo = 'sgMontagut'
ON CONFLICT (centro_id, concepto_codigo) DO NOTHING;
