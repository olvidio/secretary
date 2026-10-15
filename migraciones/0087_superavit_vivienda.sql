-- Superávit de vivienda: exceso ingresado sobre la cuota, para saldar la deuda del cierre
-- sin un ingreso nuevo y sin otro P/211.

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
        'liquidacion_deuda',
        'superavit_vivienda'
    ));

INSERT INTO cuentas (
    centro_id, persona_id, cuenta_fisica_id, padre_id, libro, codigo,
    nombre, descripcion, tipo, naturaleza, codigo_maestro, imputable, orden
)
SELECT DISTINCT p.centro_id, NULL::bigint, NULL::bigint, NULL::bigint, 'G', 'SUPERAVIT.VIV',
    'Superávit de vivienda (personas)',
    'Contrapartida en G al saldar deuda de vivienda con el exceso ya ingresado. No es ingreso del 613.',
    'puente', 'deudora', 'SUPERAVIT-VIV', TRUE, 998
FROM personas p
WHERE p.centro_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM cuentas c
      WHERE c.centro_id = p.centro_id
        AND c.libro = 'G'
        AND c.codigo = 'SUPERAVIT.VIV'
        AND c.persona_id IS NULL
  );

INSERT INTO cuentas (
    centro_id, persona_id, cuenta_fisica_id, padre_id, libro, codigo,
    nombre, descripcion, tipo, naturaleza, codigo_maestro, imputable, orden
)
SELECT p.centro_id, p.id, NULL::bigint, NULL::bigint, 'P', 'SUPERAVIT.VIV.' || upper(p.iniciales),
    'Superávit de vivienda de ' || trim(p.nombre || ' ' || p.apellidos),
    'Exceso de vivienda ya ingresado sobre la cuota. Sirve para saldar la deuda del cierre, sin el saldo contable ni un 211 nuevo.',
    'puente', 'acreedora', '92', TRUE, 3
FROM personas p
WHERE p.centro_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM cuentas c
      WHERE c.centro_id = p.centro_id
        AND c.persona_id = p.id
        AND c.libro = 'P'
        AND c.codigo_maestro = '92'
  );
