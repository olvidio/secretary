<?php $esClub = !empty($esClub); $esCentroSg = !empty($esCentroSg); $esPlanPropio = $esClub || $esCentroSg; ?>
<h1><?= _("Configuración") ?></h1>
<form id="form-config" class="grid-form">
    <label><?= $esClub ? _("Sigla") : _("Centro") ?> <input name="centro" required></label>
    <label><?= _("Año") ?> <input name="anio" type="number" required></label>
    <label><?= _("Ejercicio") ?>
        <select name="modo_ejercicio">
            <option><?= _("Año") ?></option>
            <option><?= _("Curso") ?></option>
        </select>
    </label>
    <label><?= _("Fecha inicio") ?> <input name="fecha_inicio" type="date" required></label>
    <label><?= _("Fecha cierre") ?> <input name="fecha_cierre" type="date" required></label>
    <?php if (!$esPlanPropio): ?>
    <label><?= _("Tipo de centro") ?>
        <select name="tipo">
            <option value="n"><?= _("n") ?></option>
            <option value="sg"><?= _("sg") ?></option>
        </select>
    </label>
    <label><?= _("Tipo de cierre") ?>
        <select name="tipo_cierre">
            <option value="vivienda"><?= _("Vivienda — P 21 / G 11") ?></option>
            <option value="necesidades"><?= _("Necesidades — P 6 / G 14") ?></option>
        </select>
    </label>
    <label><?= _("Plan contable") ?>
        <select name="plan_contable" required></select>
    </label>
    <?php endif; ?>
    <button type="submit"><?= _("Guardar") ?></button>
    <p class="ok" id="msg" hidden><?= _("Guardado") ?></p>
</form>
<?php if (!$esPlanPropio): ?>
<section>
    <h2><?= _("Tramos de desgravación") ?></h2>
    <p class="muted"><?= _("Se usan al proponer destinos 7. El primer tramo (p. ej. 250 € al 80 %) se reparte entre varias personas antes de subir el importe de una sola. El máximo es el 10 % de la base liquidable de cada uno (art. 69.1 de la Ley del IRPF); lo que pase de ese tope va a partidas 7 que no desgravan.") ?></p>
    <label><?= _("Máximo (% de la base liquidable)") ?>
        <input id="maximo-pct" type="number" min="1" max="100" value="10" required>
    </label>
    <table id="tabla-tramos">
        <thead><tr><th><?= _("Hasta (€, vacío = resto hasta el máximo)") ?></th><th>%</th><th></th></tr></thead>
        <tbody></tbody>
    </table>
    <p>
        <button type="button" id="btn-add-tramo"><?= _("Añadir tramo") ?></button>
        <button type="button" id="btn-save-tramos"><?= _("Guardar tramos") ?></button>
    </p>
    <p class="ok" id="msg-tramos" hidden><?= _("Tramos guardados") ?></p>
</section>
<?php endif; ?>
<?php if ($esClub): ?>
<section>
    <h2><?= _("Usuarios de esta associació") ?></h2>
    <p class="muted"><?= _("Quien lleve otra associació no ve los datos de ésta.") ?></p>
    <p id="centro-actual" class="muted"><?= _("Cargando…") ?></p>
    <table id="tabla-usuarios">
        <thead>
        <tr><th><?= _("Usuario") ?></th><th><?= _("Correo") ?></th><th><?= _("Nombre del usuario") ?></th><th><?= _("Rol") ?></th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <h3><?= _("Añadir usuario") ?></h3>
    <form id="form-usuario" class="grid-form">
        <label><?= _("Usuario (alias)") ?> <input name="usuario" required placeholder="<?= htmlspecialchars(_("p. ej. scl"), ENT_QUOTES) ?>"></label>
        <label><?= _("Correo") ?> <input name="email" type="email" required></label>
        <label><?= _("Contraseña") ?> <input name="password" type="password" required minlength="6"></label>
        <label><?= _("Nombre del usuario") ?> <input name="nombre" autocomplete="name"></label>
        <button type="submit"><?= _("Vincular") ?></button>
    </form>
    <h3><?= _("Importar Grisbi") ?></h3>
    <p class="muted"><?= _("Carga un fichero .gsb en esta associació. Las categorías nuevas se crean como cuentas y los movimientos como asientos. Volver a importar el mismo fichero no duplica.") ?></p>
    <form id="form-grisbi" class="grid-form">
        <label><?= _("Fichero .gsb") ?> <input name="grisbi" type="file" accept=".gsb,.xml,text/xml" required></label>
        <button type="submit"><?= _("Importar") ?></button>
    </form>
    <p class="ok" id="msg-import" hidden></p>
    <p class="muted"><?= _("Mientras estemos de pruebas: vaciar asientos para volver a cargar un fichero. Quedan la associació y los usuarios.") ?></p>
    <button type="button" id="btn-vaciar" class="peligro"><?= _("Vaciar datos (pruebas)") ?></button>
    <p class="ok" id="msg-vaciar" hidden></p>
