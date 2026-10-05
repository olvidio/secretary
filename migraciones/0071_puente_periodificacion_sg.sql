-- Centros H16s y Club: un solo libro G pero la entrada admite fecha de imputación (D13).
INSERT INTO cuentas (
    centro_id, persona_id, cuenta_fisica_id, padre_id, libro, codigo, nombre, descripcion,
    tipo, naturaleza, codigo_maestro, imputable, orden
)
SELECT c.id, NULL, NULL, NULL, 'G', 'PUENTE.PERIODIFICACION',
       'Periodificación · G',
       'Contrapartida de imputación a período distinto de la tesorería (D13)',
       'puente', 'deudora', 'PERIODIFICACION', TRUE, 0
FROM centros c
INNER JOIN planes_contables p ON p.id = c.plan_contable_id
WHERE p.codigo IN ('H16s', 'Club')
  AND NOT EXISTS (
      SELECT 1 FROM cuentas cu
      WHERE cu.centro_id = c.id AND cu.persona_id IS NULL AND cu.libro = 'G'
        AND cu.codigo = 'PUENTE.PERIODIFICACION'
  );
