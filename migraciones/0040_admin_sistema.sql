-- Usuario administrador de plataforma (sin menús contables).

ALTER TABLE identidades ADD COLUMN IF NOT EXISTS es_admin BOOLEAN NOT NULL DEFAULT FALSE;

ALTER TABLE rutas_acceso DROP CONSTRAINT IF EXISTS rutas_acceso_ambito_check;
ALTER TABLE rutas_acceso ADD CONSTRAINT rutas_acceso_ambito_check
    CHECK (ambito IN ('publico', 'centro', 'persona', 'persona-cuenta', 'autenticado', 'pendiente', 'admin'));
