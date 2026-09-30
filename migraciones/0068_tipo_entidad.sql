-- Entidad: centro n, centro sg, asociación o fundación.
-- El libro personal sigue en tipo p y no aparece en la administración.

ALTER TABLE centros DROP CONSTRAINT IF EXISTS centros_tipo_check;

UPDATE centros
SET tipo = 'asociacion'
WHERE tipo = 'n'
  AND (
      codigo = 'ClubMontagut'
      OR plan_contable_id IN (SELECT id FROM planes_contables WHERE codigo = 'Club')
  );

ALTER TABLE centros ADD CONSTRAINT centros_tipo_check
    CHECK (tipo IN ('n', 'sg', 'p', 'asociacion', 'fundacion'));
