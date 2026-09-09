-- Fase 4c (D13): fecha de operación vs fecha de imputación.
-- asientos.fecha sigue siendo la de imputación (diario, 613, saldos de concepto).
-- fecha_operacion es cuándo se ejecutó el hecho / se movió la tesorería.

ALTER TABLE asientos ADD COLUMN IF NOT EXISTS fecha_operacion DATE;
UPDATE asientos SET fecha_operacion = fecha WHERE fecha_operacion IS NULL;
ALTER TABLE asientos ALTER COLUMN fecha_operacion SET NOT NULL;

DO $$
DECLARE
    r RECORD;
BEGIN
    FOR r IN
        SELECT conname
        FROM pg_constraint
        WHERE conrelid = 'asientos'::regclass
          AND contype = 'c'
          AND pg_get_constraintdef(oid) LIKE '%tipo%'
          AND pg_get_constraintdef(oid) LIKE '%normal%'
    LOOP
        EXECUTE format('ALTER TABLE asientos DROP CONSTRAINT %I', r.conname);
    END LOOP;
END $$;

ALTER TABLE asientos ADD CONSTRAINT asientos_tipo_check
    CHECK (tipo IN ('normal', 'apertura', 'traspaso', 'cierre', 'remesa', 'periodificacion'));
