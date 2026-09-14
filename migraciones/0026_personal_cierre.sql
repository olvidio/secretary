-- Fecha de cierre mensual del libro personal (nivel 1).

ALTER TABLE personas
    ADD COLUMN IF NOT EXISTS dia_cierre INT NULL
        CHECK (dia_cierre IS NULL OR (dia_cierre >= 1 AND dia_cierre <= 28));

ALTER TABLE personas
    ADD COLUMN IF NOT EXISTS cierre_dia_habil BOOLEAN NOT NULL DEFAULT FALSE;

CREATE TABLE IF NOT EXISTS personal_cierre_mes (
    persona_id BIGINT NOT NULL REFERENCES personas(id) ON DELETE CASCADE,
    anio INT NOT NULL CHECK (anio >= 1990 AND anio <= 2100),
    mes INT NOT NULL CHECK (mes >= 1 AND mes <= 12),
    fecha_cierre DATE NOT NULL,
    PRIMARY KEY (persona_id, anio, mes)
);
