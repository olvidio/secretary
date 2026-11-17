-- Un movimiento importado de un extracto del banco cuyo apunte se borra conserva
-- su fila (y su huella) marcada como descartada: no vuelve a entrar al
-- reimportar el extracto ni sale en pendientes. Desde la pantalla del banco se
-- puede recuperar.

-- Banco del centro (Entrada G).
ALTER TABLE banco_centro_import_filas ADD COLUMN IF NOT EXISTS descartada_at timestamptz NULL;

-- Banco personal (Mis cuentas → Banco): la fila se queda sin asiento al descartarla.
ALTER TABLE banco_import_filas ALTER COLUMN asiento_id DROP NOT NULL;
ALTER TABLE banco_import_filas ADD COLUMN IF NOT EXISTS descartada_at timestamptz NULL;
