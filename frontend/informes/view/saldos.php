<h1><?= _("Saldos") ?></h1>
<p class="muted saldos-ayuda">
    <?= _("Por persona: cuadre de apuntes A, C y B (positivo = más ingresos que gastos; debería ser cero), saldo contable (concepto 9, no es el banco), deuda de vivienda del cierre y superávit de vivienda (lo ingresado por encima de la cuota). Saldar usa primero ese superávit y, si no alcanza, el saldo contable.") ?>
</p>
<form id="form-saldos" class="filters">
    <label><?= _("Hasta") ?> <input type="date" name="hasta"></label>
    <button type="submit"><?= _("Calcular") ?></button>
    <button type="button" id="btn-comprobaciones"><?= _("Ejecutar comprobaciones") ?></button>
</form>
<p id="tot"></p>
<h2><?= _("Tesorería física") ?></h2>
<table id="tabla-tesoreria">
    <thead>
    <tr><th><?= _("Cuenta") ?></th><th class="num">P</th><th class="num">G</th><th class="num"><?= _("Físico") ?></th></tr>
    </thead>
    <tbody></tbody>
</table>
<h2><?= _("Cuentas personales") ?></h2>
<table id="tabla-saldos">
    <thead>
    <tr>
        <th><?= _("Persona") ?></th>
        <th class="num"><?= _("A") ?></th>
        <th class="num"><?= _("C") ?></th>
        <th class="num"><?= _("B") ?></th>
        <th class="num"><?= _("Saldo cont.") ?></th>
        <th class="num"><?= _("Deuda viv.") ?></th>
        <th class="num"><?= _("Superávit") ?></th>
        <th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<section id="comprobaciones" class="comprobaciones-saldos" hidden>
    <h2><?= _("Comprobaciones") ?></h2>
    <p id="comprobaciones-resumen" class="muted"></p>
    <div id="comprobaciones-lista"></div>