</section>
<?php endif; ?>
<script>
const I18N_CONFIG = {
  quitar: <?= json_encode(_("Quitar"), JSON_UNESCAPED_UNICODE) ?>,
  noCentro: <?= json_encode(_("No se pudo cargar el centro"), JSON_UNESCAPED_UNICODE) ?>,
  grisbiImportado: <?= json_encode(_("Grisbi importado."), JSON_UNESCAPED_UNICODE) ?>,
  confirmVaciarClub: <?= json_encode(_("Esto borra los asientos de ESTA associació para poder volver a importar. Quedan la associació y los usuarios. ¿Seguro?"), JSON_UNESCAPED_UNICODE) ?>,
  vaciadosClub: <?= json_encode(_("Vaciados %s asientos en %s ejercicio(s). Ya puedes importar el fichero."), JSON_UNESCAPED_UNICODE) ?>,
};
async function cargarAssociacio() {
  const r = await api('/api/centros');
  const p = document.getElementById('centro-actual');
  const tb = document.querySelector('#tabla-usuarios tbody');
  tb.innerHTML = '';
  if (!r.ok) {
    p.textContent = r.error || I18N_CONFIG.noCentro;
    return;
  }
  const c = r.centro || {};
  p.textContent = (c.nombre || c.codigo || '') + (c.plan_contable ? ' · plan ' + c.plan_contable : '');
  (r.usuarios || []).forEach((u) => {
    const tr = document.createElement('tr');
    tr.innerHTML = '<td>' + esc(u.alias) + '</td><td>' + esc(u.email) + '</td><td>' + esc(u.nombre) + '</td><td>' + esc(u.rol) + '</td>';
    tb.appendChild(tr);
  });
}
function filaTramo(t = {}) {
  const tr = document.createElement('tr');
  const hasta = t.hasta_cents == null ? '' : (Number(t.hasta_cents) / 100).toFixed(2);
  tr.innerHTML = '<td><input name="hasta" inputmode="decimal" value="' + esc(hasta) + '"></td>'
    + '<td><input name="pct" type="number" min="0" max="100" required value="' + esc(String(t.porcentaje ?? '')) + '"></td>'
    + '<td><button type="button" class="btn-quitar">' + esc(I18N_CONFIG.quitar) + '</button></td>';
  tr.querySelector('.btn-quitar').onclick = () => tr.remove();
  return tr;
}
async function loadTramos() {
  const r = await api('/api/desgravacion-tramos');
  const tb = document.querySelector('#tabla-tramos tbody');
  tb.innerHTML = '';
  (r.tramos || []).forEach((t) => tb.appendChild(filaTramo(t)));
  const maximo = document.getElementById('maximo-pct');
  if (maximo) maximo.value = String(r.maximo_pct ?? 10);
}
function rellenarPlanes(select, planes, seleccionado) {
  select.innerHTML = '';
  (planes || []).forEach((p) => {
    const opt = document.createElement('option');
    opt.value = p.codigo;
    opt.textContent = p.nombre || p.codigo;
    select.appendChild(opt);
  });
  if (seleccionado) select.value = seleccionado;
}
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/configuracion');
  const planSelect = document.querySelector('#form-config [name=plan_contable]');
  if (planSelect) rellenarPlanes(planSelect, r.planes, r.config?.plan_contable);
  fillForm(document.getElementById('form-config'), r.config);
  document.getElementById('form-config').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const body = formObj(ev.target);
    const s = await api('/api/configuracion', {method:'POST', body});
    document.getElementById('msg').hidden = !s.ok;
    if (!s.ok) alert(s.error);
  });
  if (document.getElementById('form-usuario')) cargarAssociacio();
  if (!document.getElementById('tabla-tramos')) return;
  loadTramos();
  document.getElementById('btn-add-tramo').onclick = () => {
    document.querySelector('#tabla-tramos tbody').appendChild(filaTramo({ porcentaje: 40 }));
  };
  document.getElementById('btn-save-tramos').onclick = async () => {
    const tramos = [...document.querySelectorAll('#tabla-tramos tbody tr')].map((tr) => {
      const hasta = tr.querySelector('[name=hasta]').value.trim();
      const pct = Number(tr.querySelector('[name=pct]').value);
      let hastaCents = null;
      if (hasta !== '') {
        const n = Number(hasta.replace(',', '.'));
        hastaCents = Math.round(n * 100);
      }
      return { hasta_cents: hastaCents, porcentaje: pct };
    });
    const s = await api('/api/desgravacion-tramos', {
      method: 'POST',
      body: {
        tramos,
        maximo_pct: Number(document.getElementById('maximo-pct').value),
      },
    });
    document.getElementById('msg-tramos').hidden = !s.ok;
    if (!s.ok) alert(s.error);
  };
});
document.getElementById('form-usuario')?.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const s = await api('/api/centros/usuarios', { method: 'POST', body: formObj(ev.target) });
  if (!s.ok) return alert(s.error);
  ev.target.reset();
  cargarAssociacio();
});
document.getElementById('form-grisbi')?.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const btn = ev.target.querySelector('button[type=submit]');
  const msg = document.getElementById('msg-import');
  btn.disabled = true;
  msg.hidden = true;
  try {
    const s = await api('/api/grisbi/importar', { method: 'POST', body: new FormData(ev.target) });
    if (!s.ok) return alert(s.error || 'Error');
    msg.hidden = false;
    msg.textContent = I18N_CONFIG.grisbiImportado
      + ' Altas: ' + (s.altas || 0) + '. Omitidos: ' + (s.omitidos || 0) + '. Listados: ' + (s.listados || 0)
      + ((s.avisos && s.avisos.length) ? ' ' + s.avisos.join(' ') : '');
    ev.target.reset();
  } finally {
    btn.disabled = false;
  }
});
document.getElementById('btn-vaciar')?.addEventListener('click', async () => {
  if (!confirm(I18N_CONFIG.confirmVaciarClub)) return;
  const s = await api('/api/centros/vaciar', { method: 'POST', body: { confirmar: true } });
  if (!s.ok) return alert(s.error);
  const msg = document.getElementById('msg-vaciar');
  msg.hidden = false;
  msg.textContent = I18N_CONFIG.vaciadosClub.replace('%s', s.asientos || 0).replace('%s', s.ejercicios || 0);
});
</script>
