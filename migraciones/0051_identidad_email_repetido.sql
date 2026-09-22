-- Un correo puede repetirse en varias cuentas de secretario (centros distintos).
-- Solo puede haber una cuenta personal (libro propio) por correo (reglas en aplicación).
ALTER TABLE identidades DROP CONSTRAINT IF EXISTS identidades_email_key;
