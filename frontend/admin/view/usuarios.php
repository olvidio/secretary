<h1><?= _("Usuarios") ?></h1>
<p class="muted"><?= _("Cuentas de acceso al sistema. No se puede borrar al administrador de plataforma.") ?></p>
<table id="tabla-usuarios">
    <thead><tr><th><?= _("Alias") ?></th><th><?= _("Correo") ?></th><th><?= _("Nombre") ?></th><th><?= _("Centros") ?></th><th><?= _("Personas") ?></th><th></th></tr></thead>
    <tbody></tbody>
</table>
<script>
const I18N_ADMIN_USUARIOS = {
  admin: <?= json_encode(_("Admin plataforma"), JSON_UNESCAPED_UNICODE) ?>,
  borrar: <?= json_encode(_("Borrar"), JSON_UNESCAPED_UNICODE) ?>,
  confirmBorrar: <?= json_encode(_("¿Eliminar esta cuenta de usuario?"), JSON_UNESCAPED_UNICODE) ?>,
};
function filaUsuario(u) {
  const tr = document.createElement('tr');
  const alias = u.alias || u.email;
  tr.innerHTML =
    '<td>' + esc(alias) + (u.es_admin ? ' <span class="muted">(' + esc(I18N_ADMIN_USUARIOS.admin) + ')</span>' : '') + '</td>' +
    '<td>' + esc(u.email) + '</td>' +
    '<td>' + esc(u.nombre) + '</td>' +
    '<td>' + esc(String(u.centros ?? 0)) + '</td>' +
    '<td>' + esc(String(u.personas ?? 0)) + '</td>' +
    '<td></td>';
  if (!u.es_admin) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'peligro';
    btn.textContent = I18N_ADMIN_USUARIOS.borrar;
    btn.onclick = async () => {
      if (!confirm(I18N_ADMIN_USUARIOS.confirmBorrar)) return;
      const s = await api('/api/admin/usuarios/' + u.id + '/borrar', { method: 'POST', body: { confirmar: true } });
      if (!s.ok) return alert(s.error);
      await loadUsuarios();
    };
    tr.lastElementChild.appendChild(btn);
  }
  return tr;
}
async function loadUsuarios() {
  const r = await api('/api/admin/usuarios');
  if (!r.ok) return alert(r.error);
  const tb = document.querySelector('#tabla-usuarios tbody');
  tb.innerHTML = '';
  (r.usuarios || []).forEach((u) => tb.appendChild(filaUsuario(u)));
}
document.addEventListener('DOMContentLoaded', loadUsuarios);
</script>
