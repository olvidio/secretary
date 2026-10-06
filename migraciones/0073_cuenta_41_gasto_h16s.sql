-- En H16s el 41 y los destinos 42–54 son gasto, no puente caja/banco (H16n).
UPDATE cuentas c
SET tipo = 'gasto', naturaleza = 'deudora', imputable = TRUE
FROM centros ce
JOIN planes_contables p ON p.id = ce.plan_contable_id
WHERE c.centro_id = ce.id
  AND p.codigo = 'H16s'
  AND c.libro = 'G'
  AND c.codigo ~ '^(41|4[2-9]|5[0-4])$'
  AND c.tipo = 'puente';
