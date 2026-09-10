<h1>Comprobaciones personales / generales</h1>
<p class="muted saldos-ayuda">
    Contrasta apuntes A de Personales con los de Generales. En Nombres se indica,
    persona a persona, si la vivienda (P/21) se aporta a generales (G/11).
    Eso no depende de si el centro es n o agd: puede haber de los dos en el mismo libro.
    También avisa quién no ha anotado movimiento en el mes de cierre o en los anteriores
    (la exención de Nombres deja fuera esos meses).
</p>
<form id="form-comp" class="filters">
    <label>Hasta <input type="date" name="hasta"></label>
    <button type="submit">Ejecutar</button>
</form>
<section id="comprobaciones" class="comprobaciones-saldos">
    <p id="comp-resumen" class="muted"></p>
    <div id="comp-lista"></div>
</section>
<script>
function tablaPersonasComprobacion(c, hasta) {
  if (c.id === 'meses_sin_movimiento') {
    let html = '<table class="comprobacion-personas"><thead><tr>'
      + '<th>Persona</th><th>Meses sin movimiento</th><th></th></tr></thead><tbody>';
    c.personas.forEach((p) => {
      const q = new URLSearchParams({ iniciales: p.iniciales, from: '/comprobaciones' });
      if (hasta) q.set('hasta', hasta);
      html += `<tr class="saldos-alerta">
        <td>${esc(p.nombre)} (${esc(p.iniciales)})</td>
        <td>${esc((p.meses_es || []).join(', '))}</td>
        <td><a href="/apuntes?${q.toString()}">Apuntes</a></td>
      </tr>`;
    });
    return html + '</tbody></table>';
  }
  let html = '<table class="comprobacion-personas"><thead><tr>'
    + '<th>Persona</th><th>Aporta a G</th><th class="num">P/21</th>'
    + '<th class="num">G/11</th><th class="num">Diferencia</th><th></th></tr></thead><tbody>';
  c.personas.forEach((p) => {
    const q = new URLSearchParams({
      cuenta: 'P',
      origen: 'A',
      iniciales: p.iniciales,
      concepto: '21',
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
      <td>${p.aporta ? 'sí' : 'no'}</td>
      <td class="num">${esc(p.p21_es)}</td>
      <td class="num">${esc(p.g11_es)}</td>
      <td class="num">${esc(p.diferencia_es)}</td>
      <td><a href="/apuntes?${q.toString()}">P/21</a>
        · <a href="/apuntes?${qg.toString()}">G/11</a></td>
    </tr>`;
  });
  return html + '</tbody></table>';
}

function renderComprobaciones(data) {
  const res = document.getElementById('comp-resumen');
  const lista = document.getElementById('comp-lista');
  res.textContent = data.ok
    ? 'Todo correcto a fecha ' + fmtFecha(data.hasta) + '.'
    : 'Hay incidencias a fecha ' + fmtFecha(data.hasta) + '.';
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
  load();
});
</script>
