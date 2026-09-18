<h1><?= _("Planes contables") ?></h1>
<p class="muted"><?= _("Catálogo de planes y sus conceptos. Los códigos 7x del libro P son plantilla: cada centro los personaliza en Parámetros → Centros.") ?></p>
<table id="tabla-planes">
    <thead><tr><th><?= _("Código") ?></th><th><?= _("Nombre") ?></th><th></th></tr></thead>
    <tbody></tbody>
</table>
<h2><?= _("Nuevo plan") ?></h2>
<form id="form-plan" class="grid-form">
    <input type="hidden" name="id" value="">
    <label><?= _("Código") ?> <input name="codigo" required pattern="[A-Za-z0-9._-]{2,16}"></label>
    <label><?= _("Nombre") ?> <input name="nombre" required></label>
    <button type="submit"><?= _("Guardar") ?></button>
    <button type="button" id="btn-plan-nuevo"><?= _("Limpiar") ?></button>
</form>
<p class="ok" id="msg-plan" hidden></p>

<section id="sec-conceptos" hidden>
    <h2 id="titulo-conceptos"><?= _("Conceptos del plan") ?></h2>
    <p class="muted"><?= _("Edite nombres, descripciones y naturaleza. Los 7x son valores por defecto del capítulo VII.") ?></p>
    <div class="plan-cuenta-bar">
        <div class="plan-cuenta-tabs" role="tablist" aria-label="<?= htmlspecialchars(_("Libro contable"), ENT_QUOTES) ?>">
            <button type="button" class="on" data-cuenta="P" id="tab-p" role="tab" aria-selected="true"><?= _("P — Personal") ?></button>
            <button type="button" data-cuenta="G" id="tab-g" role="tab" aria-selected="false"><?= _("G — General") ?></button>
        </div>
        <p class="plan-cuenta-activa" id="etiq-cuenta-activa" aria-live="polite"></p>
    </div>
    <div class="plan-acciones">
        <button type="button" id="btn-add-concepto"><?= _("Añadir concepto") ?></button>
        <button type="button" id="btn-save-conceptos"><?= _("Guardar conceptos") ?></button>
        <button type="button" id="btn-export-conceptos"><?= _("Exportar") ?></button>
        <button type="button" id="btn-import-conceptos"><?= _("Importar") ?></button>
        <input type="file" id="input-import-conceptos" accept=".json,application/json" hidden>
    </div>
    <table id="tabla-conceptos">
        <thead>
        <tr>
            <th><?= _("Código") ?></th><th><?= _("Nombre") ?></th><th><?= _("Descripción") ?></th>
            <th><?= _("Naturaleza") ?></th><th><?= _("Orden") ?></th><th></th>
        </tr>
        </thead>
        <tbody></tbody>
    </table>
    <p class="ok" id="msg-conceptos" hidden></p>
</section>
<script>
const I18N_ADMIN_PLANES = {
  editar: <?= json_encode(_("Editar"), JSON_UNESCAPED_UNICODE) ?>,
  conceptos: <?= json_encode(_("Conceptos"), JSON_UNESCAPED_UNICODE) ?>,
  borrar: <?= json_encode(_("Borrar"), JSON_UNESCAPED_UNICODE) ?>,
  quitar: <?= json_encode(_("Quitar"), JSON_UNESCAPED_UNICODE) ?>,
  confirmBorrar: <?= json_encode(_("¿Borrar este plan contable?"), JSON_UNESCAPED_UNICODE) ?>,
  guardado: <?= json_encode(_("Plan guardado."), JSON_UNESCAPED_UNICODE) ?>,
  conceptosGuardados: <?= json_encode(_("Conceptos guardados."), JSON_UNESCAPED_UNICODE) ?>,
  conceptosImportados: <?= json_encode(_("Conceptos importados."), JSON_UNESCAPED_UNICODE) ?>,
  capVII: <?= json_encode(_("7x — plantilla cap. VII"), JSON_UNESCAPED_UNICODE) ?>,
  importInvalido: <?= json_encode(_("Fichero JSON no válido."), JSON_UNESCAPED_UNICODE) ?>,
  libroP: <?= json_encode(_("Libro P (personal)"), JSON_UNESCAPED_UNICODE) ?>,
  libroG: <?= json_encode(_("Libro G (general)"), JSON_UNESCAPED_UNICODE) ?>,
  nConceptos: <?= json_encode(_("%d conceptos"), JSON_UNESCAPED_UNICODE) ?>,
};
const NATURALEZAS = ['ingreso', 'gasto', 'saldo', 'disponible', 'transferencia'];
let planActivo = null;
let cuentaActiva = 'P';
let conceptosCache = { P: [], G: [] };

