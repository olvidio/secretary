-- Base liquidable del IRPF por persona: el tope de donativos desgravables
-- es el % configurado en el centro (art. 69.1 LIRPF: 10 % con carácter general).

ALTER TABLE personas
    ADD COLUMN IF NOT EXISTS base_liquidable TEXT NULL;
