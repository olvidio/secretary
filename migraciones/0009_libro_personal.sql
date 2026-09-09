-- Fase 7 (D5): índice para el libro personal X por persona.

CREATE INDEX asientos_ejercicio_libro_persona_idx
    ON asientos (ejercicio_id, libro, persona_id)
    WHERE persona_id IS NOT NULL;

CREATE INDEX cuentas_centro_persona_libro_idx
    ON cuentas (centro_id, persona_id, libro)
    WHERE persona_id IS NOT NULL;
