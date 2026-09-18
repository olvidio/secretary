-- P/211 vivienda general (cuadra G/11), P/212 vivienda personal. Retira P/21 activo.

INSERT INTO conceptos (codigo, cuenta, nombre, descripcion, naturaleza, orden)
VALUES
    ('211', 'P', 'Vivienda general', '211 Vivienda general: cuadra con G/11 (cierre automático o imputación puntual a generales)', 'gasto', 51),
    ('212', 'P', 'Vivienda personal', '212 Vivienda personal: gastos de casa propios, sin contrapartida G/11', 'gasto', 52)
ON CONFLICT (codigo, cuenta) DO UPDATE SET
    nombre = excluded.nombre,
    descripcion = excluded.descripcion,
    naturaleza = excluded.naturaleza,
    orden = excluded.orden;

INSERT INTO cuentas (
    centro_id, persona_id, cuenta_fisica_id, padre_id, libro, codigo,
    nombre, descripcion, tipo, naturaleza, codigo_maestro, imputable, orden, activo
)
SELECT
    c.centro_id, NULL, NULL, NULL, 'P', v.codigo,
    v.nombre, v.descripcion, c.tipo, c.naturaleza, v.codigo, c.imputable, v.orden, TRUE
FROM cuentas c
CROSS JOIN (
    VALUES
        ('211', 'Vivienda general', '211 Vivienda general: cuadra con G/11 (cierre automático o imputación puntual a generales)', 51),
        ('212', 'Vivienda personal', '212 Vivienda personal: gastos de casa propios, sin contrapartida G/11', 52)
) AS v(codigo, nombre, descripcion, orden)
WHERE c.libro = 'P' AND c.codigo = '21' AND c.persona_id IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM cuentas x
      WHERE x.centro_id = c.centro_id AND x.libro = 'P' AND x.codigo = v.codigo AND x.persona_id IS NULL
  );

UPDATE cuentas SET activo = TRUE, nombre = 'Vivienda personal',
    descripcion = '212 Vivienda personal: gastos de casa propios, sin contrapartida G/11', orden = 52
WHERE libro = 'P' AND codigo = '212' AND persona_id IS NULL;

-- P/21 con pareja G/11 (misma fecha, persona, importe, origen A) → 211
UPDATE movimientos m
SET cuenta_id = c211.id
FROM asientos a_p
JOIN movimientos mp ON mp.asiento_id = a_p.id
JOIN cuentas cp ON cp.id = mp.cuenta_id AND cp.libro = 'P' AND cp.codigo = '21' AND cp.persona_id IS NULL
JOIN cuentas c211 ON c211.centro_id = cp.centro_id AND c211.libro = 'P' AND c211.codigo = '211' AND c211.persona_id IS NULL
WHERE m.id = mp.id
  AND a_p.anulado_at IS NULL
  AND a_p.persona_id IS NOT NULL
  AND EXISTS (
      SELECT 1
      FROM asientos a_g
      JOIN movimientos mg ON mg.asiento_id = a_g.id
      JOIN cuentas cg ON cg.id = mg.cuenta_id AND cg.libro = 'G' AND cg.codigo = '11' AND cg.persona_id IS NULL
      WHERE a_g.anulado_at IS NULL
        AND a_g.persona_id = a_p.persona_id
        AND a_g.fecha = a_p.fecha
        AND a_g.origen = 'A'
        AND mg.haber = mp.debe
        AND mg.debe = 0
  );

-- Resto de P/21 → 212
UPDATE movimientos m
SET cuenta_id = c212.id
FROM asientos a_p
JOIN movimientos mp ON mp.asiento_id = a_p.id
JOIN cuentas cp ON cp.id = mp.cuenta_id AND cp.libro = 'P' AND cp.codigo = '21' AND cp.persona_id IS NULL
JOIN cuentas c212 ON c212.centro_id = cp.centro_id AND c212.libro = 'P' AND c212.codigo = '212' AND c212.persona_id IS NULL
WHERE m.id = mp.id
  AND a_p.anulado_at IS NULL;

UPDATE apuntes SET concepto_codigo = '211' WHERE cuenta = 'P' AND concepto_codigo = '21'
  AND EXISTS (
      SELECT 1 FROM apuntes g
      WHERE g.cuenta = 'G' AND g.concepto_codigo = '11' AND g.origen = 'A'
        AND g.iniciales = apuntes.iniciales AND g.fecha = apuntes.fecha AND g.cantidad = apuntes.cantidad
  );

UPDATE apuntes SET concepto_codigo = '212' WHERE cuenta = 'P' AND concepto_codigo = '21';

UPDATE presupuesto_lineas SET concepto_codigo = '211' WHERE cuenta = 'P' AND concepto_codigo = '21';

UPDATE cuentas SET activo = FALSE
WHERE libro = 'P' AND codigo = '21' AND persona_id IS NULL;

DELETE FROM conceptos WHERE codigo = '21' AND cuenta = 'P';
