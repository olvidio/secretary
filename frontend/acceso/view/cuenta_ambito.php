<h1><?= _("Ámbito") ?></h1>
<p class="muted"><?= _("Cambia entre su libro personal y las entidades que lleva, en un solo paso.") ?></p>
<form id="form-ambito" class="grid-form">
    <label><?= _("Ámbito") ?>
        <select name="ambito" required></select>
    </label>
    <button type="submit"><?= _("Cambiar") ?></button>
    <p class="ok" id="msg" hidden><?= _("Guardado") ?></p>
</form>

<section>
    <h2><?= _("Nueva entidad") ?></h2>
    <p class="muted"><?= _("Crea otro centro, asociación o fundación con esta misma cuenta: no hace falta otro alias ni otro correo. Usted queda como quien la lleva y después puede invitar a más personas.") ?></p>
    <form id="form-entidad" class="grid-form">
        <label><?= _("Tipo") ?>
            <select name="tipo_cuenta" required>
                <option value="club"><?= _("Asociación") ?></option>
                <option value="fundacion"><?= _("Fundación") ?></option>
                <option value="centro"><?= _("Centro n") ?></option>
                <option value="centro-sg"><?= _("Centro sg") ?></option>
            </select>
        </label>
        <label><?= _("Sigla") ?> <input name="codigo" autocomplete="off" required></label>
        <label><?= _("Nombre") ?> <input name="nombre" autocomplete="organization" required></label>
        <button type="submit"><?= _("Crear") ?></button>
    </form>
    <p class="ok" id="msg-entidad" hidden></p>
</section>

<section id="bloque-eliminar" hidden>
    <h2><?= _("Eliminar entidad") ?></h2>
    <p class="muted"><?= _("Borra la entidad con todos sus datos: asientos, ejercicios, cuentas, nombres, remesas y los accesos de quienes la llevan. No se puede deshacer; haga antes una copia. Solo aparecen las entidades que usted administra.") ?></p>
    <form id="form-eliminar" class="grid-form">
        <label><?= _("Entidad") ?>
            <select name="centro_id" required></select>
        </label>
        <label><?= _("Escriba la sigla para confirmar") ?> <input name="sigla" autocomplete="off" required></label>
        <button type="submit" class="peligro"><?= _("Eliminar entidad") ?></button>
    </form>
    <p class="ok" id="msg-eliminar" hidden><?= _("Entidad eliminada.") ?></p>
</section>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const formAmbito = document.getElementById('form-ambito');
  const sel = formAmbito.querySelector('[name="ambito"]');
  const cargar = async () => {
    const r = await api('/api/preferencias');
    if (!r.ok) return alert(r.error || <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>);
    const opciones = r.ambitos || [];
    sel.innerHTML = '';
    opciones.forEach((o) => {
      const opt = document.createElement('option');
      opt.value = o.valor;
      opt.textContent = o.etiqueta;
      if (r.ambito_actual === o.valor) opt.selected = true;
      sel.appendChild(opt);
    });
    // Con un solo ámbito no hay nada que cambiar.
    formAmbito.hidden = opciones.length < 2;
    // Solo quien administra una entidad puede eliminarla.
    const propias = (r.centros || []).filter((c) => c.rol !== 'consulta');
    const selEliminar = document.querySelector('#form-eliminar [name="centro_id"]');
    selEliminar.innerHTML = '';
    propias.forEach((c) => {
      const opt = document.createElement('option');
      opt.value = String(c.centro_id);
      opt.textContent = (c.nombre || c.codigo) + ' (' + c.codigo + ')';
      selEliminar.appendChild(opt);
    });
    document.getElementById('bloque-eliminar').hidden = propias.length === 0;
  };
  await cargar();

  formAmbito.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/preferencias/ambito', { method: 'POST', body: { ambito: sel.value } });
    if (!s.ok) return alert(s.error);
    if (s.siguiente && s.siguiente !== location.pathname) {
      location.href = s.siguiente;
      return;
    }
    document.getElementById('msg').hidden = false;
  });

  const formEntidad = document.getElementById('form-entidad');
  formEntidad.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const btn = formEntidad.querySelector('button');
    btn.disabled = true;
    const s = await api('/api/preferencias/entidad', {
      method: 'POST',
      body: {
        tipo_cuenta: formEntidad.tipo_cuenta.value,
        codigo: formEntidad.codigo.value,
        nombre: formEntidad.nombre.value,
      },
    });
    btn.disabled = false;
    if (!s.ok) return alert(s.error || <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>);
    if (s.siguiente) {
      location.href = s.siguiente;
      return;
    }
    // Sin segundo factor no se puede entrar todavía como quien lleva la entidad.
    formEntidad.reset();
    await cargar();
    const msg = document.getElementById('msg-entidad');
    msg.innerHTML = <?= json_encode(_("Entidad creada."), JSON_UNESCAPED_UNICODE) ?> + ' '
      + (s.falta_totp
        ? <?= json_encode(_("Para entrar en ella, active antes el segundo factor en"), JSON_UNESCAPED_UNICODE) ?>
          + ' <a href="/cuenta/personal#totp">' + <?= json_encode(_("Personal → 2FA"), JSON_UNESCAPED_UNICODE) ?> + '</a> '
          + <?= json_encode(_("y elíjala después en esta pantalla."), JSON_UNESCAPED_UNICODE) ?>
        : <?= json_encode(_("Elíjala en el desplegable de arriba."), JSON_UNESCAPED_UNICODE) ?>);
    msg.hidden = false;
  });

  const formEliminar = document.getElementById('form-eliminar');
  formEliminar.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const nombre = formEliminar.centro_id.selectedOptions[0]?.textContent || '';
    if (!confirm(<?= json_encode(_("Se va a eliminar la entidad con todos sus datos y no se puede deshacer:"), JSON_UNESCAPED_UNICODE) ?> + ' ' + nombre)) return;
    const btn = formEliminar.querySelector('button');
    btn.disabled = true;
    const s = await api('/api/preferencias/entidad/eliminar', {
      method: 'POST',
      body: { centro_id: Number(formEliminar.centro_id.value), sigla: formEliminar.sigla.value },
    });
    btn.disabled = false;
    if (!s.ok) return alert(s.error || <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>);
    if (s.siguiente && s.siguiente !== location.pathname) {
      location.href = s.siguiente;
      return;
    }
    formEliminar.reset();
    await cargar();
    document.getElementById('msg-eliminar').hidden = false;
  });
});
</script>
