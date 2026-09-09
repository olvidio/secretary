<h1>Centros</h1>
<p>
    Este centro tiene sus propias cuentas, nombres y secretario.
    Un usuario como <code>scl2</code> se vincula a <em>otro</em> centro y no ve los datos de éste.
</p>

<section>
    <h2>Este centro</h2>
    <p id="centro-actual" class="muted">Cargando…</p>
    <table id="tabla-usuarios">
        <thead>
        <tr><th>Usuario</th><th>Correo</th><th>Nombre</th><th>Rol</th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <h3>Añadir usuario de este centro</h3>
    <form id="form-usuario" class="grid-form">
        <label>Usuario (alias) <input name="usuario" required placeholder="p. ej. scl"></label>
        <label>Correo <input name="email" type="email" required></label>
        <label>Contraseña <input name="password" type="password" required minlength="6"></label>
        <label>Nombre <input name="nombre"></label>
        <button type="submit">Vincular</button>
    </form>
    <h3>Excel de este centro</h3>
    <p class="muted">Carga el <code>.xlsm</code> en el libro de este centro, sin tocar el de los demás.</p>
    <form id="form-import" class="grid-form">
        <label>Fichero Excel <input name="excel" type="file" accept=".xlsm,.xlsx" required></label>
        <button type="submit">Importar Excel</button>
    </form>
    <p class="ok" id="msg-import" hidden></p>
    <p class="muted">Mientras estemos de pruebas: vaciar asientos, remesas y arqueos para volver a cargar el Excel. Quedan el centro, los usuarios y los nombres.</p>
    <button type="button" id="btn-vaciar" class="peligro">Vaciar datos (pruebas)</button>
    <p class="ok" id="msg-vaciar" hidden></p>
</section>

<section>
    <h2>Nuevo centro</h2>
    <p class="muted">Crea una entidad distinta, con su plan de cuentas y su propio secretario. Tú no quedarás vinculado a ella. Si adjuntas el Excel, se importa en ese centro al crearlo.</p>
    <form id="form-centro" class="grid-form">
        <label>Código <input name="codigo" required placeholder="p. ej. CASA-B"></label>
        <label>Nombre <input name="nombre" required></label>
        <label>Tipo de cierre
            <select name="tipo_cierre">
                <option value="vivienda">Vivienda</option>
                <option value="necesidades">Necesidades</option>
            </select>
        </label>
        <label>Ejercicio desde <input name="fecha_inicio" type="date" required></label>
        <label>Ejercicio hasta <input name="fecha_fin" type="date" required></label>
        <label>Usuario secretario <input name="usuario" required placeholder="p. ej. scl2"></label>
        <label>Correo secretario <input name="email" type="email" required></label>
        <label>Contraseña <input name="password" type="password" required minlength="6"></label>
        <label>Excel (opcional) <input name="excel" type="file" accept=".xlsm,.xlsx"></label>
        <button type="submit">Crear centro</button>
    </form>
    <p class="ok" id="msg-centro" hidden></p>
</section>
<script>
function textoImportacion(imp) {
  if (!imp) return '';
  return ' Importados ' + (imp.personas || 0) + ' nombres y ' + (imp.asientos || 0) + ' asientos.';
}
async function loadCentro() {
  const r = await api('/api/centros');
  const p = document.getElementById('centro-actual');
  const tb = document.querySelector('#tabla-usuarios tbody');
  tb.innerHTML = '';
  if (!r.ok) {
    p.textContent = r.error || 'No se pudo cargar el centro';
    return;
  }
  const c = r.centro || {};
  p.textContent = (c.nombre || c.codigo || '') + (c.tipo_cierre ? ' · cierre ' + c.tipo_cierre : '');
  (r.usuarios || []).forEach((u) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${esc(u.alias)}</td><td>${esc(u.email)}</td><td>${esc(u.nombre)}</td><td>${esc(u.rol)}</td>`;
    tb.appendChild(tr);
  });
}
document.addEventListener('DOMContentLoaded', () => {
  const year = new Date().getFullYear();
  const ini = document.querySelector('#form-centro [name=fecha_inicio]');
  const fin = document.querySelector('#form-centro [name=fecha_fin]');
  if (ini && !ini.value) ini.value = year + '-01-01';
  if (fin && !fin.value) fin.value = year + '-12-31';
  loadCentro();
  document.getElementById('form-usuario').onsubmit = async (ev) => {
    ev.preventDefault();
    const s = await api('/api/centros/usuarios', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    ev.target.reset();
    loadCentro();
  };
  document.getElementById('form-import').onsubmit = async (ev) => {
    ev.preventDefault();
    const btn = ev.target.querySelector('button[type=submit]');
    const msg = document.getElementById('msg-import');
    btn.disabled = true;
    msg.hidden = true;
    try {
      const s = await api('/api/centros/import', {method:'POST', body: new FormData(ev.target)});
      if (!s.ok) return alert(s.error);
      msg.hidden = false;
      msg.textContent = 'Excel importado.' + textoImportacion(s.importacion);
      ev.target.reset();
    } finally {
      btn.disabled = false;
    }
  };
  document.getElementById('btn-vaciar').onclick = async () => {
    if (!confirm('Esto borra asientos, remesas y arqueos de ESTE centro para poder recargar el Excel. Quedan el centro, los usuarios y los nombres. ¿Seguro?')) {
      return;
    }
    const s = await api('/api/centros/vaciar', {method:'POST', body: {confirmar: true}});
    if (!s.ok) return alert(s.error);
    const msg = document.getElementById('msg-vaciar');
    msg.hidden = false;
    msg.textContent = 'Vaciados ' + (s.asientos || 0) + ' asientos en ' + (s.ejercicios || 0) + ' ejercicio(s). Ya puedes importar el Excel.';
  };
  document.getElementById('form-centro').onsubmit = async (ev) => {
    ev.preventDefault();
    const btn = ev.target.querySelector('button[type=submit]');
    const msg = document.getElementById('msg-centro');
    btn.disabled = true;
    msg.hidden = true;
    try {
      const s = await api('/api/centros', {method:'POST', body: new FormData(ev.target)});
      if (!s.ok) return alert(s.error);
      msg.hidden = false;
      let texto = 'Centro «' + (s.centro.nombre || s.centro.codigo) + '» creado. Entra con '
        + (s.usuario.alias || s.usuario.email) + ' (tendrá que activar TOTP).';
      if (s.aviso_import) {
        texto += ' El Excel no se importó: ' + s.aviso_import;
      } else {
        texto += textoImportacion(s.importacion);
      }
      msg.textContent = texto;
      ev.target.reset();
      if (ini) ini.value = year + '-01-01';
      if (fin) fin.value = year + '-12-31';
    } finally {
      btn.disabled = false;
    }
  };
});
</script>
