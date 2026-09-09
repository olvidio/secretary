<h1>Nombres</h1>
<form id="form-persona" class="grid-form">
    <input type="hidden" name="id">
    <label>Nombre <input name="nombre" required></label>
    <label>Apellidos <input name="apellidos"></label>
    <label>Iniciales <input name="iniciales" required maxlength="6"></label>
    <label>No paga desde mes <input name="mes_exento_inicio" type="number" min="1" max="12"></label>
    <label>No paga hasta mes <input name="mes_exento_fin" type="number" min="1" max="12"></label>
    <label>Otro intervalo desde <input name="mes_exento2_inicio" type="number" min="1" max="12"></label>
    <label>Otro intervalo hasta <input name="mes_exento2_fin" type="number" min="1" max="12"></label>
    <label>Importe fijo vivienda <input name="importe_vivienda_fijo"></label>
    <button type="submit">Guardar</button>
    <button type="button" id="btn-nuevo">Nuevo</button>
</form>
<table id="tabla-personas">
    <thead>
    <tr>
        <th>#</th><th>Nombre</th><th>Apellidos</th><th>Iniciales</th>
        <th>Exención</th><th>Vivienda fija</th><th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<script>
async function loadPersonas() {
  const r = await api('/api/personas');
  const tb = document.querySelector('#tabla-personas tbody');
  tb.innerHTML = '';
  (r.personas || []).forEach((p, i) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${i+1}</td><td>${esc(p.nombre)}</td><td>${esc(p.apellidos)}</td>
      <td>${esc(p.iniciales)}</td>
      <td>${p.mes_exento_inicio || ''}–${p.mes_exento_fin || ''} ${p.mes_exento2_inicio || ''}–${p.mes_exento2_fin || ''}</td>
      <td>${p.importe_vivienda_fijo || ''}</td>
      <td><button data-id="${p.id}">Editar</button> <button data-del="${p.id}">Borrar</button></td>`;
    tr.querySelector('[data-id]').onclick = () => fillForm(document.getElementById('form-persona'), p);
    tr.querySelector('[data-del]').onclick = async () => {
      if (!confirm('¿Borrar ' + p.iniciales + '?')) return;
      await api('/api/personas/' + p.id, {method:'DELETE'});
      loadPersonas();
    };
    tb.appendChild(tr);
  });
}
document.addEventListener('DOMContentLoaded', () => {
  loadPersonas();
  document.getElementById('btn-nuevo').onclick = () => document.getElementById('form-persona').reset();
  document.getElementById('form-persona').onsubmit = async (ev) => {
    ev.preventDefault();
    const s = await api('/api/personas', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    ev.target.reset();
    loadPersonas();
  };
});
</script>