function filaPlan(p) {
  const tr = document.createElement('tr');
  tr.innerHTML =
    '<td>' + esc(p.codigo) + '</td>' +
    '<td>' + esc(p.nombre) + '</td>' +
    '<td><button type="button" class="btn-meta">' + esc(I18N_ADMIN_PLANES.editar) + '</button> ' +
    '<button type="button" class="btn-conceptos">' + esc(I18N_ADMIN_PLANES.conceptos) + '</button> ' +
    '<button type="button" class="btn-del peligro">' + esc(I18N_ADMIN_PLANES.borrar) + '</button></td>';
  tr.querySelector('.btn-meta').onclick = () => {
    const f = document.getElementById('form-plan');
    f.querySelector('[name=id]').value = p.id;
    f.querySelector('[name=codigo]').value = p.codigo;
    f.querySelector('[name=nombre]').value = p.nombre;
  };
  tr.querySelector('.btn-conceptos').onclick = () => abrirConceptos(p);
  tr.querySelector('.btn-del').onclick = async () => {
    if (!confirm(I18N_ADMIN_PLANES.confirmBorrar)) return;
    const s = await api('/api/admin/planes/' + p.id + '/borrar', { method: 'POST', body: { confirmar: true } });
    if (!s.ok) return alert(s.error);
    if (planActivo && planActivo.id === p.id) {
      planActivo = null;
      document.getElementById('sec-conceptos').hidden = true;
    }
    await loadPlanes();
  };
  return tr;
}

function filaConcepto(c = {}) {
  const tr = document.createElement('tr');
  const es7 = String(c.codigo || '').match(/^7\d{1,2}$/);
  const opts = NATURALEZAS.map((n) =>
    '<option value="' + n + '"' + (c.naturaleza === n ? ' selected' : '') + '>' + n + '</option>'
  ).join('');
  tr.innerHTML =
    '<td><input name="codigo" required value="' + esc(c.codigo || '') + '"' +
    (c._persistido ? ' readonly' : '') + '></td>' +
    '<td><input name="nombre" required value="' + esc(c.nombre || '') + '"></td>' +
    '<td><input name="descripcion" value="' + esc(c.descripcion || '') + '"></td>' +
    '<td><select name="naturaleza">' + opts + '</select></td>' +
    '<td><input name="orden" type="number" value="' + esc(String(c.orden ?? 0)) + '"></td>' +
    '<td><button type="button" class="btn-quitar">' + esc(I18N_ADMIN_PLANES.quitar) + '</button></td>';
  if (es7) {
    tr.querySelector('[name=codigo]').title = I18N_ADMIN_PLANES.capVII;
  }
  tr.querySelector('.btn-quitar').onclick = () => tr.remove();
  if (c.codigo) tr.dataset.persistido = c._persistido ? '1' : '';
  return tr;
}

function conceptosDelFormulario(cuenta) {
  return [...document.querySelectorAll('#tabla-conceptos tbody tr')].map((tr) => ({
    codigo: tr.querySelector('[name=codigo]').value.trim(),
    cuenta,
    nombre: tr.querySelector('[name=nombre]').value.trim(),
    descripcion: tr.querySelector('[name=descripcion]').value.trim(),
    naturaleza: tr.querySelector('[name=naturaleza]').value,
    orden: Number(tr.querySelector('[name=orden]').value) || 0,
  }));
}

function actualizarEtiqCuenta() {
  const n = (conceptosCache[cuentaActiva] || []).length;
  const libro = cuentaActiva === 'G' ? I18N_ADMIN_PLANES.libroG : I18N_ADMIN_PLANES.libroP;
  document.getElementById('etiq-cuenta-activa').textContent =
    libro + ' · ' + I18N_ADMIN_PLANES.nConceptos.replace('%d', String(n));
}

function pintarConceptos() {
  const tb = document.querySelector('#tabla-conceptos tbody');
  tb.innerHTML = '';
  (conceptosCache[cuentaActiva] || []).forEach((c) => {
    const tr = filaConcepto(Object.assign({}, c, { _persistido: true }));
    tb.appendChild(tr);
  });
  actualizarEtiqCuenta();
}

function cambiarCuenta(cuenta) {
  if (planActivo && cuenta !== cuentaActiva) {
    conceptosCache[cuentaActiva] = conceptosDelFormulario(cuentaActiva);
  }
  cuentaActiva = cuenta;
  document.getElementById('tab-p').classList.toggle('on', cuenta === 'P');
  document.getElementById('tab-g').classList.toggle('on', cuenta === 'G');
  document.getElementById('tab-p').setAttribute('aria-selected', cuenta === 'P' ? 'true' : 'false');
  document.getElementById('tab-g').setAttribute('aria-selected', cuenta === 'G' ? 'true' : 'false');
  pintarConceptos();
}

