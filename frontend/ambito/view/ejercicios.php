<?php $esClub = !empty($esClub); ?>
<h1><?= _("Ejercicios") ?></h1>
<?php if ($esClub): ?>
<p><?= _("La fecha de inicio y la de fin delimitan el ejercicio. La fecha de corte es hasta dónde hay datos introducidos; cuando el ejercicio está cerrado del todo, coincide con la fecha de fin.") ?></p>
<p class="muted"><?= _("Al abrir el ejercicio siguiente, el saldo de partida se arrastra del anterior. En el primero, ese saldo se anota a mano.") ?></p>
<form id="form-ejercicio" class="grid-form">
    <label><?= _("Etiqueta") ?> <input name="etiqueta" placeholder="<?= htmlspecialchars(_("p. ej. 2026 o 2026-27"), ENT_QUOTES) ?>"></label>
    <label><?= _("Fecha inicio") ?> <input name="fecha_inicio" type="date" required></label>
    <label><?= _("Fecha fin") ?> <input name="fecha_fin" type="date" required></label>
    <label><?= _("Fecha de corte") ?> <input name="fecha_corte" type="date"></label>
    <button type="submit"><?= _("Crear ejercicio") ?></button>
</form>
<?php else: ?>
<p><?= _("Cada fila es un periodo contable del centro. Solo puede haber uno abierto. La fecha de corte indica hasta dónde hay datos; al crear un ejercicio empieza igual que la fecha de inicio y se va moviendo con Fecha cierre.") ?></p>
<p class="muted"><?= _("Al abrir el ejercicio siguiente al anterior cerrado, el saldo de partida (disponible, concepto 32 en G) se genera solo. En el primer ejercicio importado, ese disponible se teclea a mano.") ?></p>
<p class="muted"><?= _("Puede ajustar las fechas si el periodo no es el habitual. La etiqueta (2026, 2026-27…) se calcula al crear.") ?></p>
<form id="form-ejercicio" class="grid-form form-ejercicio-alta">
    <label><?= _("Año") ?> <input name="anio" id="inp-anio" type="number" min="2000" max="2100" required></label>
    <label><?= _("Ejercicio") ?>
        <select name="modo_ejercicio" id="sel-modo">
            <option value="Año"><?= _("Año") ?> (<?= _("ene–dic") ?>)</option>
            <option value="Curso"><?= _("Curso") ?> (<?= _("sep–ago") ?>)</option>
        </select>
    </label>
    <label><?= _("Fecha inicio") ?> <input name="fecha_inicio" id="inp-inicio" type="date" required></label>
    <label><?= _("Fecha fin") ?> <input name="fecha_fin" id="inp-fin" type="date" required></label>
    <button type="submit"><?= _("Crear ejercicio") ?></button>
