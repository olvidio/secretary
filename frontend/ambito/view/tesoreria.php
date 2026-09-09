<h1>Tesorería física</h1>
<p>Cuentas reales del centro (caja o banco). Al dar de alta una física se crean automáticamente
    sus cuentas de mayor <code>CAJA.n/P</code>, <code>CAJA.n/G</code> o <code>BANCO.n/P</code>, <code>BANCO.n/G</code>.</p>

<form id="form-fisica" class="grid-form">
    <label>Tipo
        <select name="tipo" required>
            <option value="caja">Caja</option>
            <option value="banco">Banco</option>
        </select>
    </label>
    <label>Nombre <input name="nombre" required placeholder="p. ej. Banc Sabadell"></label>
    <label>IBAN (opcional) <input name="iban" placeholder="ES…"></label>
    <button type="submit">Alta</button>
</form>

<table id="tabla-fisicas">
    <thead>
    <tr><th>Tipo</th><th>Nombre</th><th>IBAN</th><th>Orden</th><th>Activa</th><th></th></tr>
    </thead>
    <tbody></tbody>
</table>
<script>
async function loadFisicas() {
  const r = await api('/api/tesoreria');
  const tb = document.querySelector('#tabla-fisicas tbody');
  tb.innerHTML = '';
  (r.cuentas_fisicas || []).forEach(f => {
    const tr = document.createElement('tr');
    const btn = f.activo
      ? `<button type="button" data-id="${f.id}" class="btn-desact">Desactivar</button>`
      : '';
    tr.innerHTML = `<td>${esc(f.tipo)}</td><td>${esc(f.nombre)}</td><td>${esc(f.iban || '')}</td>
      <td>${f.orden}</td><td>${f.activo ? 'Sí' : 'No'}</td><td>${btn}</td>`;
    tb.appendChild(tr);
  });
  tb.querySelectorAll('.btn-desact').forEach(b => {
    b.onclick = async () => {
      if (!confirm('¿Desactivar esta cuenta física?')) return;
      const s = await api('/api/tesoreria/' + b.dataset.id + '/desactivar', {method:'POST'});
      if (!s.ok) return alert(s.error);
      loadFisicas();
    };
  });
}
document.addEventListener('DOMContentLoaded', () => {
  loadFisicas();
  document.getElementById('form-fisica').onsubmit = async (ev) => {
    ev.preventDefault();
    const s = await api('/api/tesoreria', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    ev.target.reset();
    loadFisicas();
  };
});
</script>
