-- Solicitud de una identidad (nivel 1) para unirse a un centro; aprobación por el secretario.

ALTER TABLE identidad_persona
    ADD COLUMN IF NOT EXISTS anio INTEGER NULL;

CREATE TABLE IF NOT EXISTS solicitudes_vinculo_centro (
    id BIGSERIAL PRIMARY KEY,
    identidad_id BIGINT NOT NULL REFERENCES identidades(id) ON DELETE CASCADE,
    centro_id BIGINT NOT NULL REFERENCES centros(id) ON DELETE CASCADE,
    anio INTEGER NOT NULL,
    estado TEXT NOT NULL DEFAULT 'pendiente'
        CHECK (estado IN ('pendiente', 'aprobada', 'rechazada')),
    persona_id BIGINT NULL REFERENCES personas(id) ON DELETE SET NULL,
    mensaje TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    resolved_at TIMESTAMPTZ NULL,
    resolved_by BIGINT NULL REFERENCES identidades(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS solicitudes_vinculo_pendiente_uidx
    ON solicitudes_vinculo_centro (identidad_id, centro_id, anio)
    WHERE estado = 'pendiente';

CREATE INDEX IF NOT EXISTS solicitudes_vinculo_centro_estado_idx
    ON solicitudes_vinculo_centro (centro_id, estado);
