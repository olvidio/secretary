<h1><?= _("Comprobaciones personales / generales") ?></h1>
<p class="muted saldos-ayuda">
    <?= _("Contrasta apuntes A de Personales con los de Generales. P/211 (vivienda general) cuadra con G/11; P/212 es vivienda personal y no entra aquí. En Nombres se indica quién entra en el cierre automático; quien no aporta puede igualmente imputar a generales puntualmente (211/11). También avisa quién no ha anotado movimiento en el mes de cierre o en los anteriores (la exención de Nombres deja fuera esos meses).") ?>
</p>
<form id="form-comp" class="filters">
    <label><?= _("Hasta") ?> <input type="date" name="hasta"></label>
    <button type="submit"><?= _("Ejecutar") ?></button>
</form>
<section id="comprobaciones" class="comprobaciones-saldos">
    <p id="comp-resumen" class="muted"></p>
    <div id="comp-lista"></div>
</section>
<script>
const I18N_COMP = {
  todoOk: <?= json_encode(_("Todo correcto a fecha %s."), JSON_UNESCAPED_UNICODE) ?>,
  incidencias: <?= json_encode(_("Hay incidencias a fecha %s."), JSON_UNESCAPED_UNICODE) ?>,
  persona: <?= json_encode(_("Persona"), JSON_UNESCAPED_UNICODE) ?>,
  mesesSinMov: <?= json_encode(_("Meses sin movimiento"), JSON_UNESCAPED_UNICODE) ?>,
  apuntes: <?= json_encode(_("Apuntes"), JSON_UNESCAPED_UNICODE) ?>,
  aportaG: <?= json_encode(_("Aporta a G"), JSON_UNESCAPED_UNICODE) ?>,
  si: <?= json_encode(_("sí"), JSON_UNESCAPED_UNICODE) ?>,
  no: <?= json_encode(_("no"), JSON_UNESCAPED_UNICODE) ?>,
  diferencia: <?= json_encode(_("Diferencia"), JSON_UNESCAPED_UNICODE) ?>,
  partida: <?= json_encode(_("Partida"), JSON_UNESCAPED_UNICODE) ?>,
  importe: <?= json_encode(_("Importe"), JSON_UNESCAPED_UNICODE) ?>,
  asignacion: <?= json_encode(_("Asignación"), JSON_UNESCAPED_UNICODE) ?>,
  otroApunte: <?= json_encode(_("Otro apunte"), JSON_UNESCAPED_UNICODE) ?>,
  numero: <?= json_encode(_("nº"), JSON_UNESCAPED_UNICODE) ?>,
  tipos: {
    remesa: <?= json_encode(_("remesa"), JSON_UNESCAPED_UNICODE) ?>,
    normal: <?= json_encode(_("apunte"), JSON_UNESCAPED_UNICODE) ?>,
  },
  anular: <?= json_encode(_("Anular asignación"), JSON_UNESCAPED_UNICODE) ?>,
  confirmarAnular: <?= json_encode(_("Se anulará la asignación de %s € a la partida %s de %s del %s y su liquidación de c/c. El gasto queda una sola vez (el del otro apunte), el 111 no cambia y la c/c recupera %s €. ¿Seguir?"), JSON_UNESCAPED_UNICODE) ?>,
  anulada: <?= json_encode(_("Asignación anulada (%s €)."), JSON_UNESCAPED_UNICODE) ?>,
};
function tablaLaboresDuplicadas(c, hasta) {
  let html = '<table class="comprobacion-personas"><thead><tr>'
    + '<th>' + esc(I18N_COMP.persona) + '</th><th>' + esc(I18N_COMP.partida) + '</th>'
    + '<th class="num">' + esc(I18N_COMP.importe) + '</th><th>' + esc(I18N_COMP.asignacion) + '</th>'
    + '<th>' + esc(I18N_COMP.otroApunte) + '</th><th></th></tr></thead><tbody>';
  c.duplicados.forEach((d) => {
    const q = new URLSearchParams({ cuenta: 'P', iniciales: d.iniciales, concepto: d.codigo, from: '/comprobaciones' });
    if (hasta) q.set('hasta', hasta);
    const tipo = I18N_COMP.tipos[d.otro.tipo] || d.otro.tipo;
    html += `<tr class="saldos-alerta">
      <td>${esc(d.nombre)} (${esc(d.iniciales)})</td>
      <td>${esc(d.codigo)}</td>
      <td class="num">${esc(d.importe_es)}</td>
      <td>${esc(fmtFecha(d.asignacion.fecha))} · ${esc(I18N_COMP.numero)} ${esc(String(d.asignacion.numero))}</td>
      <td>${esc(fmtFecha(d.otro.fecha))} · ${esc(tipo)} ${esc(I18N_COMP.numero)} ${esc(String(d.otro.numero))}</td>
      <td><a href="/apuntes?${q.toString()}">${esc(I18N_COMP.apuntes)}</a>
        · <button type="button" class="btn-anular-asignacion" data-id="${esc(String(d.asignacion.asiento_id))}"
            data-importe="${esc(d.importe_es)}" data-codigo="${esc(d.codigo)}" data-iniciales="${esc(d.iniciales)}"
            data-fecha="${esc(fmtFecha(d.asignacion.fecha))}">${esc(I18N_COMP.anular)}</button></td>
    </tr>`;
  });
  return html + '</tbody></table>';
}
function tablaPersonasComprobacion(c, hasta) {
  if (c.id === 'meses_sin_movimiento') {
    let html = '<table class="comprobacion-personas"><thead><tr>'
      + '<th>' + esc(I18N_COMP.persona) + '</th><th>' + esc(I18N_COMP.mesesSinMov) + '</th><th></th></tr></thead><tbody>';
    c.personas.forEach((p) => {
      const q = new URLSearchParams({ iniciales: p.iniciales, from: '/comprobaciones' });
      if (hasta) q.set('hasta', hasta);
      html += `<tr class="saldos-alerta">
        <td>${esc(p.nombre)} (${esc(p.iniciales)})</td>
        <td>${esc((p.meses_es || []).join(', '))}</td>
        <td><a href="/apuntes?${q.toString()}">${esc(I18N_COMP.apuntes)}</a></td>
      </tr>`;
    });
    return html + '</tbody></table>';
  }
  let html = '<table class="comprobacion-personas"><thead><tr>'
    + '<th>' + esc(I18N_COMP.persona) + '</th><th>' + esc(I18N_COMP.aportaG) + '</th><th class="num">P/211</th>'
    + '<th class="num">G/11</th><th class="num">' + esc(I18N_COMP.diferencia) + '</th><th></th></tr></thead><tbody>';
  c.personas.forEach((p) => {
    const q = new URLSearchParams({
      cuenta: 'P',
      origen: 'A',
      iniciales: p.iniciales,
      concepto: '211',
      from: '/comprobaciones',
    });
    if (hasta) q.set('hasta', hasta);
    const qg = new URLSearchParams({
      cuenta: 'G',
      origen: 'A',
      iniciales: p.iniciales,
      concepto: '11',
      from: '/comprobaciones',
    });
    if (hasta) qg.set('hasta', hasta);
    html += `<tr class="${p.ok ? '' : 'saldos-alerta'}">
      <td>${esc(p.nombre)} (${esc(p.iniciales)})</td>
      <td>${p.aporta ? esc(I18N_COMP.si) : esc(I18N_COMP.no)}</td>
      <td class="num">${esc(p.p21_es)}</td>
      <td class="num">${esc(p.g11_es)}</td>
      <td class="num">${esc(p.diferencia_es)}</td>
      <td><a href="/apuntes?${q.toString()}">P/211</a>
        · <a href="/apuntes?${qg.toString()}">G/11</a></td>
    </tr>`;
  });
  return html + '</tbody></table>';
}

