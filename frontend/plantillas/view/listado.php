<?php $cuenta = $cuentaEntrada ?? 'P'; ?>
<h1>Plantillas de apuntes <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></h1>
<p class="muted">Solo define los movimientos (origen, concepto, observaciones). Al usarlas en Entrada
    <?= htmlspecialchars($cuenta, ENT_QUOTES) ?>, las iniciales, la fecha y la cantidad salen de la cabecera del formulario.</p>

<form id="form-plantilla" class="grid-form">
    <input type="hidden" name="cuenta" value="<?= htmlspecialchars($cuenta, ENT_QUOTES) ?>">
    <label>Nombre <input name="nombre" required placeholder="p. ej. Club"></label>
    <fieldset class="plantilla-lineas">
        <legend>Movimientos</legend>
        <div id="lineas-form"></div>
        <button type="button" id="btn-add-linea">Añadir movimiento</button>
    </fieldset>
    <button type="submit">Guardar plantilla</button>
</form>

<table id="tabla-plantillas">
    <thead>
    <tr><th>Nombre</th><th>Movimientos</th><th></th></tr>
    </thead>
    <tbody></tbody>
</table>

<script>
const CUENTA = <?= json_encode($cuenta) ?>;
const ORIGEN_OPTS = '<option value="A">Apunte</option><option value="B">Banco</option><option value="C">Caja</option>';

function filaLinea(d = {}) {
  const div = document.createElement('div');
  div.className = 'plantilla-linea grid-form';
  div.innerHTML =
    '<label>P/G <select name="cuenta"><option>P</option><option>G</option></select></label>' +
    '<label>A/B/C <select name="origen" required>' + ORIGEN_OPTS + '</select></label>' +
    '<label>Concepto <input name="concepto_codigo" required value="' + esc(d.concepto_codigo || '') + '"></label>' +
    '<label>Observaciones <input name="observaciones" value="' + esc(d.observaciones || '') + '"></label>' +
    '<button type="button" class="btn-quitar">Quitar</button>';
  div.querySelector('[name=cuenta]').value = d.cuenta || CUENTA;
  div.querySelector('[name=origen]').value = d.origen || 'A';
  div.querySelector('.btn-quitar').onclick = () => div.remove();
  return div;
}

function lineasDelForm() {
  return [...document.querySelectorAll('#lineas-form .plantilla-linea')].map(row => ({
    cuenta: row.querySelector('[name=cuenta]').value,
    origen: row.querySelector('[name=origen]').value,
    concepto_codigo: row.querySelector('[name=concepto_codigo]').value,
    observaciones: row.querySelector('[name=observaciones]').value,
  }));
}

function resumenLineas(lineas) {
  return (lineas || []).map(l =>
    (l.cuenta || 'P') + ' ' + l.origen + ' ' + l.concepto_codigo
    + (l.observaciones ? ' · ' + l.observaciones : '')
  ).join(' → ');
}

async function loadPlantillas() {
  const r = await api('/api/plantillas-apunte?cuenta=' + CUENTA);
  const tb = document.querySelector('#tabla-plantillas tbody');
  tb.innerHTML = '';
  (r.plantillas || []).forEach(p => {
    const tr = document.createElement('tr');
    tr.innerHTML =
      '<td>' + esc(p.nombre) + '</td>' +
      '<td class="muted">' + esc(resumenLineas(p.lineas)) + '</td>' +
      '<td><button type="button" data-del="' + p.id + '">Borrar</button></td>';
    tr.querySelector('[data-del]').onclick = async () => {
      if (!confirm('¿Borrar plantilla «' + p.nombre + '»?')) return;
      const s = await api('/api/plantillas-apunte/' + p.id, { method: 'DELETE' });
      if (!s.ok) return alert(s.error);
      loadPlantillas();
    };
    tb.appendChild(tr);
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const cont = document.getElementById('lineas-form');
  cont.appendChild(filaLinea({ cuenta: 'P', origen: 'A', concepto_codigo: '21', observaciones: 'per el club' }));
  cont.appendChild(filaLinea({ cuenta: 'P', origen: 'A', concepto_codigo: '111' }));
  cont.appendChild(filaLinea({ cuenta: 'G', origen: 'A', concepto_codigo: '11', observaciones: 'per el club' }));
  cont.appendChild(filaLinea({ cuenta: 'G', origen: 'A', concepto_codigo: '211', observaciones: 'ingres al club' }));
  document.getElementById('btn-add-linea').onclick = () => cont.appendChild(filaLinea({ cuenta: CUENTA }));

  document.getElementById('form-plantilla').onsubmit = async (ev) => {
    ev.preventDefault();
    const body = formObj(ev.target);
    body.lineas = lineasDelForm();
    const s = await api('/api/plantillas-apunte', { method: 'POST', body });
    if (!s.ok) return alert(s.error);
    ev.target.reset();
    cont.innerHTML = '';
    cont.appendChild(filaLinea({ cuenta: CUENTA }));
    loadPlantillas();
  };

  loadPlantillas();
});
</script>
