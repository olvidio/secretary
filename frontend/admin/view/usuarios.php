<h1><?= _("Usuarios") ?></h1>
<p class="muted"><?= _("Cuentas de acceso. Personal: borrado inmediato con confirmación por correo (desde la propia cuenta) o desde aquí. Secretario: baja en standby (60 días) con aviso a cuentas personales vinculadas; reactivable por admin.") ?></p>
<table id="tabla-usuarios">
    <thead><tr><th><?= _("Alias") ?></th><th><?= _("Correo") ?></th><th><?= _("Nombre del usuario") ?></th><th><?= _("Centros") ?></th><th><?= _("Personas") ?></th><th></th></tr></thead>
    <tbody></tbody>
</table>
<script>
const I18N_ADMIN_USUARIOS = {
  admin: <?= json_encode(_("Admin plataforma"), JSON_UNESCAPED_UNICODE) ?>,
  borrar: <?= json_encode(_("Borrar"), JSON_UNESCAPED_UNICODE) ?>,
  reactivar: <?= json_encode(_("Reactivar"), JSON_UNESCAPED_UNICODE) ?>,
  standby: <?= json_encode(_("En baja"), JSON_UNESCAPED_UNICODE) ?>,
  confirmPersonalTitulo: <?= json_encode(_("¿Eliminar esta cuenta personal?"), JSON_UNESCAPED_UNICODE) ?>,
  confirmCentroTitulo: <?= json_encode(_("¿Programar la baja de esta cuenta de secretario?"), JSON_UNESCAPED_UNICODE) ?>,
  confirmSeguro: <?= json_encode(_("Esta acción no se puede deshacer."), JSON_UNESCAPED_UNICODE) ?>,
  confirmFinalPersonal: <?= json_encode(_("¿Confirma el borrado definitivo? Se enviará un correo al usuario."), JSON_UNESCAPED_UNICODE) ?>,
  confirmFinalCentro: <?= json_encode(_("¿Confirma la baja programada? Se desactiva el acceso y se avisa por correo."), JSON_UNESCAPED_UNICODE) ?>,
  confirmReactivar: <?= json_encode(_("¿Reactivar esta cuenta de secretario y restaurar sus centros?"), JSON_UNESCAPED_UNICODE) ?>,
};
function fmtFechaIso(s) {
  if (!s) return '';
  const d = new Date(s);
  if (Number.isNaN(d.getTime())) return s.slice(0, 10);
  return d.toISOString().slice(0, 10);
}
function filaUsuario(u) {
  const tr = document.createElement('tr');
  const alias = u.alias || u.email;
  let extra = u.es_admin ? ' <span class="muted">(' + esc(I18N_ADMIN_USUARIOS.admin) + ')</span>' : '';
  if (u.baja_centro) {
    extra += ' <span class="muted">(' + esc(I18N_ADMIN_USUARIOS.standby);
    if (u.baja_centro_ejecutar_at) extra += ' → ' + esc(fmtFechaIso(u.baja_centro_ejecutar_at));
    extra += ')</span>';
  }
  tr.innerHTML =
    '<td>' + esc(alias) + extra + '</td>' +
    '<td>' + esc(u.email) + '</td>' +
    '<td>' + esc(u.nombre) + '</td>' +
    '<td>' + esc(String(u.centros ?? 0)) + '</td>' +
    '<td>' + esc(String(u.personas ?? 0)) + '</td>' +
    '<td></td>';
  const td = tr.lastElementChild;
  if (!u.es_admin && u.baja_centro) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = I18N_ADMIN_USUARIOS.reactivar;
    btn.onclick = () => reactivarCentro(u);
    td.appendChild(btn);
  } else if (!u.es_admin && (u.es_personal || u.es_secretario)) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'peligro';
    btn.textContent = I18N_ADMIN_USUARIOS.borrar;
    btn.onclick = () => borrarUsuario(u);
    td.appendChild(btn);
  }
  return tr;
}
async function borrarUsuario(u) {
  const prev = await api('/api/admin/usuarios/' + u.id + '/borrar');
  if (!prev.ok) return alert(prev.error);
  if (!prev.puede_borrar) return alert(prev.motivo_bloqueo || prev.error);
  const esCentro = prev.es_secretario === true;
  let msg = (esCentro ? I18N_ADMIN_USUARIOS.confirmCentroTitulo : I18N_ADMIN_USUARIOS.confirmPersonalTitulo);
  if (!esCentro) msg += '\n\n' + I18N_ADMIN_USUARIOS.confirmSeguro;
  if (prev.texto_datos) msg += '\n\n' + prev.texto_datos;
  if (prev.texto_conservacion) msg += '\n\n' + prev.texto_conservacion;
  if (!confirm(msg)) return;
  if (!confirm(esCentro ? I18N_ADMIN_USUARIOS.confirmFinalCentro : I18N_ADMIN_USUARIOS.confirmFinalPersonal)) return;
  const s = await api('/api/admin/usuarios/' + u.id + '/borrar', { method: 'POST', body: { confirmar: true } });
  if (!s.ok) return alert(s.error);
  await loadUsuarios();
}
async function reactivarCentro(u) {
  if (!confirm(I18N_ADMIN_USUARIOS.confirmReactivar)) return;
  const s = await api('/api/admin/usuarios/' + u.id + '/reactivar', { method: 'POST', body: { confirmar: true } });
  if (!s.ok) return alert(s.error);
  await loadUsuarios();
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
