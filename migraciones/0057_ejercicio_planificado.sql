-- Ejercicio todavía no abierto, solo para guardar la previsión del período siguiente
-- mientras el ejercicio de trabajo sigue abierto (enero–diciembre, curso, u otro).

ALTER TABLE ejercicios DROP CONSTRAINT IF EXISTS ejercicios_estado_check;
ALTER TABLE ejercicios ADD CONSTRAINT ejercicios_estado_check
    CHECK (estado IN ('abierto', 'cerrado', 'planificado'));
