-- Grupo y clase (s / cp) de los nombres del plan H16s. El libro H16n no los usa.
CREATE TABLE IF NOT EXISTS persona_sg (
    persona_id BIGINT PRIMARY KEY REFERENCES personas(id) ON DELETE CASCADE,
    grupo INTEGER NOT NULL DEFAULT 1 CHECK (grupo >= 1),
    clase TEXT NOT NULL CHECK (clase IN ('s', 'cp'))
);
