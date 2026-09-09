-- Plantillas de apuntes recurrentes (p. ej. club: gasto 21 + ingreso 111).

CREATE TABLE plantillas_apunte (
    id SERIAL PRIMARY KEY,
    centro_id INTEGER NOT NULL REFERENCES centros(id) ON DELETE CASCADE,
    cuenta CHAR(1) NOT NULL CHECK (cuenta IN ('P', 'G')),
    nombre VARCHAR(120) NOT NULL,
    iniciales VARCHAR(20) NULL,
    activa BOOLEAN NOT NULL DEFAULT TRUE,
    orden INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX plantillas_apunte_nombre_uidx
    ON plantillas_apunte (centro_id, cuenta, lower(nombre), COALESCE(lower(iniciales), ''));

CREATE TABLE plantilla_lineas_apunte (
    id SERIAL PRIMARY KEY,
    plantilla_id INTEGER NOT NULL REFERENCES plantillas_apunte(id) ON DELETE CASCADE,
    orden INTEGER NOT NULL,
    origen CHAR(1) NOT NULL CHECK (origen IN ('A', 'B', 'C')),
    concepto_codigo VARCHAR(20) NOT NULL,
    observaciones TEXT NULL,
    cantidad NUMERIC(14, 2) NOT NULL,
    UNIQUE (plantilla_id, orden)
);

CREATE INDEX plantilla_lineas_plantilla_idx ON plantilla_lineas_apunte (plantilla_id);
