-- Banco del extracto CSV preferido por persona (pantalla /yo/banco).

ALTER TABLE personas
    ADD COLUMN IF NOT EXISTS banco_csv TEXT NULL;
