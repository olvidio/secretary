<?php $esClub = !empty($esClub); $esFundacion = !empty($esFundacion); ?>
<h1 id="centro-cabecera"><?= _("Centro") ?> …</h1>

<section>
    <h2><?php if ($esClub): ?>
        <?= $esFundacion ? _("Usuarios de esta fundación") : _("Usuarios de esta associació") ?>
    <?php else: ?>
        <?= _("Secretarios") ?>
    <?php endif; ?></h2>
    <table id="tabla-usuarios">
        <thead>
        <tr><th><?= _("Alias") ?></th><th><?= _("Correo") ?></th><th><?= _("Nombre") ?></th><th><?= _("Rol") ?></th><th></th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <?php if (empty($soloConsulta)): ?>
    <h3><?= _("Invitar secretario") ?></h3>
    <?php require __DIR__ . '/../../shared/view/_form_invitar_secretario.php'; ?>
    <?php endif; ?>
    <?php if (empty($soloConsulta) && $esClub): ?>
    <h3><?= _("Importar Grisbi") ?></h3>
    <p class="muted"><?= $esFundacion
        ? _("Carga un fichero .gsb en esta fundación. Las categorías nuevas se crean como cuentas y los movimientos como asientos. Volver a importar el mismo fichero no duplica.")
        : _("Carga un fichero .gsb en esta associació. Las categorías nuevas se crean como cuentas y los movimientos como asientos. Volver a importar el mismo fichero no duplica.") ?></p>
    <form id="form-grisbi" class="grid-form">
        <label><?= _("Fichero .gsb") ?> <input name="grisbi" type="file" accept=".gsb,.xml,text/xml" required></label>
        <button type="submit"><?= _("Importar") ?></button>
    </form>
    <p class="ok" id="msg-import" hidden></p>
    <?php elseif (empty($soloConsulta)): ?>
    <h3><?= _("Importar datos de un excel de secretario") ?></h3>
    <p class="muted"><?= _("Carga el .xlsm en el libro de este centro, sin tocar el de los demás.") ?></p>
    <form id="form-import" class="grid-form">
        <label><?= _("Fichero Excel") ?> <input name="excel" type="file" accept=".xlsm,.xlsx" required></label>
        <label class="inline casilla-legal">
            <input type="checkbox" name="asumo_responsable_nombres" value="1" required>
            <span><?= htmlspecialchars((string) ($textoAsumoNombres ?? _('Declaro que el centro, y yo como secretario, somos responsables del tratamiento de los datos de las personas que doy de alta, importo o vinculo. Secretario es un programa gratuito que solo aloja la información. Tengo base legal para ese tratamiento.')), ENT_QUOTES) ?></span>
        </label>
        <button type="submit"><?= _("Importar Excel") ?></button>
    </form>
    <p class="ok" id="msg-import" hidden></p>
    <?php endif; ?>

    <?php if (!$esClub): ?>
    <section id="sec-labores" hidden>
        <h3><?= _("VII. Otras labores apostólicas (613 P)") ?></h3>
        <p class="muted" id="labores-ayuda"><?= _("Partidas del capítulo VII en el plan H16n. Aparecen en el 613 P y como conceptos de gasto en P.") ?></p>
        <table id="tabla-labores">
            <thead>
            <tr><th><?= _("Código") ?></th><th><?= _("Etiqueta") ?></th><th><?= _("Desgrava") ?></th><th></th></tr>
            </thead>
            <tbody></tbody>
        </table>
        <?php if (empty($soloConsulta)): ?>
        <p class="grid-form" style="margin-top:.5rem">
            <button type="button" id="btn-add-labor"><?= _("Añadir partida") ?></button>
            <button type="button" id="btn-save-labores"><?= _("Guardar partidas") ?></button>
        </p>
        <?php endif; ?>
        <p class="ok" id="msg-labores" hidden></p>
    </section>
    <?php endif; ?>
</section>
<script>
const I18N_CENTROS = {
  importados: <?= json_encode(_(" Importados %s nombres y %s asientos."), JSON_UNESCAPED_UNICODE) ?>,
  si: <?= json_encode(_("Sí"), JSON_UNESCAPED_UNICODE) ?>,
  quitar: <?= json_encode(_("Quitar"), JSON_UNESCAPED_UNICODE) ?>,
  noCentro: <?= json_encode(_("No se pudo cargar el centro"), JSON_UNESCAPED_UNICODE) ?>,
  partidasGuardadas: <?= json_encode(_("Partidas guardadas."), JSON_UNESCAPED_UNICODE) ?>,
  excelImportado: <?= json_encode(_("Excel importado."), JSON_UNESCAPED_UNICODE) ?>,
  grisbiImportado: <?= json_encode(_("Grisbi importado."), JSON_UNESCAPED_UNICODE) ?>,
  esClub: <?= $esClub ? 'true' : 'false' ?>,
  faltaResponsable: <?= json_encode(_("Marque que el centro es responsable de los datos de las personas que da de alta."), JSON_UNESCAPED_UNICODE) ?>,
};
function textoImportacion(imp) {
  if (!imp) return '';
  return I18N_CENTROS.importados
    .replace('%s', imp.personas || 0)
    .replace('%s', imp.asientos || 0);
}
function filaLabor(p = {}) {
  const tr = document.createElement('tr');
  const solo = document.body.classList.contains('solo-consulta');
  const bloqueo = solo ? ' readonly' : '';
  tr.innerHTML =
    '<td><input name="codigo" required pattern="7\\d{1,2}" maxlength="3" ' +
    'placeholder="71" value="' + esc(p.codigo || '') + '"' + bloqueo + '></td>' +
    '<td><input name="etiqueta" required value="' + esc(p.etiqueta || '') + '"' + bloqueo + '></td>' +
    '<td><label><input type="checkbox" name="desgrava"' + (p.desgrava ? ' checked' : '') + (solo ? ' disabled' : '') + '> ' + esc(I18N_CENTROS.si) + '</label></td>' +
    (solo ? '<td></td>' : '<td><button type="button" class="btn-quitar">' + esc(I18N_CENTROS.quitar) + '</button></td>');
  tr.querySelector('.btn-quitar')?.addEventListener('click', () => tr.remove());
  return tr;
}

