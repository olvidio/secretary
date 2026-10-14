-- Ámbito del manual en cada consulta (centro-n, persona, etc.) para priorizar ampliaciones.

ALTER TABLE ayuda_consultas
    ADD COLUMN IF NOT EXISTS ambito TEXT NULL;

CREATE INDEX IF NOT EXISTS ayuda_consultas_pendientes_idx
    ON ayuda_consultas (created_at DESC)
    WHERE NOT resuelta AND origen = 'ia';
