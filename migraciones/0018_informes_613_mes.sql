-- Campos manuales del 613 (observaciones, saldo c/c, cocina, dinero arqueo) por mes de cierre.
-- `enviado` reservado para registrar el envío a la dl (uso futuro).

CREATE TABLE IF NOT EXISTS informes_613_mes (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ejercicio_id BIGINT NOT NULL REFERENCES ejercicios(id) ON DELETE CASCADE,
    fecha_cierre DATE NOT NULL,
    cuenta TEXT NOT NULL CHECK (cuenta IN ('P', 'G')),
    observaciones TEXT,
    saldo_cc_personales TEXT,
    media_cocina_mes TEXT,
    media_cocina_acum TEXT,
    dinero_arqueo_caja TEXT,
    dinero_arqueo_banco TEXT,
    enviado BOOLEAN NOT NULL DEFAULT FALSE,
    enviado_en TIMESTAMPTZ NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (ejercicio_id, fecha_cierre, cuenta)
);

CREATE INDEX IF NOT EXISTS informes_613_mes_ejercicio_idx ON informes_613_mes (ejercicio_id);

-- Valores legados del singleton `configuracion` → mes de corte actual del ejercicio abierto.
INSERT INTO informes_613_mes (
    ejercicio_id, fecha_cierre, cuenta,
    observaciones, saldo_cc_personales,
    media_cocina_mes, media_cocina_acum,
    dinero_arqueo_caja, dinero_arqueo_banco
)
SELECT
    e.id, c.fecha_cierre, 'P',
    c.observaciones_613_p, c.saldo_cc_personales,
    NULL, NULL, NULL, NULL
FROM configuracion c
INNER JOIN centros ct ON ct.nombre = c.centro OR ct.codigo = c.centro
INNER JOIN ejercicios e ON e.centro_id = ct.id AND e.fecha_corte = c.fecha_cierre
WHERE c.observaciones_613_p IS NOT NULL OR c.saldo_cc_personales IS NOT NULL
ON CONFLICT (ejercicio_id, fecha_cierre, cuenta) DO NOTHING;

INSERT INTO informes_613_mes (
    ejercicio_id, fecha_cierre, cuenta,
    observaciones, saldo_cc_personales,
    media_cocina_mes, media_cocina_acum,
    dinero_arqueo_caja, dinero_arqueo_banco
)
SELECT
    e.id, c.fecha_cierre, 'G',
    c.observaciones_613_g, NULL,
    c.media_cocina_mes, c.media_cocina_acum,
    c.dinero_arqueo_caja, c.dinero_arqueo_banco
FROM configuracion c
INNER JOIN centros ct ON ct.nombre = c.centro OR ct.codigo = c.centro
INNER JOIN ejercicios e ON e.centro_id = ct.id AND e.fecha_corte = c.fecha_cierre
WHERE c.observaciones_613_g IS NOT NULL
   OR c.media_cocina_mes IS NOT NULL
   OR c.media_cocina_acum IS NOT NULL
   OR c.dinero_arqueo_caja IS NOT NULL
   OR c.dinero_arqueo_banco IS NOT NULL
ON CONFLICT (ejercicio_id, fecha_cierre, cuenta) DO NOTHING;
