-- Centro de ámbito personal (libro X sin secretario): no aparece en solicitudes ni admin operativo.
ALTER TABLE centros DROP CONSTRAINT IF EXISTS centros_tipo_check;
ALTER TABLE centros ADD CONSTRAINT centros_tipo_check CHECK (tipo IN ('n', 'sg', 'p'));
