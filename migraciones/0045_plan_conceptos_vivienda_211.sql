-- P/211 y P/212 en plan_conceptos (plan contable general). Los desplegables leen esta tabla, no `conceptos`.

INSERT INTO plan_conceptos (plan_contable_id, codigo, cuenta, nombre, descripcion, naturaleza, orden)
SELECT p.id, v.codigo, 'P', v.nombre, v.descripcion, 'gasto', v.orden
FROM planes_contables p
CROSS JOIN (
    VALUES
        ('211', 'Vivienda general', '211 Vivienda general: cuadra con G/11 (cierre automático o imputación puntual a generales)', 51),
        ('212', 'Vivienda personal', '212 Vivienda personal: gastos de casa propios, sin contrapartida G/11', 52)
) AS v(codigo, nombre, descripcion, orden)
WHERE NOT EXISTS (
    SELECT 1 FROM plan_conceptos pc
    WHERE pc.plan_contable_id = p.id AND pc.cuenta = 'P' AND pc.codigo = v.codigo
);

UPDATE plan_conceptos pc
SET
    nombre = v.nombre,
    descripcion = v.descripcion,
    naturaleza = 'gasto',
    orden = v.orden
FROM (
    VALUES
        ('211', 'Vivienda general', '211 Vivienda general: cuadra con G/11 (cierre automático o imputación puntual a generales)', 51),
        ('212', 'Vivienda personal', '212 Vivienda personal: gastos de casa propios, sin contrapartida G/11', 52)
) AS v(codigo, nombre, descripcion, orden)
WHERE pc.cuenta = 'P' AND pc.codigo = v.codigo;

DELETE FROM plan_conceptos WHERE cuenta = 'P' AND codigo = '21';
