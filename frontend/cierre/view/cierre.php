<h1>Apuntes de cierre de mes</h1>
<p>Reparte lo que falta por cubrir de los gastos G 201–215 del mes. Del total se resta lo ya pagado en G/11 ese mes (incluido quien no entra en el reparto). Lo pendiente se reparte entre quienes aportan a generales. Quien en el ejercicio ya lleva cubierta su cuota acumulada no recibe cierre este mes; el hueco lo cubren quienes van más atrasados. El retraso de meses anteriores no se deshace. El cierre automático de este mes no cuenta porque se regenera. Observaciones: «automático».</p>
<p id="resumen" class="muted"></p>
<div id="aviso-faltantes" class="aviso-cierre" hidden>
  <p><strong>Faltan apuntes de vivienda en meses anteriores:</strong> <span id="lista-faltantes"></span>.</p>
  <button type="button" id="btn-regularizar">Regularizar meses anteriores</button>
</div>
<p id="msg-faltantes" class="ok" hidden></p>
<table id="prev">
    <thead><tr><th>Iniciales</th><th>Nombre</th><th class="num">Cuota</th><th class="num">Ya imputado (ejercicio)</th><th class="num">A generar</th></tr></thead>
    <tbody></tbody>
</table>
<button type="button" id="btn-gen">Generar apuntes</button>
<p id="msg" class="ok" hidden></p>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/cierre');
  if (!r.ok) return alert(r.error || 'Error');
  let resumen = 'Tipo ' + r.tipo_cierre + ' · gastos G 201–215 ' + r.gastos_generales_es + ' € · mes ' + r.mes;
  if (r.aportaciones_externas_es && r.aportaciones_externas_es !== '0,00') {
    resumen += ' · ya aportado fuera del reparto ' + r.aportaciones_externas_es + ' €';
  }
  resumen += ' · pendiente ' + (r.restante_es ?? r.base_reparto_es ?? r.gastos_generales_es) + ' €';
  document.getElementById('resumen').textContent = resumen;
  const faltantes = (r.meses_faltantes && r.meses_faltantes.meses) || [];
  if (faltantes.length) {
    document.getElementById('aviso-faltantes').hidden = false;
    document.getElementById('lista-faltantes').textContent = faltantes
      .map(m => m.mes_es + ' (' + m.gastos_es + ' €)')
      .join(', ');
  }
  const tb = document.querySelector('#prev tbody');
  (r.lineas || []).forEach(l => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${esc(l.iniciales)}</td><td>${esc(l.nombre)}</td>`
      + `<td class="num">${esc(l.importe_bruto_es)}</td>`
      + `<td class="num">${esc(l.ya_imputado_es)}</td>`
      + `<td class="num">${esc(l.importe_es)}</td>`;
    tb.appendChild(tr);
  });
  document.getElementById('btn-regularizar').onclick = async () => {
    const lista = faltantes.map(m => m.mes_es).join(', ');
    if (!confirm('¿Generar los apuntes de vivienda de ' + lista + '?')) return;
    const btn = document.getElementById('btn-regularizar');
    btn.disabled = true;
    const s = await api('/api/cierre/regularizar', { method: 'POST', body: {} });
    btn.disabled = false;
    const msg = document.getElementById('msg-faltantes');
    msg.hidden = false;
    if (!s.ok) {
      msg.textContent = s.error || 'Error';
      return;
    }
    if (s.mensaje) {
      msg.textContent = s.mensaje;
      return;
    }
    const detalle = (s.meses || []).map(m => m.mes_es + ' (' + m.creados + ' apuntes)').join(', ');
    msg.textContent = 'Regularizados ' + s.creados + ' apuntes: ' + detalle + '.';
    document.getElementById('aviso-faltantes').hidden = true;
  };
  document.getElementById('btn-gen').onclick = async () => {
    if (!confirm('¿Borrar el cierre de este mes y generar de nuevo?')) return;
    const s = await api('/api/cierre', {method:'POST', body:{}});
    document.getElementById('msg').hidden = false;
    document.getElementById('msg').textContent = s.ok ? ('Creados ' + s.creados + ' apuntes') : s.error;
  };
});
</script>
