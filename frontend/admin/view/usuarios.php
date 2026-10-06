<h1><?= _("Usuarios") ?></h1>
<p class="muted"><?= _("Cuentas de acceso. En Centros aparece el acceso como secretario; en Personas, el nombre en cada entidad (y el libro Mis cuentas). Quitar un vínculo no borra la cuenta ni los datos contables del centro. Personal: borrado inmediato con confirmación por correo (desde la propia cuenta) o desde aquí. Secretario: baja en standby (60 días) con aviso a cuentas personales vinculadas; reactivable por admin.") ?></p>
<table id="tabla-usuarios">
    <thead><tr><th><?= _("Alias") ?></th><th><?= _("Correo") ?></th><th><?= _("Nombre") ?></th><th><?= _("Centros") ?></th><th><?= _("Personas") ?></th><th></th></tr></thead>
    <tbody></tbody>
</table>

<h2><?= _("Correos con varias cuentas (legacy)") ?></h2>
<p class="muted"><?= _("D15: conviene una identidad por persona. Fusionar une mandatos de centro y vínculos de persona en la cuenta principal y elimina los alias duplicados. No borra contabilidad.") ?></p>
<div id="duplicados-correo" class="muted"><?= _("Cargando…") ?></div>

<script>
const I18N_ADMIN_USUARIOS = {
  admin: <?= json_encode(_("Admin plataforma"), JSON_UNESCAPED_UNICODE) ?>,
  borrar: <?= json_encode(_("Borrar"), JSON_UNESCAPED_UNICODE) ?>,
  reactivar: <?= json_encode(_("Reactivar"), JSON_UNESCAPED_UNICODE) ?>,
  quitar: <?= json_encode(_("Quitar"), JSON_UNESCAPED_UNICODE) ?>,
  libroPersonal: <?= json_encode(_("Libro personal (Mis cuentas)"), JSON_UNESCAPED_UNICODE) ?>,
  standby: <?= json_encode(_("En baja"), JSON_UNESCAPED_UNICODE) ?>,
  confirmPersonalTitulo: <?= json_encode(_("¿Eliminar esta cuenta personal?"), JSON_UNESCAPED_UNICODE) ?>,
  confirmCentroTitulo: <?= json_encode(_("¿Programar la baja de esta cuenta de secretario?"), JSON_UNESCAPED_UNICODE) ?>,
  confirmSeguro: <?= json_encode(_("Esta acción no se puede deshacer."), JSON_UNESCAPED_UNICODE) ?>,
  confirmVaciaTitulo: <?= json_encode(_("¿Eliminar esta cuenta?"), JSON_UNESCAPED_UNICODE) ?>,
  confirmFinalPersonal: <?= json_encode(_("¿Confirma el borrado definitivo? Se enviará un correo al usuario."), JSON_UNESCAPED_UNICODE) ?>,
  confirmFinalCentro: <?= json_encode(_("¿Confirma la baja programada? Se desactiva el acceso y se avisa por correo."), JSON_UNESCAPED_UNICODE) ?>,
  confirmReactivar: <?= json_encode(_("¿Reactivar esta cuenta de secretario y restaurar sus centros?"), JSON_UNESCAPED_UNICODE) ?>,
  confirmQuitarCentro: <?= json_encode(_("¿Quitar el acceso de secretario a este centro? La contabilidad del centro no se borra."), JSON_UNESCAPED_UNICODE) ?>,
  confirmQuitarPersona: <?= json_encode(_("¿Quitar el vínculo con esta persona en el centro? El nombre y su histórico contable siguen en la entidad."), JSON_UNESCAPED_UNICODE) ?>,
  sinDuplicados: <?= json_encode(_("No hay correos con más de una cuenta activa."), JSON_UNESCAPED_UNICODE) ?>,
  fusionar: <?= json_encode(_("Fusionar en la elegida"), JSON_UNESCAPED_UNICODE) ?>,
  principal: <?= json_encode(_("Conservar cuenta"), JSON_UNESCAPED_UNICODE) ?>,
  confirmFusion: <?= json_encode(_("¿Fusionar las cuentas duplicadas? Esta acción no se puede deshacer."), JSON_UNESCAPED_UNICODE) ?>,
  reiniciar2fa: <?= json_encode(_("Reiniciar 2FA"), JSON_UNESCAPED_UNICODE) ?>,
  confirmReiniciar2fa: <?= json_encode(_("¿Reiniciar el segundo factor de este usuario? Se borrarán el TOTP y los códigos de recuperación. En el próximo acceso deberá escanear un QR nuevo."), JSON_UNESCAPED_UNICODE) ?>,
};
const OPERADOR_ID = <?= (int) ($_SESSION['identidad_id'] ?? 0) ?>;
function fmtFechaIso(s) {
  if (!s) return '';
  const d = new Date(s);
  if (Number.isNaN(d.getTime())) return s.slice(0, 10);
  return d.toISOString().slice(0, 10);
}
function puedeGestionarVinculos(u) {
  return !u.es_admin && !u.baja_centro;
}
function ulVinculos(items, renderItem) {
  const ul = document.createElement('ul');
  ul.className = 'admin-vinculos';
  if (!items || items.length === 0) {
    const li = document.createElement('li');
    li.className = 'muted';
    li.textContent = '—';
    ul.appendChild(li);
    return ul;
  }
  items.forEach((item) => ul.appendChild(renderItem(item)));
  return ul;
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
  const tdAlias = document.createElement('td');
  tdAlias.innerHTML = esc(alias) + extra;
  tr.appendChild(tdAlias);
  tr.appendChild(document.createElement('td')).textContent = u.email || '';
  tr.appendChild(document.createElement('td')).textContent = u.nombre || '';
  const gestionar = puedeGestionarVinculos(u);
  tr.appendChild(document.createElement('td')).appendChild(
    ulVinculos(u.centros_vinculos, (c) => {
      const li = document.createElement('li');
      const txt = document.createElement('span');
      txt.textContent = (c.codigo ? c.codigo + ' — ' : '') + c.nombre + ' (' + c.rol + ')';
      li.appendChild(txt);
      if (gestionar) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-quitar-vinculo peligro';
        btn.textContent = I18N_ADMIN_USUARIOS.quitar;
        btn.onclick = () => quitarCentro(u, c);
        li.appendChild(btn);
      }
      return li;
    }),
  );
  tr.appendChild(document.createElement('td')).appendChild(
    ulVinculos(u.personas_vinculos, (p) => {
      const li = document.createElement('li');
      const txt = document.createElement('span');
      if (p.es_libro_personal) {
        txt.textContent = I18N_ADMIN_USUARIOS.libroPersonal;
      } else {
        const nom = p.nombre_completo || p.iniciales || String(p.persona_id);
        const centro = p.centro_codigo ? p.centro_codigo + ' — ' + p.centro_nombre : p.centro_nombre;
        txt.textContent = centro + ' — ' + nom + (p.anio ? ' (' + p.anio + ')' : '');
      }
      li.appendChild(txt);
      if (gestionar && !p.es_libro_personal) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-quitar-vinculo peligro';
        btn.textContent = I18N_ADMIN_USUARIOS.quitar;
        btn.onclick = () => quitarPersona(u, p);
        li.appendChild(btn);
      }
      return li;
    }),
  );
  const tdAcc = document.createElement('td');
  tdAcc.className = 'admin-usuario-acciones';
  if (!u.es_admin && u.id !== OPERADOR_ID) {
    const btn2fa = document.createElement('button');
    btn2fa.type = 'button';
    btn2fa.textContent = I18N_ADMIN_USUARIOS.reiniciar2fa;
    btn2fa.onclick = () => reiniciarTotp(u);
    tdAcc.appendChild(btn2fa);
  }
  if (!u.es_admin && u.baja_centro) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = I18N_ADMIN_USUARIOS.reactivar;
    btn.onclick = () => reactivarCentro(u);
    tdAcc.appendChild(btn);
  } else if (!u.es_admin && (u.es_personal || u.es_secretario || ((u.centros ?? 0) === 0 && (u.personas ?? 0) === 0))) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'peligro';
    btn.textContent = I18N_ADMIN_USUARIOS.borrar;
    btn.onclick = () => borrarUsuario(u);
    tdAcc.appendChild(btn);
  }
  tr.appendChild(tdAcc);
  return tr;
}
async function reiniciarTotp(u) {
  if (!confirm(I18N_ADMIN_USUARIOS.confirmReiniciar2fa)) return;
  const s = await api('/api/admin/usuarios/' + u.id + '/reiniciar-totp', { method: 'POST', body: { confirmar: true } });
  if (!s.ok) return alert(s.error);
  await loadUsuarios();
}
async function quitarCentro(u, c) {
  if (!confirm(I18N_ADMIN_USUARIOS.confirmQuitarCentro)) return;
  const s = await api('/api/admin/usuarios/' + u.id + '/centros/' + c.centro_id + '/desvincular', { method: 'POST', body: {} });
  if (!s.ok) return alert(s.error);
  await loadUsuarios();
}
async function quitarPersona(u, p) {
  if (!confirm(I18N_ADMIN_USUARIOS.confirmQuitarPersona)) return;
  const s = await api('/api/admin/usuarios/' + u.id + '/personas/' + p.persona_id + '/desvincular', { method: 'POST', body: {} });
  if (!s.ok) return alert(s.error);
  await loadUsuarios();
}
async function borrarUsuario(u) {
  const prev = await api('/api/admin/usuarios/' + u.id + '/borrar');
  if (!prev.ok) return alert(prev.error);
  if (!prev.puede_borrar) return alert(prev.motivo_bloqueo || prev.error);
  const esCentro = prev.es_secretario === true;
  const esVacia = prev.es_vacia === true;
  let msg = esCentro
    ? I18N_ADMIN_USUARIOS.confirmCentroTitulo
    : (esVacia ? I18N_ADMIN_USUARIOS.confirmVaciaTitulo : I18N_ADMIN_USUARIOS.confirmPersonalTitulo);
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
function pintarDuplicados(grupos) {
  const cont = document.getElementById('duplicados-correo');
  if (!cont) return;
  cont.innerHTML = '';
  if (!grupos || grupos.length === 0) {
    cont.textContent = I18N_ADMIN_USUARIOS.sinDuplicados;
    cont.className = 'muted';
    return;
  }
  cont.className = '';
  grupos.forEach((g) => {
    const sec = document.createElement('section');
    sec.style.marginBottom = '1rem';
    const h = document.createElement('h3');
    h.style.fontSize = '1rem';
    h.style.margin = '0 0 .35rem';
    h.textContent = g.email;
    sec.appendChild(h);
    const ul = document.createElement('ul');
    ul.className = 'admin-vinculos';
    (g.identidades || []).forEach((i) => {
      const li = document.createElement('li');
      li.textContent = (i.alias || '?') + ' — ' + (i.nombre || '') +
        ' (' + (i.centros ?? 0) + ' ' + 'centros, ' + (i.personas ?? 0) + ' personas)';
      ul.appendChild(li);
    });
    sec.appendChild(ul);
    const sel = document.createElement('select');
    (g.identidades || []).forEach((i) => {
      const o = document.createElement('option');
      o.value = String(i.id);
      o.textContent = I18N_ADMIN_USUARIOS.principal + ': ' + (i.alias || i.id);
      sel.appendChild(o);
    });
    sec.appendChild(sel);
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = I18N_ADMIN_USUARIOS.fusionar;
    btn.onclick = () => fusionarLegacy(g.email, Number(sel.value));
    sec.appendChild(btn);
    cont.appendChild(sec);
  });
}
async function fusionarLegacy(email, identidadPrincipalId) {
  const prev = await api('/api/admin/usuarios/fusionar-legacy?email=' + encodeURIComponent(email) +
    '&identidad_principal_id=' + identidadPrincipalId);
  if (!prev.ok) return alert(prev.error);
  let msg = prev.texto || '';
  if (!confirm(msg + '\n\n' + I18N_ADMIN_USUARIOS.confirmFusion)) return;
  const s = await api('/api/admin/usuarios/fusionar-legacy', {
    method: 'POST',
    body: { email, identidad_principal_id: identidadPrincipalId, confirmar: true },
  });
  if (!s.ok) return alert(s.error);
  await loadUsuarios();
}
async function loadUsuarios() {
  const r = await api('/api/admin/usuarios');
  if (!r.ok) return alert(r.error);
  const tb = document.querySelector('#tabla-usuarios tbody');
  tb.innerHTML = '';
  (r.usuarios || []).forEach((u) => tb.appendChild(filaUsuario(u)));
  pintarDuplicados(r.duplicados_correo || []);
}
document.addEventListener('DOMContentLoaded', loadUsuarios);
</script>
