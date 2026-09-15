document.addEventListener('DOMContentLoaded', () => {
  const err = document.getElementById('disp-err');
  const ok = document.getElementById('disp-ok');
  const tb = document.querySelector('#tabla-disponible tbody');
  const propuesta = document.getElementById('propuesta');
  let asignacionId = null;

  function mostrarError(msg) {
    err.textContent = msg || '';
    err.hidden = !msg;
    if (msg) ok.hidden = true;
  }
  function mostrarOk(msg) {
    ok.textContent = msg || '';
    ok.hidden = !msg;
    if (msg) err.hidden = true;
  }

  async function cargar() {
    mostrarError('');
    const r = await api('/api/disponible');
    tb.innerHTML = '';
    if (!r.ok) {
      mostrarError(r.error || 'No se pudo cargar');
      return;
    }
    (r.personas || []).forEach((p) => {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td>' + esc((p.iniciales || '') + ' · ' + (p.nombre || '')) + '</td>'
        + '<td>' + (p.puede_desgravar ? 'sí' : 'no') + '</td>'
        + '<td class="num">' + esc(p.saldo_es) + '</td>'
        + '<td><button type="button" data-id="' + p.persona_id + '" data-saldo="' + esc(p.saldo) + '">Ajustar</button></td>';
      tr.querySelector('button').onclick = async () => {
        const v = prompt('Nuevo disponible de ' + p.iniciales + ' (€)', p.saldo);
        if (v === null) return;
        const s = await api('/api/disponible/ajustar', {
          method: 'POST',
          body: { persona_id: p.persona_id, saldo: v },
        });
        if (!s.ok) return alert(s.error || 'No se pudo ajustar');
        mostrarOk('Disponible actualizado');
        cargar();
      };
      tb.appendChild(tr);
    });
  }

  document.getElementById('btn-proponer').onclick = async () => {
    mostrarError('');
    const r = await api('/api/disponible/proponer', { method: 'POST', body: {} });
    if (!r.ok) {
      mostrarError(r.error || 'No se pudo proponer');
      return;
    }
    asignacionId = r.asignacion_id;
    propuesta.hidden = false;
    document.getElementById('propuesta-meta').textContent = asignacionId
      ? 'Borrador #' + asignacionId + '. Revisar y confirmar para apuntar las 7.'
      : 'Nadie tiene disponible que aplicar.';
    const tbP = document.querySelector('#tabla-propuesta tbody');
    tbP.innerHTML = '';
    (r.personas || []).forEach((p) => {
      const tr = document.createElement('tr');
      const imps = (p.lineas || []).map((l) => l.importe_es + ' · ' + l.codigo).join('; ');
      tr.innerHTML = '<td>' + esc((p.iniciales || '') + ' · ' + (p.nombre || '')) + '</td>'
        + '<td>' + esc(p.texto || '') + '</td>'
        + '<td class="num">' + esc(imps) + '</td>';
      tbP.appendChild(tr);
    });
    document.getElementById('btn-confirmar').hidden = !asignacionId;
  };

  document.getElementById('btn-confirmar').onclick = async () => {
    if (!asignacionId) return;
    if (!confirm('¿Confirmar y anotar las partidas 7 en el libro P?')) return;
    const r = await api('/api/disponible/asignaciones/' + asignacionId + '/confirmar', {
      method: 'POST',
      body: {},
    });
    if (!r.ok) {
      mostrarError(r.error || 'No se pudo confirmar');
      return;
    }
    mostrarOk('Anotado. La persona verá el texto en su remesa.');
    asignacionId = null;
    propuesta.hidden = true;
    cargar();
  };

  cargar();
});
