-- Nº de s previsto del centro sg (613 G-D). Antes vivía en presupuesto_sg como NUM_S.
ALTER TABLE centros ADD COLUMN IF NOT EXISTS num_s INTEGER NOT NULL DEFAULT 0;

UPDATE centros c
SET num_s = GREATEST(0, (ps.previsto::numeric)::integer)
FROM presupuesto_sg ps
WHERE ps.centro_id = c.id
  AND ps.concepto_codigo = 'NUM_S'
  AND EXISTS (
      SELECT 1 FROM planes_contables p
      WHERE p.id = c.plan_contable_id AND p.codigo = 'H16s'
  );

DELETE FROM presupuesto_sg WHERE concepto_codigo = 'NUM_S';