</section>
<script>
const I18N_SALDOS = {
  todoOk: <?= json_encode(_("Todo correcto a fecha %s."), JSON_UNESCAPED_UNICODE) ?>,
  incidencias: <?= json_encode(_("Se han detectado incidencias a fecha %s."), JSON_UNESCAPED_UNICODE) ?>,
  valor: <?= json_encode(_("Valor:"), JSON_UNESCAPED_UNICODE) ?>,
  persona: <?= json_encode(_("Persona"), JSON_UNESCAPED_UNICODE) ?>,
  saldoCuenta: <?= json_encode(_("Saldo cuenta"), JSON_UNESCAPED_UNICODE) ?>,
  cuadreApuntes: <?= json_encode(_("Cuadre apuntes A"), JSON_UNESCAPED_UNICODE) ?>,
  detalle: <?= json_encode(_("Detalle"), JSON_UNESCAPED_UNICODE) ?>,
  apuntesSinCuadrar: <?= json_encode(_("Apuntes A sin cuadrar."), JSON_UNESCAPED_UNICODE) ?>,
  sugerencia: <?= json_encode(_(" Sugerencia: apunte P/A concepto "), JSON_UNESCAPED_UNICODE) ?>,
  verApuntesA: <?= json_encode(_("Ver apuntes A"), JSON_UNESCAPED_UNICODE) ?>,
  caja: <?= json_encode(_("Caja"), JSON_UNESCAPED_UNICODE) ?>,
  banco: <?= json_encode(_("Banco"), JSON_UNESCAPED_UNICODE) ?>,
  saldoCcGlobal: <?= json_encode(_("Saldo contable global (IX 613 P)"), JSON_UNESCAPED_UNICODE) ?>,
  colA: <?= json_encode(_("A"), JSON_UNESCAPED_UNICODE) ?>,
  colC: <?= json_encode(_("C"), JSON_UNESCAPED_UNICODE) ?>,
  colB: <?= json_encode(_("B"), JSON_UNESCAPED_UNICODE) ?>,
  colCc: <?= json_encode(_("Saldo cont."), JSON_UNESCAPED_UNICODE) ?>,
  saldar: <?= json_encode(_("Saldar"), JSON_UNESCAPED_UNICODE) ?>,
  confirmarSaldar: <?= json_encode(_("Saldar %s € de la deuda de vivienda de %s. Primero el superávit de vivienda; si no alcanza, el saldo contable. El resto sigue pendiente."), JSON_UNESCAPED_UNICODE) ?>,
  saldada: <?= json_encode(_("Deuda saldada: %s €. Queda %s €."), JSON_UNESCAPED_UNICODE) ?>,
};
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
    ? I18N_SALDOS.todoOk.replace('%s', fmtFecha(data.hasta))
    : I18N_SALDOS.incidencias.replace('%s', fmtFecha(data.hasta));
  lista.innerHTML = '';
  (data.comprobaciones || []).forEach((c) => {
    const art = document.createElement('article');
    art.className = 'comprobacion-item ' + (c.ok ? 'ok' : 'fail');
    let html = `<h3>${esc(c.titulo)} <span class="comprobacion-estado">${c.ok ? '✓' : '✗'}</span></h3>
      <p>${esc(c.mensaje)}</p>`;
    if (c.valor_es) {
      html += `<p class="muted">${esc(I18N_SALDOS.valor)} ${esc(c.valor_es)} €</p>`;
    }
    if (c.ayuda) {
      html += `<p class="comprobacion-ayuda">${esc(c.ayuda)}</p>`;
    }
    if (c.personas && c.personas.length) {
      html += '<table class="comprobacion-personas"><thead><tr>'
        + '<th>' + esc(I18N_SALDOS.persona) + '</th><th class="num">' + esc(I18N_SALDOS.saldoCuenta)
        + '</th><th class="num">' + esc(I18N_SALDOS.cuadreApuntes) + '</th><th>' + esc(I18N_SALDOS.detalle) + '</th></tr></thead><tbody>';
      c.personas.forEach((p) => {
        const sug = p.cuadre?.sugerencia;
        let detalle = '';
        if (!p.coherente && p.nota) {
          detalle += esc(p.nota);
        }
        if (p.cuadre && !p.cuadre.cuadrado) {
          if (detalle) detalle += ' ';
          detalle += I18N_SALDOS.apuntesSinCuadrar;
          if (sug) {
            detalle += I18N_SALDOS.sugerencia + esc(sug.concepto_codigo)
              + ' por ' + esc(sug.cantidad_es) + ' €.';
          }
          const q = new URLSearchParams({
            cuenta: 'P',
            origen: 'A',
            iniciales: p.iniciales,
          });
          if (data.hasta) q.set('hasta', data.hasta);
          q.set('from', '/saldos');
          detalle += ` <a href="/apuntes?${q.toString()}">${esc(I18N_SALDOS.verApuntesA)}</a>`;
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
      `${esc(I18N_SALDOS.caja)} <strong>${esc(r.caja_es)}</strong> · ${esc(I18N_SALDOS.banco)} <strong>${esc(r.banco_es)}</strong>`
      + ` · ${esc(I18N_SALDOS.saldoCcGlobal)} <strong>${esc(r.saldo_a_es)}</strong>`;
    const tb = document.querySelector('#tabla-saldos tbody');
    tb.innerHTML = '';
    (r.por_persona || []).forEach(p => {
      const tr = document.createElement('tr');
      const deuda = p.deuda_es || '0,00';
      const celdas = [
        p.saldo_apuntes_a_es || '0,00',
        p.saldo_apuntes_c_es || '0,00',
        p.saldo_apuntes_b_es || '0,00',
        p.saldo_cc_es || p.saldo_a_es || '0,00',
        deuda,
        p.superavit_es || '0,00',
      ];
      if (celdas.some((v) => Math.abs(parseSaldoNum(v)) > 0.001)) {
        tr.className = 'saldos-alerta';
      }
      const saldable = p.saldable_es || '0,00';
      const accion = parseSaldoNum(saldable) > 0.001
        ? `<button type="button" class="btn-saldar-deuda" data-iniciales="${esc(p.iniciales)}" data-saldable="${esc(saldable)}">${esc(I18N_SALDOS.saldar)} (${esc(saldable)})</button>`
        : '';
      tr.innerHTML = `<td>${esc(p.nombre)} (${esc(p.iniciales)})</td>`
        + celdas.map((v) => `<td class="num">${esc(v)}</td>`).join('')
        + `<td>${accion}</td>`;
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
  document.getElementById('tabla-saldos').onclick = async (ev) => {
    const btn = ev.target.closest('.btn-saldar-deuda');
    if (!btn) return;
    const iniciales = btn.getAttribute('data-iniciales') || '';
    const saldable = btn.getAttribute('data-saldable') || '';
    const pregunta = I18N_SALDOS.confirmarSaldar.replace('%s', saldable).replace('%s', iniciales);
    if (!confirm(pregunta)) return;
    const hasta = document.querySelector('[name=hasta]').value;
    const r = await api('/api/cierre/saldar-deuda', { method: 'POST', body: { iniciales, hasta } });
    if (!r.ok) return alert(r.error || 'Error');
    alert(I18N_SALDOS.saldada.replace('%s', r.saldado_es).replace('%s', r.deuda_restante_es));
    load();
  };
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
