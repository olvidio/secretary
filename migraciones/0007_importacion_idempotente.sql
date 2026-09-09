-- Fase 5 (D8): registro de ejecuciones y clave natural por fila de Excel.

CREATE TABLE import_ejecuciones (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    centro_id BIGINT NOT NULL REFERENCES centros(id),
    ejercicio_id BIGINT NOT NULL REFERENCES ejercicios(id),
    fichero TEXT NOT NULL,
    sha256 TEXT NOT NULL,
    dry_run BOOLEAN NOT NULL DEFAULT FALSE,
    altas INTEGER NOT NULL DEFAULT 0,
    cambios INTEGER NOT NULL DEFAULT 0,
    bajas INTEGER NOT NULL DEFAULT 0,
    omitidos INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX import_ejecuciones_ejercicio_idx ON import_ejecuciones (ejercicio_id, created_at DESC);

CREATE TABLE import_filas (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ejercicio_id BIGINT NOT NULL REFERENCES ejercicios(id) ON DELETE CASCADE,
    hoja TEXT NOT NULL,
    fila INTEGER NOT NULL,
    hash_contenido TEXT NOT NULL,
    asiento_id BIGINT NULL REFERENCES asientos(id) ON DELETE SET NULL,
    UNIQUE (ejercicio_id, hoja, fila)
);

CREATE INDEX import_filas_asiento_idx ON import_filas (asiento_id);
CREATE INDEX import_filas_ejercicio_hoja_idx ON import_filas (ejercicio_id, hoja);
