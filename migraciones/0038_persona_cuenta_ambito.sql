-- Ámbito persona-cuenta: cuenta personal sin persona activa (p. ej. /yo/centros tras el registro).
ALTER TABLE rutas_acceso DROP CONSTRAINT rutas_acceso_ambito_check;
ALTER TABLE rutas_acceso ADD CONSTRAINT rutas_acceso_ambito_check
    CHECK (ambito IN ('publico', 'centro', 'persona', 'persona-cuenta', 'autenticado', 'pendiente'));
