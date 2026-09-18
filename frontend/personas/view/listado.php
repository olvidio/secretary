<h1><?= _("Nombres") ?></h1>
<p class="muted"><?= _("El correo convierte a esa persona en usuario del libro personal de este centro. Si el correo es nuevo, se muestra una contraseña inicial para comunicársela una vez. «Vivienda aporta a generales» indica si entra en el cierre automático (P/211, típico de n); quien no aporta puede igualmente imputar a generales puntualmente (P/211 y G/11). P/212 (vivienda personal) es un gasto propio, sin G/11. La exención de meses es para quien llega o se va a mitad de año (no se le pide movimiento ni entra en el cierre esos meses).") ?></p>
<form id="form-persona" class="grid-form">
    <input type="hidden" name="id">
    <label><?= _("Nombre") ?> <input name="nombre" required></label>
    <label><?= _("Apellidos") ?> <input name="apellidos"></label>
    <label><?= _("Iniciales") ?> <input name="iniciales" required maxlength="6"></label>
    <label><?= _("Correo") ?> <input name="email" type="email" autocomplete="off"></label>
    <label><?= _("No paga desde mes") ?> <input name="mes_exento_inicio" type="number" min="1" max="12"></label>
    <label><?= _("No paga hasta mes") ?> <input name="mes_exento_fin" type="number" min="1" max="12"></label>
    <label><?= _("Otro intervalo desde") ?> <input name="mes_exento2_inicio" type="number" min="1" max="12"></label>
    <label><?= _("Otro intervalo hasta") ?> <input name="mes_exento2_fin" type="number" min="1" max="12"></label>
    <label><?= _("Importe fijo vivienda") ?> <input name="importe_vivienda_fijo"></label>
    <label><?= _("Vivienda aporta a generales") ?>
        <select name="vivienda_aporta_generales">
            <option value="1"><?= _("Sí — entra en el cierre automático (P/211)") ?></option>
            <option value="0"><?= _("No — vivienda solo personal") ?></option>
        </select>
    </label>
    <label><?= _("Puede desgravar donativos") ?>
        <select name="puede_desgravar">
            <option value="1"><?= _("Sí") ?></option>
            <option value="0"><?= _("No — las 7 van a partidas que no desgravan") ?></option>
        </select>
    </label>
    <label><?= _("Base liquidable IRPF") ?>
        <input name="base_liquidable" inputmode="decimal">
        <span class="muted"><?= _("Si se deja vacío, se usa el 111 de la previsión personal; si aún no está guardada, el ingreso 111 proyectado a fin de año.") ?></span>
    </label>
    <label class="inline casilla-legal">
        <input type="checkbox" name="asumo_responsable_nombres" value="1" id="asumo-responsable-nombres">
        <span><?= htmlspecialchars((string) ($textoAsumoNombres ?? _('Declaro que el centro, y yo como secretario, somos responsables del tratamiento de los datos de las personas que doy de alta, importo o vinculo. Secretario es un programa gratuito que solo aloja la información. Tengo base legal para ese tratamiento.')), ENT_QUOTES) ?></span>
    </label>
    <button type="submit"><?= _("Guardar") ?></button>
    <button type="button" id="btn-nuevo"><?= _("Nuevo") ?></button>
</form>
<p class="ok" id="msg-password" hidden></p>
<p class="ok" id="msg-personas" hidden></p>
<?php include __DIR__ . '/_solicitudes_vinculo.php'; ?>
<section class="nombres-listado">
<h2 class="nombres-listado-titulo"><?= _("Personas del centro") ?></h2>
<table id="tabla-personas">
    <thead>
    <tr>
        <th>#</th><th><?= _("Centro") ?></th><th><?= _("Nombre") ?></th><th><?= _("Apellidos") ?></th><th><?= _("Iniciales") ?></th><th><?= _("Correo") ?></th>
        <th><?= _("Exención") ?></th><th><?= _("Vivienda fija") ?></th><th><?= _("Aporta a G") ?></th><th><?= _("Desgrava") ?></th><th><?= _("Base liq.") ?></th><th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
