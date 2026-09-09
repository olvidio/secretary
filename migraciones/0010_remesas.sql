-- Fase 8 (D6): remesas versionadas nivel 1 → nivel 2.
-- UNIQUE incluye `anio` (el plan cita persona+ejercicio+mes+version): un
-- ejercicio D11 largo puede tener dos eneros de años distintos.

CREATE TABLE remesas (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    persona_id BIGINT NOT NULL REFERENCES personas(id),
    centro_id BIGINT NOT NULL REFERENCES centros(id),
    ejercicio_id BIGINT NOT NULL REFERENCES ejercicios(id),
    anio INTEGER NOT NULL,
    mes INTEGER NOT NULL CHECK (mes BETWEEN 1 AND 12),
    version INTEGER NOT NULL CHECK (version >= 1),
    estado TEXT NOT NULL CHECK (estado IN ('borrador', 'enviada', 'aceptada', 'rechazada', 'sustituida')),
    hash_contenido TEXT NOT NULL,
    enviada_at TIMESTAMPTZ NULL,
    resuelta_at TIMESTAMPTZ NULL,
    nota TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NULL,
    UNIQUE (persona_id, ejercicio_id, anio, mes, version)
);

CREATE INDEX remesas_centro_estado_idx ON remesas (centro_id, estado);
CREATE INDEX remesas_persona_mes_idx ON remesas (persona_id, ejercicio_id, anio, mes);

CREATE TABLE remesa_lineas (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    remesa_id BIGINT NOT NULL REFERENCES remesas(id) ON DELETE CASCADE,
    codigo_maestro TEXT NOT NULL,
    importe BIGINT NOT NULL,
    detalle_json JSONB NOT NULL DEFAULT '[]'::jsonb,
    UNIQUE (remesa_id, codigo_maestro)
);

CREATE TABLE remesa_solicitudes_detalle (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    remesa_linea_id BIGINT NOT NULL REFERENCES remesa_lineas(id) ON DELETE CASCADE,
    solicitada_por BIGINT NOT NULL REFERENCES identidades(id),
    solicitada_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    estado TEXT NOT NULL CHECK (estado IN ('pendiente', 'autorizada', 'denegada')),
    resuelta_at TIMESTAMPTZ NULL,
    motivo TEXT NULL
);

CREATE INDEX remesa_solicitudes_linea_idx ON remesa_solicitudes_detalle (remesa_linea_id);

ALTER TABLE asientos
    ADD CONSTRAINT asientos_remesa_id_fkey
    FOREIGN KEY (remesa_id) REFERENCES remesas(id);

CREATE INDEX asientos_remesa_id_idx ON asientos (remesa_id) WHERE remesa_id IS NOT NULL;
