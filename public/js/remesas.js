document.addEventListener('DOMContentLoaded', () => {
  const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  const filtros = document.getElementById('filtros-remesas');
  const err = document.getElementById('remesas-err');
  const tb = document.querySelector('#tabla-remesas tbody');
  const detalle = document.getElementById('remesa-detalle');
  let actualId = null;

  function mostrarError(msg) {
    err.textContent = msg || '';
    err.hidden = !msg;
  }

  async function cargarLista() {
    mostrarError('');
    const q = new URLSearchParams(formObj(filtros));
    const r = await api('/api/remesas?' + q.toString());
    tb.innerHTML = '';
    if (!r.ok) {
      mostrarError(r.error || 'No se pudieron cargar las remesas');
      return;
    }
    (r.remesas || []).forEach((m) => {
      const tr = document.createElement('tr');
      const enviada = m.enviada_at ? fmtFecha(String(m.enviada_at).slice(0, 10)) : '';
      tr.innerHTML = '<td>' + esc(MESES[(m.mes || 1) - 1] + ' ' + m.anio) + '</td>'
        + '<td>' + esc((m.iniciales || '') + ' · ' + (m.persona || '')) + '</td>'
        + '<td>' + esc(String(m.version)) + '</td>'
        + '<td>' + esc(m.estado) + '</td>'
        + '<td class="num">' + esc(m.total_es) + '</td>'
        + '<td>' + esc(enviada) + '</td>'
        + '<td><button type="button" data-id="' + m.id + '">Ver</button></td>';
      tr.querySelector('button').onclick = () => abrir(m.id);
      tb.appendChild(tr);
    });
  }

  async function abrir(id) {
    actualId = id;
    const r = await api('/api/remesas/' + id);
    if (!r.ok) {
      mostrarError(r.error || 'No se pudo abrir');
      return;
    }
    const m = r.remesa;
    detalle.hidden = false;
    document.getElementById('remesa-detalle-titulo').textContent =
      'Remesa ' + (m.iniciales || '') + ' · ' + MESES[(m.mes || 1) - 1] + ' ' + m.anio + ' v' + m.version;
    document.getElementById('remesa-detalle-meta').textContent = 'Estado: ' + m.estado
      + (m.nota ? ' · ' + m.nota : '');
    const tbL = document.querySelector('#tabla-remesa-lineas tbody');
    tbL.innerHTML = '';
    (m.lineas || []).forEach((l) => {
      const tr = document.createElement('tr');
      const sol = l.solicitud;
      let det = '';
      if (sol && sol.estado === 'autorizada') {
        det = '<button type="button" data-ver="' + l.id + '">Ver detalle</button>';
      } else if (sol && sol.estado === 'pendiente') {
        det = '<span class="muted">Pendiente de la persona</span>';
      } else {
        det = '<button type="button" data-sol="' + l.id + '">Solicitar detalle</button>';
      }
      tr.innerHTML = '<td>' + esc((l.codigo_maestro || '') + ' · ' + (l.nombre || '')) + '</td>'
        + '<td class="num">' + esc(l.importe_es) + '</td><td>' + det + '</td>';
      const btnSol = tr.querySelector('[data-sol]');
      if (btnSol) {
        btnSol.onclick = async () => {
          const s = await api('/api/remesas/' + id + '/lineas/' + l.id + '/solicitar', { method: 'POST', body: {} });
          if (!s.ok) { mostrarError(s.error || 'No se pudo solicitar'); return; }
          abrir(id);
        };
      }
      const btnVer = tr.querySelector('[data-ver]');
      if (btnVer) {
        btnVer.onclick = async () => {
          const d = await api('/api/remesas/' + id + '/lineas/' + l.id + '/detalle');
          if (!d.ok) { mostrarError(d.error || 'Sin detalle'); return; }
          const items = (d.detalle && d.detalle.detalle) || [];
          alert(items.map((x) => x.codigo + ' ' + x.nombre + ': ' + x.importe_es).join('\n') || 'Sin desglose');
        };
      }
      tbL.appendChild(tr);
    });
    const boxDiff = document.getElementById('remesa-diff');
    const ulDiff = document.getElementById('remesa-diff-list');
    const diffs = m.diff || [];
    boxDiff.hidden = diffs.length === 0;
    ulDiff.innerHTML = diffs.map((d) =>
      '<li>' + esc(d.codigo_maestro + ' · ' + d.nombre) + ': ' + esc(d.anterior_es)
      + ' → ' + esc(d.actual_es) + ' (' + esc(d.delta_es) + ')</li>'
    ).join('');
    const puede = m.estado === 'enviada' || m.estado === 'aceptada';
    document.getElementById('remesa-aceptar').hidden = m.estado !== 'enviada';
    document.getElementById('remesa-rechazar').hidden = !puede;
  }

  document.getElementById('remesa-aceptar').onclick = async () => {
    if (actualId == null) return;
    if (!confirm('¿Aceptar esta remesa? Sustituye los asientos de la versión aceptada anterior.')) return;
    const r = await api('/api/remesas/' + actualId + '/aceptar', { method: 'POST', body: {} });
    if (!r.ok) { mostrarError(r.error || 'No se pudo aceptar'); return; }
    await cargarLista();
    abrir(actualId);
  };
  document.getElementById('remesa-rechazar').onclick = async () => {
    if (actualId == null) return;
    if (!confirm('¿Rechazar esta remesa? Si ya estaba aceptada, se borran sus asientos.')) return;
    const nota = document.getElementById('remesa-nota').value;
    const r = await api('/api/remesas/' + actualId + '/rechazar', { method: 'POST', body: { nota } });
    if (!r.ok) { mostrarError(r.error || 'No se pudo rechazar'); return; }
    await cargarLista();
    abrir(actualId);
  };
  filtros.onsubmit = (ev) => { ev.preventDefault(); cargarLista(); };
  cargarLista();
});
