-- Permite PUT /api/apuntes/{id} (editar apunte desde listados).
INSERT INTO rutas_acceso (clase, metodo_php, ambito)
VALUES
    ('src\apuntes\infrastructure\http\ApunteController', 'update', 'centro')
ON CONFLICT (clase, metodo_php) DO UPDATE SET ambito = EXCLUDED.ambito;
