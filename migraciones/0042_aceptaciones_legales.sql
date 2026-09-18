-- Condiciones de uso, política de privacidad y prueba de aceptación.
CREATE TABLE documentos_legales (
    tipo TEXT NOT NULL,
    version TEXT NOT NULL,
    idioma TEXT NOT NULL,
    hash_sha256 TEXT NOT NULL,
    texto TEXT NOT NULL,
    vigente_desde TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (tipo, version, idioma),
    CONSTRAINT documentos_legales_tipo_chk CHECK (tipo IN ('condiciones', 'privacidad')),
    CONSTRAINT documentos_legales_idioma_chk CHECK (idioma IN ('es', 'ca'))
);

CREATE TABLE aceptaciones_legales (
    id BIGSERIAL PRIMARY KEY,
    identidad_id BIGINT NULL REFERENCES identidades(id) ON DELETE SET NULL,
    canal TEXT NOT NULL,
    momento TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    condiciones_version TEXT NOT NULL,
    condiciones_hash TEXT NOT NULL,
    privacidad_version TEXT NOT NULL,
    privacidad_hash TEXT NOT NULL,
    texto_casilla TEXT NOT NULL,
    idioma TEXT NOT NULL DEFAULT 'es',
    ip TEXT NULL,
    user_agent TEXT NULL,
    email TEXT NULL,
    alias TEXT NULL,
    centro_id BIGINT NULL REFERENCES centros(id) ON DELETE SET NULL,
    persona_id INTEGER NULL REFERENCES personas(id) ON DELETE SET NULL,
    token_hash TEXT NULL,
    extra JSONB NULL,
    CONSTRAINT aceptaciones_legales_canal_chk CHECK (canal IN (
        'formulario_registro',
        'confirmacion_email',
        'nombres_alta',
        'nombres_import',
        'vinculo_alta'
    ))
);

CREATE INDEX aceptaciones_legales_identidad_idx ON aceptaciones_legales (identidad_id, momento);
CREATE INDEX aceptaciones_legales_centro_idx ON aceptaciones_legales (centro_id, momento);
