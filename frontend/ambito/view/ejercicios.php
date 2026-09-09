<h1>Ejercicios</h1>
<p>
    Alta de ejercicios de período libre (D11). <code>fecha_inicio</code>/<code>fecha_fin</code>
    delimitan el ejercicio contable completo; <code>fecha_corte</code> es sólo la fecha hasta la
    que hay datos introducidos (p. ej. para informes 613 a mitad de ejercicio) y puede coincidir
    con <code>fecha_fin</code> cuando el ejercicio ya está cerrado del todo.
</p>
<p class="muted">
    El concepto <strong>32</strong> del 613 G lo calcula la apertura automática al abrir el
    ejercicio siguiente. En el primer ejercicio importado (sin anterior) el disponible a 1 de enero
    se sigue tecleando a mano.
</p>
<form id="form-ejercicio" class="grid-form">
    <label>Etiqueta <input name="etiqueta" placeholder="p. ej. 2026 o 2026-27"></label>
    <label>Fecha inicio <input name="fecha_inicio" type="date" required></label>
    <label>Fecha fin <input name="fecha_fin" type="date" required></label>
    <label>Fecha de corte <input name="fecha_corte" type="date"></label>
    <button type="submit">Crear ejercicio</button>
</form>
<table id="tabla-ejercicios">
    <thead>
    <tr>
        <th>#</th><th>Etiqueta</th><th>Inicio</th><th>Fin</th><th>Corte</th>
        <th>Meses totales</th><th>Meses transcurridos</th><th>Estado</th><th>Acciones</th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<script>
async function loadEjercicios() {
  const r = await api('/api/ejercicios');
  const tb = document.querySelector('#tabla-ejercicios tbody');
  tb.innerHTML = '';
  if (!r.centro) {
    tb.innerHTML = '<tr><td colspan="9">No hay ningún centro dado de alta todavía.</td></tr>';
    return;
  }
  (r.ejercicios || []).forEach((e, i) => {
    const tr = document.createElement('tr');
    const acciones = [];
    if (e.puede_cerrar) {
      acciones.push(`<button type="button" data-accion="cerrar" data-id="${e.id}">Cerrar</button>`);
    }
    if (e.puede_reabrir) {
      acciones.push(`<button type="button" data-accion="reabrir" data-id="${e.id}">Reabrir</button>`);
    }
    if (e.puede_regenerar_apertura) {
      acciones.push(`<button type="button" data-accion="apertura" data-id="${e.id}">Regenerar apertura</button>`);
    }
    tr.innerHTML = `<td>${i+1}</td><td>${esc(e.etiqueta)}</td><td>${esc(e.fecha_inicio)}</td>
      <td>${esc(e.fecha_fin)}</td><td>${esc(e.fecha_corte)}</td>
      <td>${e.meses_totales}</td><td>${e.meses_transcurridos}</td><td>${esc(e.estado)}</td>
      <td class="acciones">${acciones.join(' ') || '—'}</td>`;
    tb.appendChild(tr);
  });
}
document.addEventListener('DOMContentLoaded', () => {
  loadEjercicios();
  document.getElementById('form-ejercicio').onsubmit = async (ev) => {
    ev.preventDefault();
    const s = await api('/api/ejercicios', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    ev.target.reset();
    loadEjercicios();
  };
  document.querySelector('#tabla-ejercicios tbody').addEventListener('click', async (ev) => {
    const btn = ev.target.closest('button[data-accion]');
    if (!btn) return;
    const id = btn.dataset.id;
    const accion = btn.dataset.accion;
    const textos = {
      cerrar: '¿Cerrar este ejercicio? No se podrán registrar más asientos.',
      reabrir: '¿Reabrir este ejercicio para correcciones?',
      apertura: '¿Regenerar los asientos de apertura? Se borrarán los actuales tipo apertura.',
    };
    if (!confirm(textos[accion] || '¿Continuar?')) return;
    const s = await api(`/api/ejercicios/${id}/${accion}`, {method:'POST'});
    if (!s.ok) return alert(s.error);
    loadEjercicios();
  });
});
</script>
