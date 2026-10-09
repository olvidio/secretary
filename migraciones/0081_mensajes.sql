-- Bandeja de avisos por identidad (destinos 7 y solicitudes de detalle de remesa).

CREATE TABLE IF NOT EXISTS mensajes (
    id BIGSERIAL PRIMARY KEY,
    identidad_id BIGINT NOT NULL REFERENCES identidades(id) ON DELETE CASCADE,
    clave TEXT NOT NULL,
    tipo TEXT NOT NULL,
    payload JSONB NOT NULL DEFAULT '{}'::jsonb,
    leido_at TIMESTAMPTZ,
    cerrado_at TIMESTAMPTZ,
    creado_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (identidad_id, clave)
);

CREATE INDEX IF NOT EXISTS mensajes_abiertos_idx
    ON mensajes (identidad_id)
    WHERE cerrado_at IS NULL;
