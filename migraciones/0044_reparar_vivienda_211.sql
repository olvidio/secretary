-- Repara 0043: la pareja G/11 incluye cierres (origen «cierre», no «A»).

UPDATE movimientos m
SET cuenta_id = c211.id
FROM asientos a_p
JOIN movimientos mp ON mp.asiento_id = a_p.id
JOIN cuentas cp ON cp.id = mp.cuenta_id AND cp.libro = 'P' AND cp.codigo = '212' AND cp.persona_id IS NULL
JOIN cuentas c211 ON c211.centro_id = cp.centro_id AND c211.libro = 'P' AND c211.codigo = '211' AND c211.persona_id IS NULL
WHERE m.id = mp.id
  AND a_p.anulado_at IS NULL
  AND (
    a_p.tipo = 'cierre'
    OR EXISTS (
      SELECT 1
      FROM asientos a_g
      JOIN movimientos mg ON mg.asiento_id = a_g.id
      JOIN cuentas cg ON cg.id = mg.cuenta_id AND cg.libro = 'G' AND cg.codigo = '11' AND cg.persona_id IS NULL
      WHERE a_g.anulado_at IS NULL
        AND a_g.persona_id = a_p.persona_id
        AND a_g.fecha = a_p.fecha
        AND mg.haber = mp.debe
        AND mg.debe = 0
    )
  );

UPDATE apuntes SET concepto_codigo = '211'
WHERE cuenta = 'P' AND concepto_codigo = '212'
  AND (
    es_cierre = 1
    OR EXISTS (
      SELECT 1 FROM apuntes g
      WHERE g.cuenta = 'G' AND g.concepto_codigo = '11' AND g.origen = 'A'
        AND g.iniciales = apuntes.iniciales AND g.fecha = apuntes.fecha AND g.cantidad = apuntes.cantidad
    )
  );
