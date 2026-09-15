-- P/21 = vivienda general (cuadra G/11). P/212 = vivienda personal (sin generales).
-- Revierte el uso erróneo de P/211 (cierre) y de P/212 para gastos imputados a generales.

UPDATE conceptos SET
    nombre = 'Vivienda',
    descripcion = '21 Vivienda: aportación a los gastos generales de la casa (cuadra con G/11)',
    orden = 50
WHERE codigo = '21' AND cuenta = 'P';

UPDATE conceptos SET
    nombre = 'Vivienda personal',
    descripcion = '212 Vivienda personal: gastos de vivienda propios, como ordinarios (no cuentan en G/11)',
    orden = 52
WHERE codigo = '212' AND cuenta = 'P';

UPDATE cuentas SET
    nombre = 'Vivienda',
    descripcion = '21 Vivienda: aportación a los gastos generales de la casa (cuadra con G/11)',
    orden = 50
WHERE libro = 'P' AND codigo = '21' AND persona_id IS NULL;

UPDATE cuentas SET
    nombre = 'Vivienda personal',
    descripcion = '212 Vivienda personal: gastos de vivienda propios, como ordinarios (no cuentan en G/11)',
    orden = 52
WHERE libro = 'P' AND codigo = '212' AND persona_id IS NULL;

UPDATE movimientos m
SET cuenta_id = c21.id
FROM asientos a, cuentas c211, cuentas c21
WHERE m.asiento_id = a.id
  AND c211.id = m.cuenta_id
  AND c211.libro = 'P' AND c211.codigo = '211' AND c211.persona_id IS NULL
  AND c21.centro_id = c211.centro_id
  AND c21.libro = 'P' AND c21.codigo = '21' AND c21.persona_id IS NULL
  AND a.anulado_at IS NULL;

UPDATE movimientos m
SET cuenta_id = c21.id
FROM asientos a, cuentas c212, cuentas c21
WHERE m.asiento_id = a.id
  AND c212.id = m.cuenta_id
  AND c212.libro = 'P' AND c212.codigo = '212' AND c212.persona_id IS NULL
  AND c21.centro_id = c212.centro_id
  AND c21.libro = 'P' AND c21.codigo = '21' AND c21.persona_id IS NULL
  AND a.remesa_id IS NOT NULL
  AND a.anulado_at IS NULL;

DELETE FROM conceptos WHERE codigo = '211' AND cuenta = 'P';

UPDATE cuentas SET activo = FALSE
WHERE libro = 'P' AND codigo = '211' AND persona_id IS NULL;
