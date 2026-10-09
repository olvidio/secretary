-- Liquidación CC al confirmar destinos 7 (ConstructorAsientoLiquidacionCc).

ALTER TABLE asientos DROP CONSTRAINT IF EXISTS asientos_tipo_check;
ALTER TABLE asientos ADD CONSTRAINT asientos_tipo_check
    CHECK (tipo IN (
        'normal',
        'apertura',
        'traspaso',
        'cierre',
        'remesa',
        'periodificacion',
        'asignacion_cc'
    ));
