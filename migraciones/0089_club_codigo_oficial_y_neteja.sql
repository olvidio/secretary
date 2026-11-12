-- Plan Club: código del plan oficial de asociaciones de Catalunya (Decret 259/2008)
-- junto a cada cuenta propia, y categoría 62 Neteja (sous, seguretat social, gestoria).
-- La numeración del club es propia (por finalidad); el código oficial permite
-- trasladarla al cuadro de cuentas oficial sin renumerar.

ALTER TABLE cuentas ADD COLUMN IF NOT EXISTS codigo_oficial TEXT;

-- Plantilla del plan Club.
INSERT INTO plan_conceptos (plan_contable_id, codigo, cuenta, nombre, descripcion, naturaleza, orden)
SELECT p.id, v.codigo, 'G', v.nombre, v.nombre, v.naturaleza, v.orden
FROM planes_contables p
CROSS JOIN (VALUES
    ('62', 'Neteja', 'grupo-gasto', 27),
    ('62.1', 'Sous', 'gasto', 28),
    ('62.2', 'Seguretat Social', 'gasto', 28),
    ('62.3', 'Gestoria', 'gasto', 29)
) AS v(codigo, nombre, naturaleza, orden)
WHERE p.codigo = 'Club'
ON CONFLICT (plan_contable_id, cuenta, codigo) DO NOTHING;

-- Cuentas de los centros que ya usan el plan Club.
INSERT INTO cuentas (centro_id, persona_id, libro, codigo, nombre, descripcion, tipo, naturaleza,
                     codigo_maestro, imputable, orden)
SELECT ce.id, NULL, 'G', v.codigo, v.nombre, v.nombre, 'gasto', 'deudora', v.codigo, v.imputable, v.orden
FROM centros ce
JOIN planes_contables p ON p.id = ce.plan_contable_id AND p.codigo = 'Club'
CROSS JOIN (VALUES
    ('62', 'Neteja', FALSE, 27),
    ('62.1', 'Sous', TRUE, 28),
    ('62.2', 'Seguretat Social', TRUE, 28),
    ('62.3', 'Gestoria', TRUE, 29)
) AS v(codigo, nombre, imputable, orden)
WHERE NOT EXISTS (
    SELECT 1 FROM cuentas c
    WHERE c.centro_id = ce.id AND c.persona_id IS NULL AND c.libro = 'G' AND c.codigo = v.codigo
);

-- Código oficial propuesto para las cuentas de la plantilla; no pisa uno ya puesto.
UPDATE cuentas c
SET codigo_oficial = v.oficial
FROM centros ce
JOIN planes_contables p ON p.id = ce.plan_contable_id AND p.codigo = 'Club'
CROSS JOIN (VALUES
    ('60.1', '600'), ('60.2', '623'),
    ('61.1', '628'), ('61.2', '622'), ('61.3', '629'), ('61.4', '625'), ('61.5', '626'), ('61.6', '631'),
    ('62.1', '640'), ('62.2', '642'), ('62.3', '623'),
    ('70.1', '721'), ('70.2', '728'),
    ('80.1', '629'), ('80.2', '629'), ('80.3', '629'), ('80.4', '629'), ('80.5', '629'), ('80.6', '629'),
    ('90.1', '759'), ('90.2', '628'), ('90.3', '622'), ('90.4', '625'), ('90.5', '629')
) AS v(codigo, oficial)
WHERE c.centro_id = ce.id AND c.persona_id IS NULL AND c.libro = 'G'
  AND c.codigo = v.codigo AND c.codigo_oficial IS NULL;
