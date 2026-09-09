-- Fase 3 OLA 1 (D1, D4): libro diario de partida doble. Aditivo respecto a apuntes.

CREATE TABLE asientos (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ejercicio_id BIGINT NOT NULL REFERENCES ejercicios(id),
    libro TEXT NOT NULL CHECK (libro IN ('P', 'G', 'X')),
    numero INTEGER NOT NULL,
    fecha DATE NOT NULL,
    glosa TEXT NULL,
    tipo TEXT NOT NULL CHECK (tipo IN ('normal', 'apertura', 'traspaso', 'cierre', 'remesa')),
    origen TEXT NOT NULL CHECK (origen IN ('manual', 'import', 'cierre', 'remesa')),
    persona_id BIGINT NULL REFERENCES personas(id),
    remesa_id BIGINT NULL,
    creado_por BIGINT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NULL,
    anulado_at TIMESTAMPTZ NULL,
    UNIQUE (ejercicio_id, libro, numero)
);

CREATE TABLE movimientos (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    asiento_id BIGINT NOT NULL REFERENCES asientos(id) ON DELETE CASCADE,
    orden INTEGER NOT NULL,
    cuenta_id BIGINT NOT NULL REFERENCES cuentas(id),
    persona_id BIGINT NULL REFERENCES personas(id),
    debe BIGINT NOT NULL DEFAULT 0,
    haber BIGINT NOT NULL DEFAULT 0,
    CHECK (debe >= 0 AND haber >= 0),
    CHECK ((debe = 0) <> (haber = 0))
);

CREATE INDEX movimientos_asiento_idx ON movimientos (asiento_id);
CREATE INDEX movimientos_cuenta_idx ON movimientos (cuenta_id);
CREATE INDEX asientos_ejercicio_idx ON asientos (ejercicio_id, libro, fecha);
