-- Destinos 42–54 de cada centro H16s. El 41 (Necesidades generales) es fijo del plan.
CREATE TABLE IF NOT EXISTS centro_destinos_sg (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    centro_id BIGINT NOT NULL REFERENCES centros(id) ON DELETE CASCADE,
    codigo TEXT NOT NULL,
    etiqueta TEXT NOT NULL,
    orden INTEGER NOT NULL DEFAULT 0,
    UNIQUE (centro_id, codigo)
);

CREATE INDEX IF NOT EXISTS centro_destinos_sg_centro_idx
    ON centro_destinos_sg (centro_id, orden);

INSERT INTO centro_destinos_sg (centro_id, codigo, etiqueta, orden)
SELECT cu.centro_id, cu.codigo, btrim(cu.nombre), cu.orden
FROM cuentas cu
JOIN centros c ON c.id = cu.centro_id
JOIN planes_contables p ON p.id = c.plan_contable_id
WHERE p.codigo = 'H16s'
  AND cu.persona_id IS NULL
  AND cu.libro = 'G'
  AND cu.codigo ~ '^(4[2-9]|5[0-4])$'
  AND btrim(cu.nombre) <> ''
  AND btrim(cu.nombre) <> cu.codigo
ON CONFLICT (centro_id, codigo) DO NOTHING;
