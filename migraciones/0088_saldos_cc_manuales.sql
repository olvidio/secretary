-- Concepto 90 (cuenta corriente banco) anotado a mano por el secretario, para
-- personas que no envían remesa. Informativo: no genera asientos. En el 613 P
-- compite con la remesa aceptada del mismo mes; si hay las dos, manda la manual.

CREATE TABLE saldos_cc_manuales (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    centro_id BIGINT NOT NULL REFERENCES centros(id) ON DELETE CASCADE,
    ejercicio_id BIGINT NOT NULL REFERENCES ejercicios(id) ON DELETE CASCADE,
    persona_id BIGINT NOT NULL REFERENCES personas(id) ON DELETE CASCADE,
    anio INTEGER NOT NULL,
    mes INTEGER NOT NULL CHECK (mes BETWEEN 1 AND 12),
    saldo_cents BIGINT NOT NULL,
    nota TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NULL,
    UNIQUE (persona_id, ejercicio_id, anio, mes)
);

CREATE INDEX saldos_cc_manuales_centro_ej_idx ON saldos_cc_manuales (centro_id, ejercicio_id);
