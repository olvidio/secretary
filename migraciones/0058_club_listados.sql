-- Plan Club: listados definidos por el centro e idempotencia de la importación Grisbi.

CREATE TABLE IF NOT EXISTS listados (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    centro_id BIGINT NOT NULL REFERENCES centros(id) ON DELETE CASCADE,
    nombre TEXT NOT NULL,
    mostrar_movimientos BOOLEAN NOT NULL DEFAULT TRUE,
    mostrar_totales BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_desde DATE NULL,
    fecha_hasta DATE NULL,
    categorias JSONB NOT NULL DEFAULT '[]',
    terceros JSONB NOT NULL DEFAULT '[]',
    UNIQUE (centro_id, nombre)
);

CREATE INDEX IF NOT EXISTS listados_centro_idx ON listados (centro_id, nombre);

CREATE TABLE IF NOT EXISTS grisbi_vinculos (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ejercicio_id BIGINT NOT NULL REFERENCES ejercicios(id) ON DELETE CASCADE,
    cuenta_grisbi INTEGER NOT NULL,
    numero INTEGER NOT NULL,
    asiento_id BIGINT NULL REFERENCES asientos(id) ON DELETE SET NULL,
    UNIQUE (ejercicio_id, cuenta_grisbi, numero)
);
