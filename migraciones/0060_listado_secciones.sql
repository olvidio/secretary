-- Filtros del listado alineados con las propiedades de un informe Grisbi.

ALTER TABLE listados ADD COLUMN IF NOT EXISTS cuentas_tesoreria JSONB NOT NULL DEFAULT '[]';
ALTER TABLE listados ADD COLUMN IF NOT EXISTS incluir_traspasos BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE listados ADD COLUMN IF NOT EXISTS excluir_nulos BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE listados ADD COLUMN IF NOT EXISTS texto TEXT NULL;
