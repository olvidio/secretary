-- Entradas periódicas (plan H16s / centro sg): definición y registro de ejecuciones.

CREATE TABLE entradas_periodicas (
    id SERIAL PRIMARY KEY,
    centro_id INTEGER NOT NULL REFERENCES centros(id) ON DELETE CASCADE,
    iniciales VARCHAR(20) NOT NULL,
    concepto_codigo VARCHAR(20) NOT NULL,
    observaciones TEXT NULL,
    cantidad NUMERIC(14, 2) NOT NULL CHECK (cantidad > 0),
    periodicidad VARCHAR(20) NOT NULL CHECK (periodicidad IN ('mensual', 'trimestral', 'anual')),
    fecha_ancla DATE NOT NULL,
    activa BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX entradas_periodicas_centro_idx ON entradas_periodicas (centro_id);

CREATE TABLE entradas_periodicas_ejecucion (
    id SERIAL PRIMARY KEY,
    entrada_periodica_id INTEGER NOT NULL REFERENCES entradas_periodicas(id) ON DELETE CASCADE,
    fecha DATE NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (entrada_periodica_id, fecha)
);

CREATE INDEX entradas_periodicas_ejecucion_entrada_idx
    ON entradas_periodicas_ejecucion (entrada_periodica_id);
