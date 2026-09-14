-- Consultas de la ventana de ayuda: reutilización de respuestas, límite diario
-- y registro de lo que se pregunta (las no resueltas son la lista de tareas del manual).

CREATE TABLE IF NOT EXISTS ayuda_consultas (
    id BIGSERIAL PRIMARY KEY,
    identidad_id BIGINT NULL REFERENCES identidades(id) ON DELETE SET NULL,
    huella TEXT NOT NULL,
    pregunta TEXT NOT NULL,
    respuesta TEXT NOT NULL,
    fuentes TEXT NOT NULL DEFAULT '',
    origen TEXT NOT NULL CHECK (origen IN ('ia', 'cache', 'busqueda')),
    resuelta BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- La huella ya incluye la versión del manual: al cambiar el manual dejan de encontrarse.
CREATE INDEX IF NOT EXISTS ayuda_consultas_huella_idx
    ON ayuda_consultas (huella, id DESC)
    WHERE resuelta;

CREATE INDEX IF NOT EXISTS ayuda_consultas_limite_idx
    ON ayuda_consultas (identidad_id, created_at DESC);
