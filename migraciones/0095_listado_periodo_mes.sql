-- Listados: el periodo puede ser también el mes natural actual o el anterior.
ALTER TABLE listados DROP CONSTRAINT IF EXISTS listados_periodo_chk;
ALTER TABLE listados ADD CONSTRAINT listados_periodo_chk
    CHECK (periodo IN ('actual', 'anterior', 'mes_actual', 'mes_anterior', 'otro'));
