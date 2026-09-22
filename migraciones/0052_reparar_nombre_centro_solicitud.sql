-- Centros de ámbito personal mal tipados como n (no deben salir en solicitudes).
UPDATE centros c
SET tipo = 'p'
WHERE c.tipo = 'n'
  AND c.codigo LIKE 'p-%'
  AND NOT EXISTS (SELECT 1 FROM identidad_centro ic WHERE ic.centro_id = c.id);

-- Alta antigua de centro: el nombre del secretario se guardó en centros.nombre.
UPDATE centros c
SET nombre = initcap(replace(replace(c.codigo, '-', ' '), '_', ' '))
FROM identidad_centro ic
INNER JOIN identidades i ON i.id = ic.identidad_id
WHERE ic.centro_id = c.id
  AND ic.rol = 'admin'
  AND c.tipo IN ('n', 'sg')
  AND c.codigo NOT LIKE 'p-%'
  AND lower(btrim(c.nombre)) = lower(btrim(i.nombre))
  AND lower(btrim(c.nombre)) <> lower(btrim(c.codigo));