</section>
<script>
const I18N_PERSONAS = {
  si: <?= json_encode(_("sí"), JSON_UNESCAPED_UNICODE) ?>,
  no: <?= json_encode(_("no"), JSON_UNESCAPED_UNICODE) ?>,
  editar: <?= json_encode(_("Editar"), JSON_UNESCAPED_UNICODE) ?>,
  borrar: <?= json_encode(_("Borrar"), JSON_UNESCAPED_UNICODE) ?>,
  confirmQuitar: <?= json_encode(_("¿Quitar %s del listado?"), JSON_UNESCAPED_UNICODE) ?>,
  error: <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>,
  passwordInicial: <?= json_encode(_("Contraseña inicial de %s: %s — comunícasela ahora; no se volverá a mostrar."), JSON_UNESCAPED_UNICODE) ?>,
  faltaResponsable: <?= json_encode(_("Marque que el centro es responsable de los datos de las personas que da de alta."), JSON_UNESCAPED_UNICODE) ?>,
  basePrevision: <?= json_encode(_("111 previsión"), JSON_UNESCAPED_UNICODE) ?>,
  baseProyectado: <?= json_encode(_("111 proyectado"), JSON_UNESCAPED_UNICODE) ?>,
};
function etiquetaBaseLiquidable(p) {
  const valor = p.base_liquidable || p.base_liquidable_defecto || '';
  if (!valor) return '';
  if (p.base_liquidable) return valor;
  if (p.base_liquidable_origen === 'prevision_111') {
    return valor + ' (' + I18N_PERSONAS.basePrevision + ')';
  }
  if (p.base_liquidable_origen === 'proyectado_111') {
    return valor + ' (' + I18N_PERSONAS.baseProyectado + ')';
  }
  return valor;
}
async function loadPersonas() {
  const r = await api('/api/personas');
  const tb = document.querySelector('#tabla-personas tbody');
  tb.innerHTML = '';
  (r.personas || []).forEach((p, i) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${i+1}</td><td>${esc(p.centro_nombre || '')}</td><td>${esc(p.nombre)}</td><td>${esc(p.apellidos)}</td>
      <td>${esc(p.iniciales)}</td><td>${esc(p.email)}</td>
      <td>${p.mes_exento_inicio || ''}–${p.mes_exento_fin || ''} ${p.mes_exento2_inicio || ''}–${p.mes_exento2_fin || ''}</td>
      <td>${p.importe_vivienda_fijo || ''}</td>
      <td>${p.vivienda_aporta_generales ? esc(I18N_PERSONAS.si) : esc(I18N_PERSONAS.no)}</td>
      <td>${p.puede_desgravar ? esc(I18N_PERSONAS.si) : esc(I18N_PERSONAS.no)}</td>
      <td>${esc(etiquetaBaseLiquidable(p))}</td>
      <td><button data-id="${p.id}">${esc(I18N_PERSONAS.editar)}</button> <button data-del="${p.id}">${esc(I18N_PERSONAS.borrar)}</button></td>`;
    tr.querySelector('[data-id]').onclick = () => {
      const form = document.getElementById('form-persona');
      fillForm(form, p);
      form.querySelector('[name=vivienda_aporta_generales]').value = p.vivienda_aporta_generales ? '1' : '0';
      form.querySelector('[name=puede_desgravar]').value = p.puede_desgravar ? '1' : '0';
      const bl = form.querySelector('[name=base_liquidable]');
      bl.placeholder = p.base_liquidable_defecto || '';
      if (!p.base_liquidable && p.base_liquidable_defecto) {
        bl.value = p.base_liquidable_defecto;
      }
    };
    tr.querySelector('[data-del]').onclick = async () => {
      if (!confirm(I18N_PERSONAS.confirmQuitar.replace('%s', p.iniciales))) return;
      const s = await api('/api/personas/' + p.id, {method:'DELETE'});
      if (!s.ok) return alert(s.error || I18N_PERSONAS.error);
      const msg = document.getElementById('msg-personas');
      if (s.mensaje) {
        msg.hidden = false;
        msg.textContent = s.mensaje;
      } else {
        msg.hidden = true;
      }
      loadPersonas();
    };
    tb.appendChild(tr);
  });
}
document.addEventListener('DOMContentLoaded', async () => {
  let aportaDefault = '1';
  const cfg = await api('/api/configuracion');
  if (cfg.config && cfg.config.tipo_cierre === 'necesidades') {
    aportaDefault = '0';
  }
  loadPersonas();
  document.getElementById('btn-nuevo').onclick = () => {
    document.getElementById('form-persona').reset();
    document.querySelector('[name=vivienda_aporta_generales]').value = aportaDefault;
    document.querySelector('[name=puede_desgravar]').value = '1';
    document.querySelector('[name=base_liquidable]').placeholder = '';
    document.getElementById('msg-password').hidden = true;
  };
  document.querySelector('[name=vivienda_aporta_generales]').value = aportaDefault;
  document.querySelector('[name=puede_desgravar]').value = '1';
  document.getElementById('form-persona').onsubmit = async (ev) => {
    ev.preventDefault();
    const datos = formObj(ev.target);
    const esAlta = !datos.id;
    const casilla = document.getElementById('asumo-responsable-nombres');
    if (esAlta && !casilla.checked) {
      return alert(I18N_PERSONAS.faltaResponsable);
    }
    if (esAlta) {
      datos.asumo_responsable_nombres = '1';
    }
    const s = await api('/api/personas', {method:'POST', body: datos});
    if (!s.ok) return alert(s.error);
    const msg = document.getElementById('msg-password');
    if (s.password_inicial) {
      msg.hidden = false;
      msg.textContent = I18N_PERSONAS.passwordInicial
        .replace('%s', s.persona.email || '')
        .replace('%s', s.password_inicial);
    } else {
      msg.hidden = true;
    }
    ev.target.reset();
    document.querySelector('[name=vivienda_aporta_generales]').value = aportaDefault;
    document.querySelector('[name=base_liquidable]').placeholder = '';
    loadPersonas();
  };
});
</script>
