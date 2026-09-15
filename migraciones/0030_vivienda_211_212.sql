-- Desglose de P/21: 211 cierre automático, 212 gastos generales desde el personal.

INSERT INTO conceptos (codigo, cuenta, nombre, descripcion, naturaleza, orden)
VALUES
    ('211', 'P', 'Vivienda (cierre)', '211 Vivienda: reparto automático del cierre de mes', 'gasto', 51),
    ('212', 'P', 'Vivienda (personal)', '212 Vivienda: gastos de casa imputados desde el libro personal', 'gasto', 52)
ON CONFLICT (codigo, cuenta) DO UPDATE SET
    nombre = excluded.nombre,
    descripcion = excluded.descripcion,
    naturaleza = excluded.naturaleza,
    orden = excluded.orden;

INSERT INTO cuentas (
    centro_id, persona_id, cuenta_fisica_id, padre_id, libro, codigo,
    nombre, descripcion, tipo, naturaleza, codigo_maestro, imputable, orden
)
SELECT
    c.centro_id, NULL, NULL, NULL, 'P', v.codigo,
    v.nombre, v.descripcion, c.tipo, c.naturaleza, v.codigo, c.imputable, v.orden
FROM cuentas c
CROSS JOIN (
    VALUES
        ('211', 'Vivienda (cierre)', '211 Vivienda: reparto automático del cierre de mes', 51),
        ('212', 'Vivienda (personal)', '212 Vivienda: gastos de casa imputados desde el libro personal', 52)
) AS v(codigo, nombre, descripcion, orden)
WHERE c.libro = 'P' AND c.codigo = '21' AND c.persona_id IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM cuentas x
      WHERE x.centro_id = c.centro_id AND x.libro = 'P' AND x.codigo = v.codigo AND x.persona_id IS NULL
  );

UPDATE movimientos m
SET cuenta_id = c211.id
FROM asientos a, cuentas c21, cuentas c211
WHERE m.asiento_id = a.id
  AND c21.id = m.cuenta_id
  AND c21.libro = 'P' AND c21.codigo = '21' AND c21.persona_id IS NULL
  AND c211.centro_id = c21.centro_id
  AND c211.libro = 'P' AND c211.codigo = '211' AND c211.persona_id IS NULL
  AND a.tipo = 'cierre'
  AND a.anulado_at IS NULL;

UPDATE movimientos m
SET cuenta_id = c212.id
FROM asientos a, cuentas c21, cuentas c212
WHERE m.asiento_id = a.id
  AND c21.id = m.cuenta_id
  AND c21.libro = 'P' AND c21.codigo = '21' AND c21.persona_id IS NULL
  AND c212.centro_id = c21.centro_id
  AND c212.libro = 'P' AND c212.codigo = '212' AND c212.persona_id IS NULL
  AND a.remesa_id IS NOT NULL
  AND a.anulado_at IS NULL;
