-- Campos manuales del 613 viven en informes_613_mes (migración 0018).

ALTER TABLE configuracion DROP COLUMN IF EXISTS observaciones_613_p;
ALTER TABLE configuracion DROP COLUMN IF EXISTS observaciones_613_g;
ALTER TABLE configuracion DROP COLUMN IF EXISTS media_cocina_mes;
ALTER TABLE configuracion DROP COLUMN IF EXISTS media_cocina_acum;
ALTER TABLE configuracion DROP COLUMN IF EXISTS saldo_cc_personales;
ALTER TABLE configuracion DROP COLUMN IF EXISTS dinero_arqueo_caja;
ALTER TABLE configuracion DROP COLUMN IF EXISTS dinero_arqueo_banco;