function renderComprobaciones(data) {
  const res = document.getElementById('comp-resumen');
  const lista = document.getElementById('comp-lista');
  res.textContent = data.ok
    ? I18N_COMP.todoOk.replace('%s', fmtFecha(data.hasta))
    : I18N_COMP.incidencias.replace('%s', fmtFecha(data.hasta));
  lista.innerHTML = '';
  (data.comprobaciones || []).forEach((c) => {
    const art = document.createElement('article');
    art.className = 'comprobacion-item ' + (c.ok ? 'ok' : 'fail');
    let html = `<h3>${esc(c.titulo)} <span class="comprobacion-estado">${c.ok ? '✓' : '✗'}</span></h3>
      <p>${esc(c.mensaje)}</p>`;
    if (c.ayuda) {
      html += `<p class="comprobacion-ayuda">${esc(c.ayuda)}</p>`;
    }
    (c.avisos || []).forEach((a) => {
      html += `<p class="error">${esc(a)}</p>`;
    });
    if (c.personas && c.personas.length) {
      html += tablaPersonasComprobacion(c, data.hasta);
    }
    if (c.duplicados && c.duplicados.length) {
      html += tablaLaboresDuplicadas(c, data.hasta);
    }
    art.innerHTML = html;
    lista.appendChild(art);
  });
}

document.addEventListener('DOMContentLoaded', async () => {
  const cfg = await api('/api/configuracion');
  document.querySelector('[name=hasta]').value = cfg.config.fecha_cierre;
  async function load() {
    const hasta = document.querySelector('[name=hasta]').value;
    const r = await api('/api/informes/comprobaciones?hasta=' + encodeURIComponent(hasta));
    if (!r.ok && r.error) return alert(r.error);
    renderComprobaciones(r);
  }
  document.getElementById('form-comp').onsubmit = (ev) => { ev.preventDefault(); load(); };
  document.getElementById('comp-lista').addEventListener('click', async (ev) => {
    const btn = ev.target.closest('.btn-anular-asignacion');
    if (!btn) return;
    const imp = btn.dataset.importe;
    const pregunta = I18N_COMP.confirmarAnular
      .replace('%s', imp).replace('%s', btn.dataset.codigo).replace('%s', btn.dataset.iniciales)
      .replace('%s', btn.dataset.fecha).replace('%s', imp);
    if (!confirm(pregunta)) return;
    btn.disabled = true;
    const r = await api('/api/disponible/asientos-asignacion/' + encodeURIComponent(btn.dataset.id) + '/anular-duplicada', { method: 'POST', body: {} });
    if (!r.ok) {
      btn.disabled = false;
      return alert(r.error || 'Error');
    }
    alert(I18N_COMP.anulada.replace('%s', r.importe_es));
    load();
  });
  load();
});
</script>
