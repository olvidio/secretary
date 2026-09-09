ALTER TABLE asientos ADD COLUMN IF NOT EXISTS asiento_par_id BIGINT NULL REFERENCES asientos(id);
CREATE INDEX IF NOT EXISTS asientos_par_idx ON asientos (asiento_par_id);

ALTER TABLE arqueos ADD COLUMN IF NOT EXISTS ejercicio_id BIGINT NULL REFERENCES ejercicios(id);
ALTER TABLE arqueos ADD COLUMN IF NOT EXISTS cuenta_fisica_id BIGINT NULL REFERENCES cuentas_fisicas(id);
ALTER TABLE arqueos ADD COLUMN IF NOT EXISTS total_cents BIGINT NULL;
