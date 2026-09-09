<h1>Configuración</h1>
<form id="form-config" class="grid-form">
    <label>Centro <input name="centro" required></label>
    <label>Año <input name="anio" type="number" required></label>
    <label>Ejercicio
        <select name="modo_ejercicio">
            <option>Año</option>
            <option>Curso</option>
        </select>
    </label>
    <label>Fecha inicio <input name="fecha_inicio" type="date" required></label>
    <label>Fecha cierre <input name="fecha_cierre" type="date" required></label>
    <label>Tipo de cierre
        <select name="tipo_cierre">
            <option value="vivienda">Vivienda (n) — P 21 / G 11</option>
            <option value="necesidades">Necesidades (agd/sss+) — P 6 / G 14</option>
        </select>
    </label>
    <label>Número de residentes <input name="num_residentes" type="number"></label>
    <button type="submit">Guardar</button>
    <p class="ok" id="msg" hidden>Guardado</p>
</form>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/configuracion');
  fillForm(document.getElementById('form-config'), r.config);
  document.getElementById('form-config').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const body = formObj(ev.target);
    const s = await api('/api/configuracion', {method:'POST', body});
    document.getElementById('msg').hidden = !s.ok;
    if (!s.ok) alert(s.error);
  });
});
</script>
