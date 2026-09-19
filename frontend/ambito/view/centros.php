<h1><?= _("Centros") ?></h1>
<p>
    <?= _("Este centro tiene sus propias cuentas, nombres y secretario. Un usuario como scl2 se vincula a otro centro y no ve los datos de éste.") ?>
</p>

<section>
    <h2><?= _("Este centro") ?></h2>
    <p id="centro-actual" class="muted"><?= _("Cargando…") ?></p>
    <table id="tabla-usuarios">
        <thead>
        <tr><th><?= _("Usuario") ?></th><th><?= _("Correo") ?></th><th><?= _("Nombre del usuario") ?></th><th><?= _("Rol") ?></th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <h3><?= _("Añadir usuario de este centro") ?></h3>
    <form id="form-usuario" class="grid-form">
        <label><?= _("Usuario (alias)") ?> <input name="usuario" required placeholder="<?= htmlspecialchars(_("p. ej. scl"), ENT_QUOTES) ?>"></label>
        <label><?= _("Correo") ?> <input name="email" type="email" required></label>
        <label><?= _("Contraseña") ?> <input name="password" type="password" required minlength="6"></label>
        <label><?= _("Nombre del usuario") ?> <input name="nombre" autocomplete="name"></label>
        <button type="submit"><?= _("Vincular") ?></button>
    </form>
    <h3><?= _("Excel de este centro") ?></h3>
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
    <p class="muted"><?= _("Mientras estemos de pruebas: vaciar asientos, remesas y arqueos para volver a cargar el Excel. Quedan el centro, los usuarios y los nombres.") ?></p>
    <button type="button" id="btn-vaciar" class="peligro"><?= _("Vaciar datos (pruebas)") ?></button>
    <p class="ok" id="msg-vaciar" hidden></p>

    <section id="sec-labores" hidden>
        <h3><?= _("VII. Otras labores apostólicas (613 P)") ?></h3>
        <p class="muted" id="labores-ayuda"><?= _("Partidas del capítulo VII en el plan H16n. Aparecen en el 613 P y como conceptos de gasto en P.") ?></p>
        <table id="tabla-labores">
            <thead>
            <tr><th><?= _("Código") ?></th><th><?= _("Etiqueta") ?></th><th><?= _("Desgrava") ?></th><th></th></tr>
            </thead>
            <tbody></tbody>
        </table>
        <p class="grid-form" style="margin-top:.5rem">
            <button type="button" id="btn-add-labor"><?= _("Añadir partida") ?></button>
            <button type="button" id="btn-save-labores"><?= _("Guardar partidas") ?></button>
        </p>
        <p class="ok" id="msg-labores" hidden></p>
    </section>
</section>
<script>
const I18N_CENTROS = {
  importados: <?= json_encode(_(" Importados %s nombres y %s asientos."), JSON_UNESCAPED_UNICODE) ?>,
  si: <?= json_encode(_("Sí"), JSON_UNESCAPED_UNICODE) ?>,
  quitar: <?= json_encode(_("Quitar"), JSON_UNESCAPED_UNICODE) ?>,
  noCentro: <?= json_encode(_("No se pudo cargar el centro"), JSON_UNESCAPED_UNICODE) ?>,
  partidasGuardadas: <?= json_encode(_("Partidas guardadas."), JSON_UNESCAPED_UNICODE) ?>,
  excelImportado: <?= json_encode(_("Excel importado."), JSON_UNESCAPED_UNICODE) ?>,
  faltaResponsable: <?= json_encode(_("Marque que el centro es responsable de los datos de las personas que da de alta."), JSON_UNESCAPED_UNICODE) ?>,
  confirmVaciar: <?= json_encode(_("Esto borra asientos, remesas y arqueos de ESTE centro para poder recargar el Excel. Quedan el centro, los usuarios y los nombres. ¿Seguro?"), JSON_UNESCAPED_UNICODE) ?>,
  vaciados: <?= json_encode(_("Vaciados %s asientos en %s ejercicio(s). Ya puedes importar el Excel."), JSON_UNESCAPED_UNICODE) ?>,
};
function textoImportacion(imp) {
  if (!imp) return '';
  return I18N_CENTROS.importados
    .replace('%s', imp.personas || 0)
    .replace('%s', imp.asientos || 0);
}
function filaLabor(p = {}) {
  const tr = document.createElement('tr');
  tr.innerHTML =
    '<td><input name="codigo" required pattern="7\\d{1,2}" maxlength="3" ' +
    'placeholder="71" value="' + esc(p.codigo || '') + '"></td>' +
    '<td><input name="etiqueta" required value="' + esc(p.etiqueta || '') + '"></td>' +
    '<td><label><input type="checkbox" name="desgrava"' + (p.desgrava ? ' checked' : '') + '> ' + esc(I18N_CENTROS.si) + '</label></td>' +
    '<td><button type="button" class="btn-quitar">' + esc(I18N_CENTROS.quitar) + '</button></td>';
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
  const p = document.getElementById('centro-actual');
  const tb = document.querySelector('#tabla-usuarios tbody');
  tb.innerHTML = '';
  if (!r.ok) {
    p.textContent = r.error || I18N_CENTROS.noCentro;
    return;
  }
  const c = r.centro || {};
  p.textContent = (c.nombre || c.codigo || '')
    + (c.plan_contable ? ' · plan ' + c.plan_contable : '')
    + (c.tipo_cierre ? ' · cierre ' + c.tipo_cierre : '');
  (r.usuarios || []).forEach((u) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${esc(u.alias)}</td><td>${esc(u.email)}</td><td>${esc(u.nombre)}</td><td>${esc(u.rol)}</td>`;
    tb.appendChild(tr);
  });
}
document.addEventListener('DOMContentLoaded', () => {
  loadCentro();
  loadLabores();
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
  document.getElementById('form-usuario').onsubmit = async (ev) => {
    ev.preventDefault();
    const s = await api('/api/centros/usuarios', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    ev.target.reset();
    loadCentro();
  };
  document.getElementById('form-import').onsubmit = async (ev) => {
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
  };
  document.getElementById('btn-vaciar').onclick = async () => {
    if (!confirm(I18N_CENTROS.confirmVaciar)) {
      return;
    }
    const s = await api('/api/centros/vaciar', {method:'POST', body: {confirmar: true}});
    if (!s.ok) return alert(s.error);
    const msg = document.getElementById('msg-vaciar');
    msg.hidden = false;
    msg.textContent = I18N_CENTROS.vaciados
      .replace('%s', s.asientos || 0)
      .replace('%s', s.ejercicios || 0);
  };
});
</script>
