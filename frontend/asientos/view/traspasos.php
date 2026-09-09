<h1>Traspasos de tesorería</h1>

<h2>Mismo libro (caja ↔ banco u otra física)</h2>
<form id="form-traspaso" class="grid-form">
    <label>Libro
        <select name="libro"><option value="P">P</option><option value="G">G</option></select>
    </label>
    <label>Origen (física) <select name="cuenta_fisica_origen_id" id="tr-origen"></select></label>
    <label>Destino (física) <select name="cuenta_fisica_destino_id" id="tr-destino"></select></label>
    <label>Fecha <input type="date" name="fecha" required></label>
    <label>Importe <input name="cantidad" required placeholder="0.00"></label>
    <label>Glosa <input name="glosa"></label>
    <button type="submit">Registrar traspaso</button>
</form>

<h2>Préstamo entre libros (misma física)</h2>
<p>El dinero sale del libro origen y entra en el destino; el saldo físico no cambia.</p>
<form id="form-prestamo" class="grid-form">
    <label>Cuenta física <select name="cuenta_fisica_id" id="pre-fisica"></select></label>
    <label>Libro origen (sale)
        <select name="libro_origen"><option value="G">G</option><option value="P">P</option></select>
    </label>
    <label>Libro destino (entra)
        <select name="libro_destino"><option value="P">P</option><option value="G">G</option></select>
    </label>
    <label>Fecha <input type="date" name="fecha" required></label>
    <label>Importe <input name="cantidad" required placeholder="0.00"></label>
    <label>Glosa <input name="glosa"></label>
    <button type="submit">Registrar préstamo</button>
</form>

<script>
async function loadFisicasSelects() {
  const r = await api('/api/tesoreria');
  const activas = (r.cuentas_fisicas || []).filter(f => f.activo);
  const opts = activas.map(f =>
    `<option value="${f.id}">${esc(f.tipo)} · ${esc(f.nombre)}</option>`).join('');
  ['tr-origen', 'tr-destino', 'pre-fisica'].forEach(id => {
    const el = document.getElementById(id);
    el.innerHTML = opts;
  });
}
document.addEventListener('DOMContentLoaded', async () => {
  const cfg = await api('/api/configuracion');
  document.querySelectorAll('[name=fecha]').forEach(el => { el.value = cfg.config.fecha_cierre; });
  await loadFisicasSelects();
  document.getElementById('form-traspaso').onsubmit = async (ev) => {
    ev.preventDefault();
    const s = await api('/api/traspasos', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    alert('Traspaso registrado (asiento #' + s.asiento.numero + ')');
  };
  document.getElementById('form-prestamo').onsubmit = async (ev) => {
    ev.preventDefault();
    const s = await api('/api/prestamos-libros', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    alert('Préstamo enlazado: asientos ' + s.asiento_origen.id + ' ↔ ' + s.asiento_destino.id);
  };
});
</script>
