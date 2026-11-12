-- Plan Club: la cuenta APERTURA (saldo inicial) corresponde al 120 Romanent del plan
-- oficial de asociaciones de Catalunya: excedentes acumulados de ejercicios anteriores.
-- El código interno APERTURA no cambia; no pisa un código oficial ya puesto.

UPDATE cuentas c
SET codigo_oficial = '120'
FROM centros ce
JOIN planes_contables p ON p.id = ce.plan_contable_id AND p.codigo = 'Club'
WHERE c.centro_id = ce.id AND c.persona_id IS NULL AND c.libro = 'G'
  AND c.codigo = 'APERTURA' AND c.codigo_oficial IS NULL;
