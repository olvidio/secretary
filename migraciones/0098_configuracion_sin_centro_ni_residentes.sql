-- La sigla es de cada centro, no de la base entera. `configuracion` es una sola
-- fila: su `centro` hacía de centro por defecto y se mezclaba entre centros. La
-- sigla ya está en `centros.codigo`.
-- El número de residentes no se guarda: el 613 G lo calcula contando personas.

ALTER TABLE configuracion DROP COLUMN IF EXISTS num_residentes;
ALTER TABLE configuracion DROP COLUMN IF EXISTS centro;
-- Columna que llegó a crear una versión previa de esta migración (solo en local).
ALTER TABLE centros DROP COLUMN IF EXISTS num_residentes;
