-- Tipo de centro: n (vivienda) o sg (necesidades / apostolado).
ALTER TABLE centros ADD COLUMN IF NOT EXISTS tipo TEXT NOT NULL DEFAULT 'n'
    CHECK (tipo IN ('n', 'sg'));

-- Una sola solicitud pendiente por cuenta personal.
CREATE UNIQUE INDEX IF NOT EXISTS solicitudes_vinculo_una_pendiente_por_identidad
    ON solicitudes_vinculo_centro (identidad_id) WHERE estado = 'pendiente';
