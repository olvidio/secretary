-- Mejora continua de la ayuda:
--   * ayuda_valoraciones: «¿Le ha servido?» de cada usuario sobre una respuesta
--     (un «no» apunta la pregunta como pendiente y la respuesta deja de reutilizarse).
--   * ayuda_consultas.repaso: las pendientes ya repasadas (cubiertas en el manual
--     o descartadas) dejan de salir en la exportación.

ALTER TABLE ayuda_consultas
    ADD COLUMN IF NOT EXISTS repaso TEXT NOT NULL DEFAULT 'nueva'
        CHECK (repaso IN ('nueva', 'cubierta', 'descartada')),
    ADD COLUMN IF NOT EXISTS repasada_at TIMESTAMPTZ NULL;

CREATE TABLE IF NOT EXISTS ayuda_valoraciones (
    id BIGSERIAL PRIMARY KEY,
    consulta_id BIGINT NOT NULL REFERENCES ayuda_consultas(id) ON DELETE CASCADE,
    identidad_id BIGINT NULL REFERENCES identidades(id) ON DELETE SET NULL,
    util BOOLEAN NOT NULL,
    comentario TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Una valoración por persona y respuesta: si cambia de opinión, se sustituye.
CREATE UNIQUE INDEX IF NOT EXISTS ayuda_valoraciones_persona_idx
    ON ayuda_valoraciones (consulta_id, identidad_id)
    WHERE identidad_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS ayuda_valoraciones_negativas_idx
    ON ayuda_valoraciones (consulta_id)
    WHERE NOT util;
