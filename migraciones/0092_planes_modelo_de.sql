-- Modelos de plan de cuentas de las associacions: un plan guardado desde la pantalla de
-- Cuentas solo sirve para copiar sus cuentas a un centro. `modelo_de` es el código del
-- plan base al que pertenece ('Club'); NULL en los planes de verdad (H16n, Club, H16s),
-- que son los que se asignan a un centro. Un modelo nunca se asigna, así que se puede borrar.
ALTER TABLE planes_contables ADD COLUMN IF NOT EXISTS modelo_de TEXT;
