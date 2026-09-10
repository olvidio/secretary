-- Retira el plan v8: todos los centros pasan a H16n.

UPDATE centros
SET plan_contable_id = (SELECT id FROM planes_contables WHERE codigo = 'H16n')
WHERE plan_contable_id IS NULL
   OR plan_contable_id = (SELECT id FROM planes_contables WHERE codigo = 'v8');

DELETE FROM planes_contables WHERE codigo = 'v8';