function repartirConceptos(lista) {
  const cache = { P: [], G: [] };
  (lista || []).forEach((c) => {
    if (c.cuenta === 'P' || c.cuenta === 'G') {
      cache[c.cuenta].push(c);
    }
  });
  return cache;
}

async function abrirConceptos(plan) {
  planActivo = plan;
  document.getElementById('sec-conceptos').hidden = false;
  document.getElementById('msg-conceptos').hidden = true;
  document.getElementById('titulo-conceptos').textContent =
    <?= json_encode(_("Conceptos de"), JSON_UNESCAPED_UNICODE) ?> + ' ' + (plan.nombre || plan.codigo);
  const r = await api('/api/admin/planes/' + plan.id + '/conceptos');
  if (!r.ok) return alert(r.error);
  conceptosCache = repartirConceptos(r.conceptos);
  cuentaActiva = 'P';
  cambiarCuenta('P');
  document.getElementById('sec-conceptos').scrollIntoView({ behavior: 'smooth' });
}

async function loadPlanes() {
  const r = await api('/api/admin/planes');
  if (!r.ok) return alert(r.error);
  const tb = document.querySelector('#tabla-planes tbody');
  tb.innerHTML = '';
  (r.planes || []).forEach((p) => tb.appendChild(filaPlan(p)));
}

document.addEventListener('DOMContentLoaded', () => {
  loadPlanes();
  document.getElementById('btn-plan-nuevo').onclick = () => {
    const f = document.getElementById('form-plan');
    f.reset();
    f.querySelector('[name=id]').value = '';
  };
  document.getElementById('form-plan').onsubmit = async (ev) => {
    ev.preventDefault();
    const body = formObj(ev.target);
    if (body.id === '') delete body.id;
    const s = await api('/api/admin/planes', { method: 'POST', body });
    if (!s.ok) return alert(s.error);
    document.getElementById('msg-plan').hidden = false;
    document.getElementById('msg-plan').textContent = I18N_ADMIN_PLANES.guardado;
    ev.target.reset();
    await loadPlanes();
  };
  document.getElementById('tab-p').onclick = () => cambiarCuenta('P');
  document.getElementById('tab-g').onclick = () => cambiarCuenta('G');
  document.getElementById('btn-add-concepto').onclick = () => {
    document.querySelector('#tabla-conceptos tbody').appendChild(filaConcepto({ naturaleza: 'gasto', orden: 0 }));
  };
  document.getElementById('btn-save-conceptos').onclick = async () => {
    if (!planActivo) return;
    conceptosCache[cuentaActiva] = conceptosDelFormulario(cuentaActiva);
    const conceptos = [...conceptosCache.P, ...conceptosCache.G];
    const s = await api('/api/admin/planes/' + planActivo.id + '/conceptos', {
      method: 'POST',
      body: { conceptos },
    });
    if (!s.ok) return alert(s.error);
    document.getElementById('msg-conceptos').hidden = false;
    document.getElementById('msg-conceptos').textContent = I18N_ADMIN_PLANES.conceptosGuardados;
    conceptosCache = repartirConceptos(s.conceptos);
    pintarConceptos();
  };
  document.getElementById('btn-export-conceptos').onclick = () => {
    if (!planActivo) return;
    window.location.href = '/api/admin/planes/' + planActivo.id + '/conceptos/export';
  };
  document.getElementById('btn-import-conceptos').onclick = () => {
    if (!planActivo) return;
    document.getElementById('input-import-conceptos').click();
  };
  document.getElementById('input-import-conceptos').onchange = async (ev) => {
    const input = ev.target;
    const file = input.files && input.files[0];
    input.value = '';
    if (!file || !planActivo) return;
    let payload;
    try {
      payload = JSON.parse(await file.text());
    } catch (e) {
      return alert(I18N_ADMIN_PLANES.importInvalido);
    }
    const s = await api('/api/admin/planes/' + planActivo.id + '/conceptos/import', {
      method: 'POST',
      body: payload,
    });
    if (!s.ok) return alert(s.error);
    document.getElementById('msg-conceptos').hidden = false;
    document.getElementById('msg-conceptos').textContent = I18N_ADMIN_PLANES.conceptosImportados;
    conceptosCache = repartirConceptos(s.conceptos);
    pintarConceptos();
  };
});
</script>
