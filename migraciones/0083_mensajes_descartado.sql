-- Permite ocultar un aviso desde Mensajes sin borrar el registro (no reaparece si el aviso no cambia).

ALTER TABLE mensajes
    ADD COLUMN IF NOT EXISTS descartado_manual BOOLEAN NOT NULL DEFAULT FALSE;
