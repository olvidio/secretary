document.addEventListener('DOMContentLoaded', () => {
  const err = document.getElementById('envio-dl-err');
  const ok = document.getElementById('envio-dl-ok');
  const propuesta = document.getElementById('propuesta-envio-dl');
  let envioId = null;

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

  document.getElementById('form-envio-dl').onsubmit = async (e) => {
    e.preventDefault();
    mostrarError('');
    const importe = document.getElementById('inp-importe').value.trim();
    const r = await api('/api/envio-dl/proponer', { method: 'POST', body: { importe } });
    if (!r.ok) {
      mostrarError(r.error || 'No se pudo proponer');
      return;
    }
    envioId = r.envio_id;
    propuesta.hidden = false;
    const meta = envioId
      ? 'Borrador #' + envioId + '. Saldos hasta ' + (r.hasta || '') + '. Revisar y confirmar para anotar P/71 desde caja.'
      : 'Nadie entra en el reparto de este mes.';
    document.getElementById('propuesta-envio-meta').textContent = meta;
    const tb = document.querySelector('#tabla-propuesta-envio-dl tbody');
    tb.innerHTML = '';
    (r.personas || []).forEach((p) => {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td>' + esc((p.iniciales || '') + ' · ' + (p.nombre || '')) + '</td>'
        + '<td class="num">' + esc(p.saldo_es || '0,00') + '</td>'
        + '<td class="num">' + esc(p.importe_es) + '</td>'
        + '<td class="muted">P / 71 / Caja · ' + esc(p.importe_es) + '</td>';
      tb.appendChild(tr);
    });
    document.getElementById('propuesta-total').textContent = r.importe_total_es || '';
    document.getElementById('btn-confirmar-envio-dl').hidden = !envioId;
  };

  document.getElementById('btn-confirmar-envio-dl').onclick = async () => {
    if (!envioId) return;
    if (!confirm('¿Confirmar y anotar los gastos P/71 desde caja?')) return;
    const r = await api('/api/envio-dl/' + envioId + '/confirmar', { method: 'POST', body: {} });
    if (!r.ok) {
      mostrarError(r.error || 'No se pudo confirmar');
      return;
    }
    mostrarOk('Apuntado. Los movimientos aparecen en Apuntes.');
    envioId = null;
    propuesta.hidden = true;
    document.getElementById('inp-importe').value = '';
  };
});
