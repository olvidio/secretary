<h1><?= _("Legal") ?></h1>
<p class="muted"><?= _("Consulta las pruebas de aceptación de condiciones y declaraciones responsables. Sirve para atender reclamaciones o requerimientos judiciales.") ?></p>

<form id="form-buscar-legal" class="grid-form admin-legal-buscar">
    <label><?= _("Buscar usuario") ?>
        <input name="q" id="legal-q" required minlength="2" placeholder="<?= htmlspecialchars(_("Correo, alias o ID"), ENT_QUOTES) ?>" autocomplete="off">
    </label>
    <button type="submit"><?= _("Buscar") ?></button>
</form>

<table id="tabla-legal-resultados" hidden>
    <thead>
        <tr>
            <th><?= _("Usuario") ?></th>
            <th><?= _("Correo") ?></th>
            <th><?= _("Verificado") ?></th>
            <th><?= _("Aceptaciones") ?></th>
            <th><?= _("Primera") ?></th>
            <th><?= _("Última") ?></th>
            <th></th>
        </tr>
    </thead>
    <tbody></tbody>
</table>
<p id="legal-sin-resultados" class="muted" hidden><?= _("No hay coincidencias.") ?></p>

<section id="panel-expediente" class="admin-legal-expediente" hidden>
    <div class="admin-legal-expediente-cabecera">
        <h2 id="expediente-titulo"></h2>
        <button type="button" id="btn-expediente-pdf"><?= _("Descargar PDF") ?></button>
        <button type="button" id="btn-expediente-html"><?= _("Descargar HTML") ?></button>
    </div>
    <div id="expediente-resumen" class="muted"></div>
    <div id="expediente-eventos"></div>
</section>

<div id="expediente-pdf" hidden aria-hidden="true"></div>

<script src="/js/html2pdf.bundle.min.js"></script>
<script>
const I18N_ADMIN_LEGAL = {
  verExpediente: <?= json_encode(_("Ver expediente"), JSON_UNESCAPED_UNICODE) ?>,
  si: <?= json_encode(_("Sí"), JSON_UNESCAPED_UNICODE) ?>,
  no: <?= json_encode(_("No"), JSON_UNESCAPED_UNICODE) ?>,
  aceptaciones: <?= json_encode(_("aceptaciones"), JSON_UNESCAPED_UNICODE) ?>,
  verificado: <?= json_encode(_("Correo verificado el"), JSON_UNESCAPED_UNICODE) ?>,
  sinRegistros: <?= json_encode(_("No hay registros de aceptación."), JSON_UNESCAPED_UNICODE) ?>,
  pdfError: <?= json_encode(_("No se pudo generar el PDF"), JSON_UNESCAPED_UNICODE) ?>,
};

let expedienteActivo = null;

function fmtLegal(iso) {
  if (!iso) return '—';
  return fmtFecha ? fmtFecha(iso) : iso;
}

function filaResultado(u) {
  const tr = document.createElement('tr');
  const alias = u.alias || u.email;
  tr.innerHTML =
    '<td>' + esc(alias) + (u.es_admin ? ' <span class="muted">(admin)</span>' : '') + '</td>' +
    '<td>' + esc(u.email) + '</td>' +
    '<td>' + esc(u.email_verificado_at ? I18N_ADMIN_LEGAL.si : I18N_ADMIN_LEGAL.no) + '</td>' +
    '<td>' + esc(String(u.aceptaciones ?? 0)) + '</td>' +
    '<td>' + esc(fmtLegal(u.primera_aceptacion)) + '</td>' +
    '<td>' + esc(fmtLegal(u.ultima_aceptacion)) + '</td>' +
    '<td></td>';
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.textContent = I18N_ADMIN_LEGAL.verExpediente;
  btn.onclick = () => cargarExpediente(u.id, alias);
  tr.lastElementChild.appendChild(btn);
  return tr;
}

async function buscarLegal(q) {
  const r = await api('/api/admin/legal/buscar?q=' + encodeURIComponent(q));
  if (!r.ok) return alert(r.error);
  const lista = r.resultados || [];
  const tabla = document.getElementById('tabla-legal-resultados');
  const vacio = document.getElementById('legal-sin-resultados');
  const tb = tabla.querySelector('tbody');
  tb.innerHTML = '';
  document.getElementById('panel-expediente').hidden = true;
  expedienteActivo = null;
  if (!lista.length) {
    tabla.hidden = true;
    vacio.hidden = false;
    return;
  }
  vacio.hidden = true;
  tabla.hidden = false;
  lista.forEach((u) => tb.appendChild(filaResultado(u)));
}