</form>
<?php endif; ?>
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
const ES_CLUB = <?= $esClub ? 'true' : 'false' ?>;
const I18N_EJERCICIOS = {
  sinCentro: <?= json_encode(_("No hay ningún centro dado de alta todavía."), JSON_UNESCAPED_UNICODE) ?>,
  cerrar: <?= json_encode(_("Cerrar"), JSON_UNESCAPED_UNICODE) ?>,
  reabrir: <?= json_encode(_("Reabrir"), JSON_UNESCAPED_UNICODE) ?>,
  regenerar: <?= json_encode(_("Regenerar apertura"), JSON_UNESCAPED_UNICODE) ?>,
  confirmCerrar: <?= json_encode(_("¿Cerrar este ejercicio? No se podrán registrar más asientos."), JSON_UNESCAPED_UNICODE) ?>,
  confirmReabrir: <?= json_encode(_("¿Reabrir este ejercicio para correcciones?"), JSON_UNESCAPED_UNICODE) ?>,
  confirmApertura: <?= json_encode(_("¿Regenerar los asientos de apertura? Se borrarán los actuales tipo apertura."), JSON_UNESCAPED_UNICODE) ?>,
  eliminar: <?= json_encode(_("Eliminar"), JSON_UNESCAPED_UNICODE) ?>,
  confirmEliminar: <?= json_encode(_("¿Eliminar el ejercicio %s? Se borran sus %s asientos y no se puede deshacer."), JSON_UNESCAPED_UNICODE) ?>,
  continuar: <?= json_encode(_("¿Continuar?"), JSON_UNESCAPED_UNICODE) ?>,
};
function fechasTipicas(anio, modo) {
  const a = Number(anio);
  if (modo === 'Curso') {
    return { inicio: `${a}-09-01`, fin: `${a + 1}-08-31` };
  }
  return { inicio: `${a}-01-01`, fin: `${a}-12-31` };
}
function aplicarFechasTipicas() {
  if (ES_CLUB) return;
  const anio = document.getElementById('inp-anio').value;
  const modo = document.getElementById('sel-modo').value;
  if (!anio) return;
  const f = fechasTipicas(anio, modo);
  document.getElementById('inp-inicio').value = f.inicio;
  document.getElementById('inp-fin').value = f.fin;
}
function rellenarAlta(r) {
  if (ES_CLUB || !r.sugerencia_nuevo) return;
  const s = r.sugerencia_nuevo;
  document.getElementById('inp-anio').value = s.anio;
  document.getElementById('sel-modo').value = s.modo_ejercicio;
  document.getElementById('inp-inicio').value = s.fecha_inicio;
  document.getElementById('inp-fin').value = s.fecha_fin;
}
async function loadEjercicios() {
  const r = await api('/api/ejercicios');
  const tb = document.querySelector('#tabla-ejercicios tbody');
  tb.innerHTML = '';
  if (!r.centro) {
    tb.innerHTML = '<tr><td colspan="9">' + esc(I18N_EJERCICIOS.sinCentro) + '</td></tr>';
    return;
  }
  rellenarAlta(r);
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
    if (e.puede_eliminar) {
      acciones.push(`<button type="button" data-accion="eliminar" data-id="${e.id}" data-etiqueta="${esc(e.etiqueta)}" data-asientos="${e.asientos || 0}">${esc(I18N_EJERCICIOS.eliminar)}</button>`);
    }
    tr.innerHTML = `<td>${i+1}</td><td>${esc(e.etiqueta)}</td><td>${esc(e.fecha_inicio)}</td>
      <td>${esc(e.fecha_fin)}</td><td>${esc(e.fecha_corte)}</td>
      <td>${e.meses_totales}</td><td>${e.meses_transcurridos}</td><td>${esc(e.estado)}</td>
      <td class="acciones">${acciones.join(' ') || '—'}</td>`;
    tb.appendChild(tr);
  });
}
document.addEventListener('DOMContentLoaded', () => {
  if (!ES_CLUB) {
    document.getElementById('inp-anio').addEventListener('change', aplicarFechasTipicas);
    document.getElementById('inp-anio').addEventListener('input', aplicarFechasTipicas);
    document.getElementById('sel-modo').addEventListener('change', aplicarFechasTipicas);
  }
  loadEjercicios();
  document.getElementById('form-ejercicio').onsubmit = async (ev) => {
    ev.preventDefault();
    const body = formObj(ev.target);
    if (!ES_CLUB) {
      delete body.anio;
      delete body.modo_ejercicio;
    }
    const s = await api('/api/ejercicios', {method:'POST', body});
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
      eliminar: I18N_EJERCICIOS.confirmEliminar.replace('%s', btn.dataset.etiqueta || '').replace('%s', btn.dataset.asientos || '0'),
    };
    if (!confirm(textos[accion] || I18N_EJERCICIOS.continuar)) return;
    const s = await api(`/api/ejercicios/${id}/${accion}`, {method:'POST'});
    if (!s.ok) return alert(s.error);
    loadEjercicios();
  });
});
</script>
