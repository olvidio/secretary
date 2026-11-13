-- Listados del club: al agrupar, mostrar también los movimientos de cada grupo
-- (cabecera del grupo, sus líneas y el total) en vez de una sola línea por grupo.
ALTER TABLE listados ADD COLUMN IF NOT EXISTS detalle_grupos BOOLEAN NOT NULL DEFAULT FALSE;
