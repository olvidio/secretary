-- La copia de centro recreaba los asientos sin persona_id. Si la cuenta del
-- movimiento pertenece a una sola persona, se recupera el vínculo. También se
-- reenlazan pares de periodificación que quedaron sueltos y se identifican
-- sin ambigüedad (mismo ejercicio, libro, importe del puente y glosa).

UPDATE movimientos m
SET persona_id = c.persona_id
FROM cuentas c
WHERE c.id = m.cuenta_id
  AND m.persona_id IS NULL
  AND c.persona_id IS NOT NULL;

UPDATE asientos a
SET persona_id = sub.persona_id
FROM (
    SELECT m.asiento_id, MIN(c.persona_id) AS persona_id
    FROM movimientos m
    JOIN cuentas c ON c.id = m.cuenta_id
    WHERE c.persona_id IS NOT NULL
    GROUP BY m.asiento_id
    HAVING COUNT(DISTINCT c.persona_id) = 1
) sub
WHERE a.id = sub.asiento_id
  AND a.persona_id IS NULL;

WITH n AS (
    SELECT a.id, a.ejercicio_id, a.libro, a.fecha_operacion, COALESCE(a.glosa, '') AS glosa,
           m.haber AS puente
    FROM asientos a
    JOIN movimientos m ON m.asiento_id = a.id
    JOIN cuentas c ON c.id = m.cuenta_id AND c.codigo = 'PUENTE.PERIODIFICACION'
    WHERE a.tipo = 'normal'
      AND a.fecha <> a.fecha_operacion
      AND a.anulado_at IS NULL
      AND a.asiento_par_id IS NULL
),
p AS (
    SELECT a.id, a.ejercicio_id, a.libro, a.fecha, COALESCE(a.glosa, '') AS glosa,
           m.debe AS puente
    FROM asientos a
    JOIN movimientos m ON m.asiento_id = a.id
    JOIN cuentas c ON c.id = m.cuenta_id AND c.codigo = 'PUENTE.PERIODIFICACION'
    WHERE a.tipo = 'periodificacion'
      AND a.anulado_at IS NULL
      AND a.asiento_par_id IS NULL
),
pares AS (
    SELECT n.id AS normal_id, p.id AS peri_id
    FROM n
    JOIN p ON p.ejercicio_id = n.ejercicio_id
      AND p.libro = n.libro
      AND p.fecha = n.fecha_operacion
      AND p.glosa = n.glosa
      AND p.puente = n.puente
),
unicos AS (
    SELECT normal_id, peri_id
    FROM pares
    WHERE normal_id IN (SELECT normal_id FROM pares GROUP BY normal_id HAVING COUNT(*) = 1)
      AND peri_id IN (SELECT peri_id FROM pares GROUP BY peri_id HAVING COUNT(*) = 1)
)
UPDATE asientos a
SET asiento_par_id = u.peri_id
FROM unicos u
WHERE a.id = u.normal_id
  AND a.asiento_par_id IS NULL;

UPDATE asientos peri
SET asiento_par_id = normal.id
FROM asientos normal
WHERE normal.asiento_par_id = peri.id
  AND peri.asiento_par_id IS NULL
  AND peri.tipo = 'periodificacion'
  AND normal.tipo = 'normal';
