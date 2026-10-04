<h1><?= _("Ejecutar entradas periódicas") ?></h1>
<p class="muted"><?= _("Movimientos que ya tocan y aún no se han anotado. Revise la lista, desmarque los que no quiera contabilizar ahora y pulse Ejecutar.") ?></p>

<p class="grid-form">
    <label><?= _("Hasta fecha") ?>
        <input type="date" id="inp-hasta">
    </label>
    <button type="button" id="btn-recargar"><?= _("Actualizar") ?></button>
</p>

<table id="tabla-pendientes">
    <thead>
    <tr>
        <th><input type="checkbox" id="chk-todos" checked title="<?= htmlspecialchars(_("Marcar o desmarcar todos"), ENT_QUOTES) ?>"></th>
        <th><?= _("Fecha") ?></th>
        <th><?= _("Iniciales") ?></th>
        <th><?= _("Concepto") ?></th>
        <th><?= _("Observaciones") ?></th>
        <th class="num"><?= _("Cantidad") ?></th>
        <th><?= _("Periodicidad") ?></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<p class="muted" id="vacio" hidden><?= _("No hay movimientos periódicos pendientes.") ?></p>
<p class="ok" id="msg-ok" hidden></p>

<p><button type="button" id="btn-ejecutar"><?= _("Ejecutar seleccionados") ?></button></p>

<script>
const I18N = {
  ejecutados: <?= json_encode(_("%s apunte(s) creado(s)."), JSON_UNESCAPED_UNICODE) ?>,
  ninguno: <?= json_encode(_("No hay filas seleccionadas."), JSON_UNESCAPED_UNICODE) ?>,
  confirm: <?= json_encode(_("¿Crear los apuntes seleccionados en el libro?"), JSON_UNESCAPED_UNICODE) ?>,
};
let pendientes = [];

async function cargar() {
  const hasta = document.getElementById('inp-hasta').value;
  const q = hasta ? '?hasta=' + encodeURIComponent(hasta) : '';
  const r = await api('/api/entradas-periodicas/pendientes' + q);
  if (!r.ok) return alert(r.error || 'Error');
  pendientes = r.pendientes || [];
  const tb = document.querySelector('#tabla-pendientes tbody');
  tb.innerHTML = '';
  document.getElementById('vacio').hidden = pendientes.length > 0;
  document.getElementById('chk-todos').checked = true;
  pendientes.forEach((p, i) => {
    const tr = document.createElement('tr');
    tr.innerHTML =
      '<td><input type="checkbox" class="chk-linea" data-idx="' + i + '" checked></td>'
      + '<td>' + esc(fmtFecha(p.fecha)) + '</td>'
      + '<td>' + esc(p.iniciales) + '</td>'
      + '<td>' + esc(p.concepto_etiqueta || p.concepto_codigo) + '</td>'
      + '<td>' + esc(p.observaciones || '') + '</td>'
      + '<td class="num">' + esc(p.cantidad) + '</td>'
      + '<td>' + esc(p.periodicidad_etiqueta || p.periodicidad) + '</td>';
    tb.appendChild(tr);
  });
}

document.addEventListener('DOMContentLoaded', async () => {
  document.getElementById('inp-hasta').value = new Date().toISOString().slice(0, 10);
  document.getElementById('btn-recargar').onclick = () => cargar();
  document.getElementById('chk-todos').onchange = (ev) => {
    document.querySelectorAll('.chk-linea').forEach(c => { c.checked = ev.target.checked; });
  };
  document.getElementById('btn-ejecutar').onclick = async () => {
    const lineas = [];
    document.querySelectorAll('.chk-linea:checked').forEach(chk => {
      const p = pendientes[parseInt(chk.dataset.idx, 10)];
      if (p) lineas.push({ entrada_id: p.entrada_id, fecha: p.fecha });
    });
    if (lineas.length === 0) return alert(I18N.ninguno);
    if (!confirm(I18N.confirm)) return;
    const btn = document.getElementById('btn-ejecutar');
    btn.disabled = true;
    const s = await api('/api/entradas-periodicas/ejecutar', { method: 'POST', body: { lineas } });
    btn.disabled = false;
    if (!s.ok) return alert(s.error || 'Error');
    const msg = document.getElementById('msg-ok');
    msg.textContent = I18N.ejecutados.replace('%s', String(s.ejecutados ?? lineas.length));
    msg.hidden = false;
    await cargar();
  };
  await cargar();
});
</script>
