-- Fase 6 (D7): identidades, TOTP, recuperación, vínculos y tabla de autorización.

CREATE TABLE identidades (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    alias TEXT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    nombre TEXT NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    intentos_fallidos INTEGER NOT NULL DEFAULT 0,
    bloqueado_hasta TIMESTAMPTZ NULL,
    ultimo_acceso TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE identidad_totp (
    identidad_id BIGINT PRIMARY KEY REFERENCES identidades(id) ON DELETE CASCADE,
    secret_cifrado TEXT NOT NULL,
    confirmado_at TIMESTAMPTZ NULL
);

CREATE TABLE identidad_recovery (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    identidad_id BIGINT NOT NULL REFERENCES identidades(id) ON DELETE CASCADE,
    code_hash TEXT NOT NULL,
    usado_at TIMESTAMPTZ NULL
);

CREATE INDEX identidad_recovery_identidad_idx ON identidad_recovery (identidad_id);

CREATE TABLE identidad_centro (
    identidad_id BIGINT NOT NULL REFERENCES identidades(id) ON DELETE CASCADE,
    centro_id BIGINT NOT NULL REFERENCES centros(id),
    rol TEXT NOT NULL DEFAULT 'operador',
    PRIMARY KEY (identidad_id, centro_id)
);

CREATE TABLE identidad_persona (
    identidad_id BIGINT NOT NULL REFERENCES identidades(id) ON DELETE CASCADE,
    persona_id BIGINT NOT NULL REFERENCES personas(id),
    PRIMARY KEY (identidad_id, persona_id)
);

CREATE TABLE rutas_acceso (
    clase TEXT NOT NULL,
    metodo_php TEXT NOT NULL,
    ambito TEXT NOT NULL CHECK (ambito IN ('publico', 'centro', 'persona', 'autenticado', 'pendiente')),
    PRIMARY KEY (clase, metodo_php)
);
