-- Documento XML de la remesa (urn:secretario:mensajes:1.0).
-- Nullable: las remesas ya guardadas no tienen mensaje.

ALTER TABLE remesas
    ADD COLUMN mensaje_xml TEXT NULL;
