-- H16s: 41–54 son gastos/destinos, nunca naturaleza transferencia del H16n.
UPDATE plan_conceptos pc
SET naturaleza = 'gasto'
FROM planes_contables p
WHERE p.id = pc.plan_contable_id
  AND p.codigo = 'H16s'
  AND pc.cuenta = 'G'
  AND pc.codigo ~ '^(41|4[2-9]|5[0-4])$'
  AND pc.naturaleza <> 'gasto';