function partidasDelFormulario() {
  return [...document.querySelectorAll('#tabla-labores tbody tr')].map((tr) => ({
    codigo: tr.querySelector('[name=codigo]').value.trim(),
    etiqueta: tr.querySelector('[name=etiqueta]').value.trim(),
    desgrava: tr.querySelector('[name=desgrava]').checked,
  }));
}

function sugerirCodigoLabor() {
  const usados = new Set(partidasDelFormulario().map((p) => p.codigo));
  for (let n = 71; n <= 79; n++) {
    const c = String(n);
    if (!usados.has(c)) return c;
  }
  for (let n = 790; n <= 799; n++) {
    const c = String(n);
    if (!usados.has(c)) return c;
  }
  return '791';
}

async function loadLabores() {
  const sec = document.getElementById('sec-labores');
  const r = await api('/api/centros/partidas-labores');
  if (!r.ok) {
    sec.hidden = true;
    return;
  }
  sec.hidden = false;
  const tb = document.querySelector('#tabla-labores tbody');
  tb.innerHTML = '';
  (r.partidas || []).forEach((p) => tb.appendChild(filaLabor(p)));
}

async function loadCentro() {
  const r = await api('/api/centros');
  const cab = document.getElementById('centro-cabecera');
  const tb = document.querySelector('#tabla-usuarios tbody');
  tb.innerHTML = '';
  if (!r.ok) {
    if (cab) cab.textContent = r.error || I18N_CENTROS.noCentro;
    return;
  }
  const c = r.centro || {};
  const sigla = String(c.codigo || c.nombre || '').trim();
  if (cab) {
    cab.textContent = sigla
      ? (<?= json_encode(_("Centro"), JSON_UNESCAPED_UNICODE) ?> + ' ' + sigla)
      : <?= json_encode(_("Centro"), JSON_UNESCAPED_UNICODE) ?>;
  }
  const solo = document.body.classList.contains('solo-consulta');
  const miId = Number(r.mi_identidad_id || 0);
  (r.usuarios || []).forEach((u) => {
    const tr = document.createElement('tr');
    tr.innerHTML = filaUsuariosCentroHtml(u, solo, miId);
    tb.appendChild(tr);
  });
}
document.addEventListener('DOMContentLoaded', () => {
  loadCentro();
  document.addEventListener('centro-usuarios-actualizados', () => loadCentro());
  if (!I18N_CENTROS.esClub) loadLabores();
  document.getElementById('btn-add-labor')?.addEventListener('click', () => {
    const tb = document.querySelector('#tabla-labores tbody');
    tb.appendChild(filaLabor({ codigo: sugerirCodigoLabor(), etiqueta: '' }));
    tb.lastElementChild?.querySelector('[name=etiqueta]')?.focus();
  });
  document.getElementById('btn-save-labores')?.addEventListener('click', async () => {
    const msg = document.getElementById('msg-labores');
    msg.hidden = true;
    const partidas = partidasDelFormulario();
    const s = await api('/api/centros/partidas-labores', { method: 'POST', body: { partidas } });
    if (!s.ok) return alert(s.error);
    msg.hidden = false;
    msg.textContent = I18N_CENTROS.partidasGuardadas;
    await loadLabores();
  });
  document.getElementById('form-usuario')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/centros/usuarios', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    ev.target.reset();
    loadCentro();
  });
  document.getElementById('form-grisbi')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const btn = ev.target.querySelector('button[type=submit]');
    const msg = document.getElementById('msg-import');
    btn.disabled = true;
    msg.hidden = true;
    try {
      const s = await api('/api/grisbi/importar', { method: 'POST', body: new FormData(ev.target) });
      if (!s.ok) return alert(s.error || 'Error');
      msg.hidden = false;
      msg.textContent = I18N_CENTROS.grisbiImportado
        + ' Altas: ' + (s.altas || 0) + '. Omitidos: ' + (s.omitidos || 0) + '. Listados: ' + (s.listados || 0)
        + ((s.avisos && s.avisos.length) ? ' ' + s.avisos.join(' ') : '');
      ev.target.reset();
    } finally {
      btn.disabled = false;
    }
  });
  document.getElementById('form-import')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    if (!ev.target.querySelector('[name=asumo_responsable_nombres]')?.checked) {
      return alert(I18N_CENTROS.faltaResponsable);
    }
    const btn = ev.target.querySelector('button[type=submit]');
    const msg = document.getElementById('msg-import');
    btn.disabled = true;
    msg.hidden = true;
    try {
      const s = await api('/api/centros/import', {method:'POST', body: new FormData(ev.target)});
      if (!s.ok) return alert(s.error);
      msg.hidden = false;
      msg.textContent = I18N_CENTROS.excelImportado + textoImportacion(s.importacion);
      ev.target.reset();
    } finally {
      btn.disabled = false;
    }
  });
});
</script>