function renderEvento(a, n) {
  const div = document.createElement('div');
  div.className = 'admin-legal-evento';
  let extra = '<ul class="admin-legal-detalles">';
  extra += '<li><strong>' + esc(a.canal_etiqueta || a.canal) + '</strong> · ' + esc(fmtLegal(a.momento)) + '</li>';
  extra += '<li>' + esc(a.texto_casilla) + '</li>';
  extra += '<li>Condiciones ' + esc(a.condiciones_version) + ' · Privacidad ' + esc(a.privacidad_version) + '</li>';
  if (a.ip) extra += '<li>IP: ' + esc(a.ip) + '</li>';
  if (a.centro_codigo) extra += '<li>Centro: ' + esc(a.centro_codigo + ' — ' + (a.centro_nombre || '')) + '</li>';
  if (a.persona_iniciales) extra += '<li>Persona: ' + esc(a.persona_iniciales + ' ' + (a.persona_nombre || '')) + '</li>';
  extra += '</ul>';
  div.innerHTML = '<h3>#' + n + '</h3>' + extra;
  return div;
}

async function cargarExpediente(id, alias) {
  const r = await api('/api/admin/legal/expediente/' + id);
  if (!r.ok) return alert(r.error);
  expedienteActivo = { id, alias, datos: r };
  document.getElementById('panel-expediente').hidden = false;
  document.getElementById('expediente-titulo').textContent = alias;
  const u = r.usuario || {};
  const verificado = u.email_verificado_at
    ? I18N_ADMIN_LEGAL.verificado + ' ' + fmtLegal(u.email_verificado_at)
    : I18N_ADMIN_LEGAL.no;
  document.getElementById('expediente-resumen').textContent =
    u.email + ' · ID ' + u.id + ' · ' + verificado + ' · ' +
    (r.aceptaciones || []).length + ' ' + I18N_ADMIN_LEGAL.aceptaciones;
  const cont = document.getElementById('expediente-eventos');
  cont.innerHTML = '';
  const eventos = r.aceptaciones || [];
  if (!eventos.length) {
    cont.innerHTML = '<p class="muted">' + esc(I18N_ADMIN_LEGAL.sinRegistros) + '</p>';
  } else {
    eventos.forEach((a, i) => cont.appendChild(renderEvento(a, i + 1)));
  }
  document.getElementById('panel-expediente').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function descargarExpediente(formato) {
  if (!expedienteActivo) return;
  const url = '/api/admin/legal/expediente/' + expedienteActivo.id + '/export';
  if (formato === 'html') {
    window.location.href = url;
    return;
  }
  const btn = document.getElementById('btn-expediente-pdf');
  if (btn) btn.disabled = true;
  try {
    const resp = await fetch(url, { headers: { 'Accept': 'text/html' } });
    if (!resp.ok) {
      const err = await resp.json().catch(() => null);
      throw new Error(err?.error || I18N_ADMIN_LEGAL.pdfError);
    }
    const html = await resp.text();
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const cont = document.getElementById('expediente-pdf');
    cont.innerHTML = doc.body.innerHTML;
    if (typeof html2pdf === 'undefined') {
      window.open(url, '_blank');
      return;
    }
    const fecha = (expedienteActivo.datos.generado || '').slice(0, 10).replace(/-/g, '') || 'fecha';
    const nombre = 'expediente-legal-' + (expedienteActivo.alias || 'usuario').replace(/[^A-Za-z0-9._-]+/g, '_') + '-' + fecha + '.pdf';
    await html2pdf().set({
      margin: [10, 10, 12, 10],
      filename: nombre,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2, useCORS: true, letterRendering: true },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
      pagebreak: { mode: ['avoid-all', 'css', 'legacy'] },
    }).from(cont).save();
  } catch (e) {
    alert(e.message || I18N_ADMIN_LEGAL.pdfError);
  } finally {
    if (btn) btn.disabled = false;
  }
}

document.getElementById('form-buscar-legal').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const q = document.getElementById('legal-q').value.trim();
  if (q.length < 2 && !/^\d+$/.test(q)) return;
  await buscarLegal(q);
});
document.getElementById('btn-expediente-pdf').addEventListener('click', () => descargarExpediente('pdf'));
document.getElementById('btn-expediente-html').addEventListener('click', () => descargarExpediente('html'));
</script>
