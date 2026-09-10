-- Rutas de acceso añadidas tras el despliegue inicial (AccesoSeeder las mantiene,
-- pero esta migración garantiza que existan al aplicar db:migrate).
INSERT INTO rutas_acceso (clase, metodo_php, ambito)
VALUES
    ('src\informes\infrastructure\http\InformeController', 'comprobacionesSaldos', 'centro'),
    ('src\informes\infrastructure\http\InformeController', 'guardarManual613', 'centro')
ON CONFLICT (clase, metodo_php) DO UPDATE SET ambito = EXCLUDED.ambito;
