-- La ayuda puede contestar pidiendo más datos cuando la pregunta es vaga
-- («en arqueo sale una diferencia»). Esa respuesta se reutiliza como cualquier
-- otra, pero hay que recordar que es una petición de aclaración.

ALTER TABLE ayuda_consultas
    ADD COLUMN IF NOT EXISTS aclaracion BOOLEAN NOT NULL DEFAULT FALSE;
