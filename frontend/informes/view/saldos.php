<h1>Saldos</h1>
<p class="muted saldos-ayuda">
    El saldo de apuntes A es el de la cuenta personal (libro P) de cada residente.
    Debería ser <strong>cero</strong>: cada gasto personal (origen A) va con su contrapartida,
    normalmente un ingreso 111 (Trabajo).
</p>
<form id="form-saldos" class="filters">
    <label>Hasta <input type="date" name="hasta"></label>
    <button type="submit">Calcular</button>
    <button type="button" id="btn-comprobaciones">Ejecutar comprobaciones</button>
</form>
<p id="tot"></p>
<h2>Tesorería física</h2>
<table id="tabla-tesoreria">
    <thead>
    <tr><th>Cuenta</th><th class="num">P</th><th class="num">G</th><th class="num">Físico</th></tr>
    </thead>
    <tbody></tbody>
</table>
<h2>Cuentas personales (apuntes A)</h2>
<table id="tabla-saldos">
    <thead><tr><th>Persona</th><th class="num">Saldo</th></tr></thead>
    <tbody></tbody>
</table>
<section id="comprobaciones" class="comprobaciones-saldos" hidden>
    <h2>Comprobaciones</h2>
    <p id="comprobaciones-resumen" class="muted"></p>
    <div id="comprobaciones-lista"></div>
</section>
<script>
function parseSaldoNum(es) {
  if (!es) return 0;
  const s = String(es).trim().replace(/\./g, '').replace(',', '.');
  const n = Number(s);
  return Number.isFinite(n) ? n : 0;
}

function renderComprobaciones(data) {
  const sec = document.getElementById('comprobaciones');
  const res = document.getElementById('comprobaciones-resumen');
  const lista = document.getElementById('comprobaciones-lista');
  sec.hidden = false;
  res.textContent = data.ok
    ? 'Todo correcto a fecha ' + fmtFecha(data.hasta) + '.'
    : 'Se han detectado incidencias a fecha ' + fmtFecha(data.hasta) + '.';
  lista.innerHTML = '';
  (data.comprobaciones || []).forEach((c) => {
    const art = document.createElement('article');
    art.className = 'comprobacion-item ' + (c.ok ? 'ok' : 'fail');
    let html = `<h3>${esc(c.titulo)} <span class="comprobacion-estado">${c.ok ? '✓' : '✗'}</span></h3>
      <p>${esc(c.mensaje)}</p>`;
    if (c.valor_es) {
      html += `<p class="muted">Valor: ${esc(c.valor_es)} €</p>`;
    }
    if (c.ayuda) {
      html += `<p class="comprobacion-ayuda">${esc(c.ayuda)}</p>`;
    }
    if (c.personas && c.personas.length) {
      html += '<table class="comprobacion-personas"><thead><tr>'
        + '<th>Persona</th><th class="num">Saldo cuenta</th><th class="num">Cuadre apuntes A</th><th>Detalle</th></tr></thead><tbody>';
      c.personas.forEach((p) => {
        const sug = p.cuadre?.sugerencia;
        let detalle = '';
        if (!p.coherente && p.nota) {
          detalle += esc(p.nota);
        }
        if (p.cuadre && !p.cuadre.cuadrado) {
          if (detalle) detalle += ' ';
          detalle += 'Apuntes A sin cuadrar.';
          if (sug) {
            detalle += ' Sugerencia: apunte P/A concepto ' + esc(sug.concepto_codigo)
              + ' por ' + esc(sug.cantidad_es) + ' €.';
          }
          const q = new URLSearchParams({
            cuenta: 'P',
            origen: 'A',
            iniciales: p.iniciales,
          });
          if (data.hasta) q.set('hasta', data.hasta);
          q.set('from', '/saldos');
          detalle += ` <a href="/apuntes?${q.toString()}">Ver apuntes A</a>`;
        }
        if (!detalle) detalle = p.coherente ? '—' : esc(p.nota || '');
        const cuadreEs = p.cuadre ? (p.cuadre.cuadrado ? '0,00' : esc(p.cuadre.saldo_apuntes_es)) : '—';
        html += `<tr>
          <td>${esc(p.nombre)} (${esc(p.iniciales)})</td>
          <td class="num">${esc(p.saldo_cuenta_es)}</td>
          <td class="num">${cuadreEs}</td>
          <td>${detalle}</td>
        </tr>`;
      });
      html += '</tbody></table>';
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
    const r = await api('/api/informes/saldos?hasta=' + hasta);
    document.getElementById('tot').innerHTML =
      `Caja <strong>${esc(r.caja_es)}</strong> · Banco <strong>${esc(r.banco_es)}</strong>`
      + ` · Saldo A global <strong>${esc(r.saldo_a_es)}</strong>`;
    const tb = document.querySelector('#tabla-saldos tbody');
    tb.innerHTML = '';
    (r.por_persona || []).forEach(p => {
      const tr = document.createElement('tr');
      if (Math.abs(parseSaldoNum(p.saldo_a_es)) > 0.001) {
        tr.className = 'saldos-alerta';
      }
      tr.innerHTML = `<td>${esc(p.nombre)} (${esc(p.iniciales)})</td><td class="num">${esc(p.saldo_a_es)}</td>`;
      tb.appendChild(tr);
    });
    const tr = await api('/api/informes/tesoreria?hasta=' + hasta);
    const tbt = document.querySelector('#tabla-tesoreria tbody');
    tbt.innerHTML = '';
    (tr.cuentas || []).forEach(c => {
      const row = document.createElement('tr');
      row.innerHTML = `<td>${esc(c.tipo)} · ${esc(c.nombre)}</td>
        <td class="num">${esc(c.saldo_p_es)}</td><td class="num">${esc(c.saldo_g_es)}</td>
        <td class="num">${esc(c.saldo_fisico_es)}</td>`;
      tbt.appendChild(row);
    });
  }
  document.getElementById('form-saldos').onsubmit = (ev) => { ev.preventDefault(); load(); };
  document.getElementById('btn-comprobaciones').onclick = async () => {
    const hasta = document.querySelector('[name=hasta]').value;
    const r = await api('/api/informes/comprobaciones-saldos?hasta=' + hasta);
    if (!r.ok && r.error) return alert(r.error);
    renderComprobaciones(r);
    document.getElementById('comprobaciones').scrollIntoView({ behavior: 'smooth', block: 'start' });
  };
  load();
});
</script>
