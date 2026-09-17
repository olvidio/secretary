-- Previsión personal del 613 P: importe anual por persona y concepto,
-- para generar el presupuesto P a partir de las hojas individuales.

CREATE TABLE IF NOT EXISTS prevision_personal_lineas (
    ejercicio_id BIGINT NOT NULL REFERENCES ejercicios(id) ON DELETE CASCADE,
    persona_id BIGINT NOT NULL REFERENCES personas(id) ON DELETE CASCADE,
    concepto_codigo TEXT NOT NULL,
    previsto_cents BIGINT NOT NULL,
    PRIMARY KEY (ejercicio_id, persona_id, concepto_codigo)
);

CREATE INDEX IF NOT EXISTS prevision_personal_ejercicio_idx
    ON prevision_personal_lineas (ejercicio_id);
