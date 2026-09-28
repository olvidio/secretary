-- El listado recuerda el ejercicio (actual, anterior u otro), no un año fijo.

ALTER TABLE listados ADD COLUMN IF NOT EXISTS periodo TEXT NOT NULL DEFAULT 'actual';

UPDATE listados SET periodo = 'otro'
 WHERE fecha_desde IS NOT NULL OR fecha_hasta IS NOT NULL;

ALTER TABLE listados DROP CONSTRAINT IF EXISTS listados_periodo_chk;
ALTER TABLE listados ADD CONSTRAINT listados_periodo_chk
    CHECK (periodo IN ('actual', 'anterior', 'otro'));
