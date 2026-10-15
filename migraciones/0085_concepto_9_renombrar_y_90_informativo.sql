-- Concepto 9: saldo contable CC (no confundir con banco). Concepto 90: cuenta corriente informativa en remesa.

UPDATE plan_conceptos
SET nombre = 'Saldo contable (persona–centro)',
    descripcion = '9 Saldo de la cuenta CC en el libro P: contrapartida de apuntes A (no es el dinero en el banco). Agregado en 613 P y E37.',
    naturaleza = 'saldo'
WHERE cuenta = 'P' AND codigo = '9';

INSERT INTO plan_conceptos (plan_contable_id, codigo, cuenta, nombre, descripcion, naturaleza, orden)
SELECT p.id, '90', 'P', 'Cuenta corriente (banco)',
       '90 Saldo informativo de caja+banco personal en la remesa; no genera apuntes. Sirve para calcular el disponible hacia partidas 7.',
       'informacion', 270
FROM planes_contables p
WHERE p.codigo = 'H16N'
  AND NOT EXISTS (
    SELECT 1 FROM plan_conceptos pc
    WHERE pc.plan_contable_id = p.id AND pc.cuenta = 'P' AND pc.codigo = '90'
  );

UPDATE cuentas
SET nombre = 'Saldo contable (persona–centro)',
    descripcion = '9 Saldo de la cuenta CC en el libro P: contrapartida de apuntes A (no es el dinero en el banco). Agregado en 613 P y E37.'
WHERE libro = 'P' AND codigo = '9' AND persona_id IS NULL;
