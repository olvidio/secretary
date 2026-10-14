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

  async function cargarInstrucciones() {
    const tbI = document.querySelector('#tabla-instrucciones tbody');
    const vacI = document.getElementById('instr-vacio');
    const tbl = document.getElementById('tabla-instrucciones');
    if (!tbI) return;
    const r = await api('/api/disponible/instrucciones-pendientes');
    tbI.innerHTML = '';
    const lista = r.ok ? (r.instrucciones || []) : [];
    if (vacI) vacI.hidden = lista.length > 0;
    if (tbl) tbl.hidden = lista.length === 0;
    lista.forEach((ins) => {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td>' + esc((ins.iniciales || '') + ' · ' + (ins.nombre || '')) + '</td>'
        + '<td>' + esc(ins.texto || '') + '</td>'
        + '<td><button type="button" class="del">' + esc(t('quitar')) + '</button></td>';
      tr.querySelector('button').onclick = async () => {
        if (!confirm(t('instruccion_quitar_confirm'))) return;
        const q = await api('/api/disponible/instrucciones-pendientes/descartar', {
          method: 'POST',
          body: { persona_id: ins.persona_id },
        });
        if (!q.ok) return alert(q.error || t('error'));
        mostrarOk(t('instruccion_retirada'));
        cargar();
        cargarInstrucciones();
      };
      tbI.appendChild(tr);
    });
  }

  async function cargar() {
    mostrarError('');
    const r = await api('/api/disponible');
    tb.innerHTML = '';
    if (!r.ok) {
      mostrarError(r.error || t('no_se_pudo_cargar'));
      return;
    }
    (r.personas || []).forEach((p) => {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td>' + esc((p.iniciales || '') + ' · ' + (p.nombre || '')) + '</td>'
        + '<td>' + (p.puede_desgravar ? 'sí' : 'no') + '</td>'
        + '<td class="num">' + esc(p.saldo_es) + '</td>'
        + '<td><button type="button" data-id="' + p.persona_id + '" data-saldo="' + esc(p.saldo) + '">Ajustar</button></td>';
      tr.querySelector('button').onclick = async () => {
        const v = prompt(t('nuevo_disponible_de') + ' ' + p.iniciales + ' (€)', p.saldo);
        if (v === null) return;
        const s = await api('/api/disponible/ajustar', {
          method: 'POST',
          body: { persona_id: p.persona_id, saldo: v },
        });
        if (!s.ok) return alert(s.error || t('no_se_pudo_ajustar'));
        mostrarOk(t('disponible_actualizado'));
        cargar();
      };
      tb.appendChild(tr);
    });
    cargarInstrucciones();
  }

  document.getElementById('btn-proponer').onclick = async () => {
    mostrarError('');
    const r = await api('/api/disponible/proponer', { method: 'POST', body: {} });
    if (!r.ok) {
      mostrarError(r.error || t('no_se_pudo_proponer'));
      return;
    }
    asignacionId = r.asignacion_id;
    propuesta.hidden = false;
    document.getElementById('propuesta-meta').textContent = asignacionId
      ? t('propuesta_borrador') + asignacionId + t('propuesta_revisar_7')
      : t('nadie_disponible_aplicar');
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
    if (!confirm(t('confirmar_partidas_7'))) return;
    const r = await api('/api/disponible/asignaciones/' + asignacionId + '/confirmar', {
      method: 'POST',
      body: {},
    });
    if (!r.ok) {
      mostrarError(r.error || t('no_se_pudo_confirmar'));
      return;
    }
    mostrarOk(t('instrucciones_persona_remesa'));
    asignacionId = null;
    propuesta.hidden = true;
    cargar();
  };

  cargar();
});
