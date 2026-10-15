-- Deuda de vivienda del cierre, fuera del saldo contable (CC / concepto 9).
-- Liquidarla no genera un ingreso 111.

ALTER TABLE asientos DROP CONSTRAINT IF EXISTS asientos_tipo_check;
ALTER TABLE asientos ADD CONSTRAINT asientos_tipo_check
    CHECK (tipo IN (
        'normal',
        'apertura',
        'traspaso',
        'cierre',
        'remesa',
        'periodificacion',
        'asignacion_cc',
        'liquidacion_deuda'
    ));

INSERT INTO cuentas (
    centro_id, persona_id, cuenta_fisica_id, padre_id, libro, codigo,
    nombre, descripcion, tipo, naturaleza, codigo_maestro, imputable, orden
)
SELECT DISTINCT p.centro_id, NULL::bigint, NULL::bigint, NULL::bigint, libro.libro, 'REG.DEUDA.VIV',
    'Regularización deuda de vivienda',
    'Contrapartida al saldar deuda de vivienda. No es ingreso ni gasto del 613.',
    'puente', 'deudora', 'REG-DEUDA', TRUE, 997
FROM personas p
CROSS JOIN (VALUES ('P'), ('G')) AS libro(libro)
WHERE p.centro_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM cuentas c
      WHERE c.centro_id = p.centro_id
        AND c.libro = libro.libro
        AND c.codigo = 'REG.DEUDA.VIV'
        AND c.persona_id IS NULL
  );

INSERT INTO cuentas (
    centro_id, persona_id, cuenta_fisica_id, padre_id, libro, codigo,
    nombre, descripcion, tipo, naturaleza, codigo_maestro, imputable, orden
)
SELECT p.centro_id, p.id, NULL::bigint, NULL::bigint, 'P', 'DEUDA.VIV.' || upper(p.iniciales),
    'Deuda de vivienda de ' || trim(p.nombre || ' ' || p.apellidos),
    'Vivienda imputada en el cierre, pendiente de liquidar. No es el saldo contable (CC) ni trabajo (111).',
    'personal', 'acreedora', '91', TRUE, 2
FROM personas p
WHERE p.centro_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM cuentas c
      WHERE c.centro_id = p.centro_id
        AND c.persona_id = p.id
        AND c.libro = 'P'
        AND c.codigo_maestro = '91'
  );

-- El haber del cierre que estaba en CC pasa a la deuda de esa persona.
UPDATE movimientos m
SET cuenta_id = d.id
FROM asientos a, cuentas cc, cuentas d
WHERE m.asiento_id = a.id
  AND a.tipo = 'cierre'
  AND a.libro = 'P'
  AND a.anulado_at IS NULL
  AND cc.id = m.cuenta_id
  AND cc.codigo_maestro = '9'
  AND cc.libro = 'P'
  AND d.centro_id = cc.centro_id
  AND d.persona_id = cc.persona_id
  AND d.libro = 'P'
  AND d.codigo_maestro = '91';
