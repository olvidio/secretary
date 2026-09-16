document.addEventListener('DOMContentLoaded', () => {
  const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  const filtros = document.getElementById('filtros-remesas');
  const err = document.getElementById('remesas-err');
  const tb = document.querySelector('#tabla-remesas tbody');
  const detalle = document.getElementById('remesa-detalle');
  const panelDetLinea = document.getElementById('remesa-linea-detalle');
  let actualId = null;
  let lineaDetalleAbierta = null;

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
        + '<td class="num">' + esc(m.sobrante_es || '') + '</td>'
        + '<td>' + esc(enviada) + '</td>'
        + '<td><button type="button" data-id="' + m.id + '">Ver</button></td>';
      tr.querySelector('button').onclick = () => abrir(m.id);
      tb.appendChild(tr);
    });
  }

  function ocultarDetalleLinea() {
    lineaDetalleAbierta = null;
    if (panelDetLinea) panelDetLinea.hidden = true;
  }

  function textoExtrasDetalle(item) {
    const partes = [];
    (item.generales || []).forEach((g) => {
      partes.push('G/' + esc(g.concepto) + ' ' + esc(g.importe_es || ''));
    });
    (item.plantillas || []).forEach((p) => {
      partes.push(esc(p.nombre || ('Plantilla ' + p.plantilla_id)) + ' ' + esc(p.importe_es || ''));
    });
    return partes.join(', ');
  }

  async function mostrarDetalleLinea(remesaId, lineaId, etiqueta) {
    mostrarError('');
    const d = await api('/api/remesas/' + remesaId + '/lineas/' + lineaId + '/detalle');
    if (!d.ok) {
      mostrarError(d.error || 'Sin detalle');
      return;
    }
    const items = (d.detalle && d.detalle.detalle) || [];
    lineaDetalleAbierta = lineaId;
    if (!panelDetLinea) return;
    panelDetLinea.hidden = false;
    document.getElementById('remesa-linea-detalle-titulo').textContent =
      'Desglose: ' + (etiqueta || d.detalle.codigo_maestro || '');
    const tbDet = document.querySelector('#tabla-remesa-linea-detalle tbody');
    const vacio = document.getElementById('remesa-linea-detalle-vacio');
    tbDet.innerHTML = '';
    if (items.length === 0) {
      vacio.hidden = false;
      document.getElementById('tabla-remesa-linea-detalle').hidden = true;
      return;
    }
    vacio.hidden = true;
    document.getElementById('tabla-remesa-linea-detalle').hidden = false;
    items.forEach((x) => {
      const tr = document.createElement('tr');
      const extras = textoExtrasDetalle(x);
      tr.innerHTML = '<td>' + esc(x.codigo || '') + '</td>'
        + '<td>' + esc(x.nombre || '') + '</td>'
        + '<td class="num">' + esc(x.importe_es || '') + '</td>'
        + '<td>' + (extras ? extras : '<span class="muted">—</span>') + '</td>';
      tbDet.appendChild(tr);
    });
    panelDetLinea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  async function abrir(id) {
    if (actualId !== null && actualId !== id) ocultarDetalleLinea();
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
    const tes = document.getElementById('remesa-tesoreria');
    const wrapSust = document.getElementById('remesa-sustituir-wrap');
    if (m.saldo_tesoreria_es != null) {
      tes.hidden = false;
      tes.textContent = 'Tesorería enviada (caja+banco personal a la fecha de cierre): '
        + m.saldo_tesoreria_es + ' €';
      wrapSust.hidden = m.estado !== 'enviada';
      document.getElementById('remesa-sustituir').checked = false;
    } else {
      tes.hidden = true;
      wrapSust.hidden = true;
    }
    const tbL = document.querySelector('#tabla-remesa-lineas tbody');
    tbL.innerHTML = '';
    (m.lineas || []).forEach((l) => {
      const tr = document.createElement('tr');
      const sol = l.solicitud;
      let det = '';
      if (sol && sol.estado === 'autorizada') {
        const abierta = lineaDetalleAbierta === l.id;
        det = '<button type="button" data-ver="' + l.id + '">'
          + (abierta ? 'Ocultar detalle' : 'Ver detalle') + '</button>';
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
        const etiqueta = (l.codigo_maestro || '') + ' · ' + (l.nombre || '');
        btnVer.onclick = async () => {
          if (lineaDetalleAbierta === l.id) {
            ocultarDetalleLinea();
            btnVer.textContent = 'Ver detalle';
            return;
          }
          await mostrarDetalleLinea(id, l.id, etiqueta);
          tbL.querySelectorAll('[data-ver]').forEach((b) => {
            b.textContent = b.getAttribute('data-ver') === String(l.id)
              ? 'Ocultar detalle' : 'Ver detalle';
          });
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
    const r = await api('/api/remesas/' + actualId + '/aceptar', {
      method: 'POST',
      body: { sustituir_disponible: !!document.getElementById('remesa-sustituir')?.checked },
    });
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
  filtros.querySelector('[name=estado]').addEventListener('change', () => cargarLista());
  cargarLista();
});
