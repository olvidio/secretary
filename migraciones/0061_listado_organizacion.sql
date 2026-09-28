-- Columnas visibles y agrupación del listado, al estilo del informe Grisbi.

ALTER TABLE listados ADD COLUMN IF NOT EXISTS columnas JSONB NOT NULL DEFAULT '["fecha","categoria","glosa","debe","haber"]';
ALTER TABLE listados ADD COLUMN IF NOT EXISTS agrupar JSONB NOT NULL DEFAULT '[]';
ALTER TABLE listados ADD COLUMN IF NOT EXISTS separar_signo BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE listados ADD COLUMN IF NOT EXISTS separar_periodo TEXT NOT NULL DEFAULT '';
ALTER TABLE listados ADD COLUMN IF NOT EXISTS orden TEXT NOT NULL DEFAULT 'fecha';
ALTER TABLE listados ADD COLUMN IF NOT EXISTS orden_desc BOOLEAN NOT NULL DEFAULT FALSE;
