-- Reparación: presupuesto guardado solo en el ejercicio planificado siguiente → copiar al abierto (anterior).

INSERT INTO presupuesto_lineas (ejercicio_id, cuenta, concepto_codigo, previsto)
SELECT e_ant.id, pl.cuenta, pl.concepto_codigo, pl.previsto
FROM presupuesto_lineas pl
JOIN ejercicios e_post ON e_post.id = pl.ejercicio_id
JOIN ejercicios e_ant ON e_ant.id = e_post.ejercicio_anterior_id AND e_ant.estado = 'abierto'
WHERE NOT EXISTS (
    SELECT 1
    FROM presupuesto_lineas pl2
    WHERE pl2.ejercicio_id = e_ant.id
      AND pl2.cuenta = pl.cuenta
      AND pl2.concepto_codigo = pl.concepto_codigo
)
ON CONFLICT (ejercicio_id, cuenta, concepto_codigo) DO NOTHING;

INSERT INTO presupuesto_sg (centro_id, ejercicio_id, concepto_codigo, previsto)
SELECT ps.centro_id, e_ant.id, ps.concepto_codigo, ps.previsto
FROM presupuesto_sg ps
JOIN ejercicios e_post ON e_post.id = ps.ejercicio_id
JOIN ejercicios e_ant ON e_ant.id = e_post.ejercicio_anterior_id AND e_ant.estado = 'abierto'
WHERE e_ant.centro_id = ps.centro_id
  AND NOT EXISTS (
    SELECT 1
    FROM presupuesto_sg ps2
    WHERE ps2.centro_id = ps.centro_id
      AND ps2.ejercicio_id = e_ant.id
      AND ps2.concepto_codigo = ps.concepto_codigo
)
ON CONFLICT (centro_id, ejercicio_id, concepto_codigo) DO NOTHING;
