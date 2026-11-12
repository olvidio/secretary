-- Listados del club: saldo inicial y saldo final de caja y banco (cuadre de tesorería).
ALTER TABLE listados ADD COLUMN IF NOT EXISTS incluir_saldos BOOLEAN NOT NULL DEFAULT FALSE;
