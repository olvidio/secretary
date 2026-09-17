<?php
/** @var string $idioma */
use src\shared\infrastructure\i18n\ServicioCatalogoIdioma;

$codigoIdioma = is_string($idioma ?? null) && $idioma !== '' ? $idioma : 'es';
$i18nJs = [
    'error' => _("Error"),
    'respuesta_no_json' => _("Respuesta no JSON"),
    'continuar' => _("¿Continuar?"),
    'no_se_pudo_borrar' => _("No se pudo borrar"),
    'no_se_pudo_guardar' => _("No se pudo guardar"),
    'no_se_pudo_cargar' => _("No se pudo cargar"),
    'no_se_pudo_crear' => _("No se pudo crear"),
    'no_se_pudo_desdoblar' => _("No se pudo desdoblar"),
    'no_se_pudo_enviar' => _("No se pudo enviar"),
    'no_se_pudo_responder' => _("No se pudo responder"),
    'no_se_pudo_ajustar' => _("No se pudo ajustar"),
    'no_se_pudo_proponer' => _("No se pudo proponer"),
    'no_se_pudo_confirmar' => _("No se pudo confirmar"),
    'no_se_pudo_abrir' => _("No se pudo abrir"),
    'no_se_pudo_solicitar' => _("No se pudo solicitar"),
    'no_se_pudo_aceptar' => _("No se pudo aceptar"),
    'no_se_pudo_rechazar' => _("No se pudo rechazar"),
    'no_se_pudieron_cargar_remesas' => _("No se pudieron cargar las remesas"),
    'sin_detalle' => _("Sin detalle"),
    'borrar_movimiento_confirm' => _("¿Borrar este movimiento?"),
    'quitar_fecha_cierre_confirm' => _("¿Quitar la fecha concreta y volver a la regla por defecto?"),
    'enviar_mes_centro_confirm' => _("¿Enviar este mes al centro?"),
    'borrar_apunte_confirm' => _("¿Borrar apunte?"),
    'guardar_cambios_obs_confirm' => _("Ha modificado algún campo además de observaciones. ¿Guardar los cambios?"),
    'aceptar_remesa_confirm' => _("¿Aceptar esta remesa? Sustituye los asientos de la versión aceptada anterior."),
    'rechazar_remesa_confirm' => _("¿Rechazar esta remesa? Si ya estaba aceptada, se borran sus asientos."),
    'confirmar_partidas_7' => _("¿Confirmar y anotar las partidas 7 en el libro P?"),
    'confirmar_gastos_p71' => _("¿Confirmar y anotar los gastos P/71 desde caja?"),
    'nuevo_disponible_de' => _("Nuevo disponible de"),
    'elija_categoria_plan' => _("Elija una categoría del plan"),
    'cada_parte_mayor_cero' => _("Cada parte debe ser mayor que cero"),
    'partes_deben_sumar_total' => _("Las dos partes deben sumar el total"),
    'elija_categoria_cada_parte' => _("Elija la categoría de cada parte"),
    'elija_concepto_generales' => _("Elija el concepto de generales (p. ej. Gas)"),
    'subcuenta_creada' => _("Subcuenta creada"),
    'remesa_enviada_v' => _("Remesa enviada (v"),
    'disponible_actualizado' => _("Disponible actualizado"),
    'anotado_persona_remesa' => _("Anotado. La persona verá el texto en su remesa."),
    'apuntado_movimientos_apuntes' => _("Apuntado. Los movimientos aparecen en Apuntes."),
    'propuesta_borrador' => _("Borrador #"),
    'propuesta_revisar_7' => _(". Revisar y confirmar para apuntar las 7."),
    'nadie_disponible_aplicar' => _("Nadie tiene disponible que aplicar."),
    'propuesta_borrador_envio' => _("Borrador #"),
    'propuesta_saldos_hasta' => _(". Saldos hasta "),
    'propuesta_revisar_p71' => _(". Revisar y confirmar para anotar P/71 desde caja."),
    'nadie_reparto_mes' => _("Nadie entra en el reparto de este mes."),
];
?>
<script>
window.SECRETARY_LOCALE = <?= json_encode(ServicioCatalogoIdioma::localeJs($codigoIdioma), JSON_UNESCAPED_UNICODE) ?>;
window.I18N = <?= json_encode($i18nJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
