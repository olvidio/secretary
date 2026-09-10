<h1>Nombres</h1>
<p class="muted">El correo convierte a esa persona en usuario del libro personal de <em>este</em> centro. Si el correo es nuevo, se muestra una contraseña inicial para comunicársela una vez. «Vivienda aporta a generales» indica si un gasto P/21 debe tener la entrada G/11 (típico de n); si no, el 21 es solo personal (típico de agd). La exención de meses es para quien llega o se va a mitad de año (no se le pide movimiento ni entra en el cierre de vivienda esos meses). Quien no aporta a G debe dejarla vacía, para que Comprobaciones avise si no anota el mes.</p>
<form id="form-persona" class="grid-form">
    <input type="hidden" name="id">
    <label>Nombre <input name="nombre" required></label>
    <label>Apellidos <input name="apellidos"></label>
    <label>Iniciales <input name="iniciales" required maxlength="6"></label>
    <label>Correo <input name="email" type="email" autocomplete="off"></label>
    <label>No paga desde mes <input name="mes_exento_inicio" type="number" min="1" max="12"></label>
    <label>No paga hasta mes <input name="mes_exento_fin" type="number" min="1" max="12"></label>
    <label>Otro intervalo desde <input name="mes_exento2_inicio" type="number" min="1" max="12"></label>
    <label>Otro intervalo hasta <input name="mes_exento2_fin" type="number" min="1" max="12"></label>
    <label>Importe fijo vivienda <input name="importe_vivienda_fijo"></label>
    <label>Vivienda aporta a generales
        <select name="vivienda_aporta_generales">
            <option value="1">Sí — P/21 tiene entrada G/11</option>
            <option value="0">No — vivienda solo personal</option>
        </select>
    </label>
    <button type="submit">Guardar</button>
    <button type="button" id="btn-nuevo">Nuevo</button>
</form>
<p class="ok" id="msg-password" hidden></p>
<table id="tabla-personas">
    <thead>
    <tr>
        <th>#</th><th>Nombre</th><th>Apellidos</th><th>Iniciales</th><th>Correo</th>
        <th>Exención</th><th>Vivienda fija</th><th>Aporta a G</th><th></th>
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
      <td>${esc(p.iniciales)}</td><td>${esc(p.email)}</td>
      <td>${p.mes_exento_inicio || ''}–${p.mes_exento_fin || ''} ${p.mes_exento2_inicio || ''}–${p.mes_exento2_fin || ''}</td>
      <td>${p.importe_vivienda_fijo || ''}</td>
      <td>${p.vivienda_aporta_generales ? 'sí' : 'no'}</td>
      <td><button data-id="${p.id}">Editar</button> <button data-del="${p.id}">Borrar</button></td>`;
    tr.querySelector('[data-id]').onclick = () => {
      const form = document.getElementById('form-persona');
      fillForm(form, p);
      form.querySelector('[name=vivienda_aporta_generales]').value = p.vivienda_aporta_generales ? '1' : '0';
    };
    tr.querySelector('[data-del]').onclick = async () => {
      if (!confirm('¿Borrar ' + p.iniciales + '?')) return;
      await api('/api/personas/' + p.id, {method:'DELETE'});
      loadPersonas();
    };
    tb.appendChild(tr);
  });
}
document.addEventListener('DOMContentLoaded', async () => {
  let aportaDefault = '1';
  const cfg = await api('/api/configuracion');
  if (cfg.config && cfg.config.tipo_cierre === 'necesidades') {
    aportaDefault = '0';
  }
  loadPersonas();
  document.getElementById('btn-nuevo').onclick = () => {
    document.getElementById('form-persona').reset();
    document.querySelector('[name=vivienda_aporta_generales]').value = aportaDefault;
    document.getElementById('msg-password').hidden = true;
  };
  document.querySelector('[name=vivienda_aporta_generales]').value = aportaDefault;
  document.getElementById('form-persona').onsubmit = async (ev) => {
    ev.preventDefault();
    const s = await api('/api/personas', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    const msg = document.getElementById('msg-password');
    if (s.password_inicial) {
      msg.hidden = false;
      msg.textContent = 'Contraseña inicial de ' + (s.persona.email || '') + ': ' + s.password_inicial + ' — comunícasela ahora; no se volverá a mostrar.';
    } else {
      msg.hidden = true;
    }
    ev.target.reset();
    document.querySelector('[name=vivienda_aporta_generales]').value = aportaDefault;
    loadPersonas();
  };
});
</script>
