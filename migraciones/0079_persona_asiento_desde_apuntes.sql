-- Los apuntes de caja o banco no llevan la persona en la cuenta, solo en el
-- asiento. Una copia antigua no guardaba esas iniciales. Si el talonario
-- (apuntes) sigue identificando una sola persona para la misma fecha, libro,
-- texto e importe, se recupera.

WITH candidatos AS (
    SELECT a.id AS asiento_id, e.centro_id, lower(btrim(ap.iniciales)) AS iniciales
    FROM asientos a
    JOIN ejercicios e ON e.id = a.ejercicio_id
    JOIN apuntes ap ON ap.cuenta = a.libro
      AND ap.fecha = a.fecha_operacion
      AND COALESCE(ap.observaciones, '') = COALESCE(a.glosa, '')
      AND round(abs(ap.cantidad::numeric) * 100) = (
          SELECT MAX(GREATEST(m.debe, m.haber))
          FROM movimientos m
          WHERE m.asiento_id = a.id
      )
    WHERE a.persona_id IS NULL
      AND a.anulado_at IS NULL
      AND ap.iniciales IS NOT NULL
      AND btrim(ap.iniciales) <> ''
    GROUP BY a.id, e.centro_id, lower(btrim(ap.iniciales))
),
unicos AS (
    SELECT asiento_id, centro_id, MIN(iniciales) AS iniciales
    FROM candidatos
    GROUP BY asiento_id, centro_id
    HAVING COUNT(*) = 1
)
UPDATE asientos a
SET persona_id = per.id
FROM unicos u
JOIN personas per ON per.centro_id = u.centro_id AND lower(per.iniciales) = u.iniciales
WHERE a.id = u.asiento_id
  AND a.persona_id IS NULL;
