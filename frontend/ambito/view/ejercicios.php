<h1><?= _("Ejercicios") ?></h1>
<p>
    <?= _("Alta de ejercicios de período libre (D11). fecha_inicio/fecha_fin delimitan el ejercicio contable completo; fecha_corte es sólo la fecha hasta la que hay datos introducidos (p. ej. para informes 613 a mitad de ejercicio) y puede coincidir con fecha_fin cuando el ejercicio ya está cerrado del todo.") ?>
</p>
<p class="muted">
    <?= _("El concepto 32 del 613 G lo calcula la apertura automática al abrir el ejercicio siguiente. En el primer ejercicio importado (sin anterior) el disponible a 1 de enero se sigue tecleando a mano.") ?>
</p>
<form id="form-ejercicio" class="grid-form">
    <label><?= _("Etiqueta") ?> <input name="etiqueta" placeholder="<?= htmlspecialchars(_("p. ej. 2026 o 2026-27"), ENT_QUOTES) ?>"></label>
    <label><?= _("Fecha inicio") ?> <input name="fecha_inicio" type="date" required></label>
    <label><?= _("Fecha fin") ?> <input name="fecha_fin" type="date" required></label>
    <label><?= _("Fecha de corte") ?> <input name="fecha_corte" type="date"></label>
    <button type="submit"><?= _("Crear ejercicio") ?></button>
</form>
<table id="tabla-ejercicios">
    <thead>
    <tr>
        <th>#</th><th><?= _("Etiqueta") ?></th><th><?= _("Inicio") ?></th><th><?= _("Fin") ?></th><th><?= _("Corte") ?></th>
        <th><?= _("Meses totales") ?></th><th><?= _("Meses transcurridos") ?></th><th><?= _("Estado") ?></th><th><?= _("Acciones") ?></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<script>
const I18N_EJERCICIOS = {
  sinCentro: <?= json_encode(_("No hay ningún centro dado de alta todavía."), JSON_UNESCAPED_UNICODE) ?>,
  cerrar: <?= json_encode(_("Cerrar"), JSON_UNESCAPED_UNICODE) ?>,
  reabrir: <?= json_encode(_("Reabrir"), JSON_UNESCAPED_UNICODE) ?>,
  regenerar: <?= json_encode(_("Regenerar apertura"), JSON_UNESCAPED_UNICODE) ?>,
  confirmCerrar: <?= json_encode(_("¿Cerrar este ejercicio? No se podrán registrar más asientos."), JSON_UNESCAPED_UNICODE) ?>,
  confirmReabrir: <?= json_encode(_("¿Reabrir este ejercicio para correcciones?"), JSON_UNESCAPED_UNICODE) ?>,
  confirmApertura: <?= json_encode(_("¿Regenerar los asientos de apertura? Se borrarán los actuales tipo apertura."), JSON_UNESCAPED_UNICODE) ?>,
  continuar: <?= json_encode(_("¿Continuar?"), JSON_UNESCAPED_UNICODE) ?>,
};
async function loadEjercicios() {
  const r = await api('/api/ejercicios');
  const tb = document.querySelector('#tabla-ejercicios tbody');
  tb.innerHTML = '';
  if (!r.centro) {
    tb.innerHTML = '<tr><td colspan="9">' + esc(I18N_EJERCICIOS.sinCentro) + '</td></tr>';
    return;
  }
  (r.ejercicios || []).forEach((e, i) => {
    const tr = document.createElement('tr');
    const acciones = [];
    if (e.puede_cerrar) {
      acciones.push(`<button type="button" data-accion="cerrar" data-id="${e.id}">${esc(I18N_EJERCICIOS.cerrar)}</button>`);
    }
    if (e.puede_reabrir) {
      acciones.push(`<button type="button" data-accion="reabrir" data-id="${e.id}">${esc(I18N_EJERCICIOS.reabrir)}</button>`);
    }
    if (e.puede_regenerar_apertura) {
      acciones.push(`<button type="button" data-accion="apertura" data-id="${e.id}">${esc(I18N_EJERCICIOS.regenerar)}</button>`);
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
      cerrar: I18N_EJERCICIOS.confirmCerrar,
      reabrir: I18N_EJERCICIOS.confirmReabrir,
      apertura: I18N_EJERCICIOS.confirmApertura,
    };
    if (!confirm(textos[accion] || I18N_EJERCICIOS.continuar)) return;
    const s = await api(`/api/ejercicios/${id}/${accion}`, {method:'POST'});
    if (!s.ok) return alert(s.error);
    loadEjercicios();
  });
});
</script>
