-- Propuestas de envío a la DL (gasto P/71 desde caja).

CREATE TABLE IF NOT EXISTS envios_dl (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    centro_id BIGINT NOT NULL REFERENCES centros(id) ON DELETE CASCADE,
    ejercicio_id BIGINT NOT NULL REFERENCES ejercicios(id),
    importe_total_cents BIGINT NOT NULL CHECK (importe_total_cents > 0),
    estado TEXT NOT NULL CHECK (estado IN ('borrador', 'confirmada')),
    confirmada_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS envios_dl_lineas (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    envio_id BIGINT NOT NULL REFERENCES envios_dl(id) ON DELETE CASCADE,
    persona_id BIGINT NOT NULL REFERENCES personas(id) ON DELETE CASCADE,
    importe_cents BIGINT NOT NULL CHECK (importe_cents > 0)
);

CREATE INDEX IF NOT EXISTS envios_dl_centro_idx
    ON envios_dl (centro_id, estado, id DESC);
CREATE INDEX IF NOT EXISTS envios_dl_lineas_envio_idx
    ON envios_dl_lineas (envio_id);
