-- Instrucciones «Deberías ingresar…» retiradas sin apunte (devuelven disponible operativo).

ALTER TABLE asignaciones_labores_lineas
    ADD COLUMN IF NOT EXISTS descartado_at TIMESTAMPTZ;
