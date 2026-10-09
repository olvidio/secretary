<?php $esClub = !empty($esClub); $esFundacion = !empty($esFundacion); $esCentroSg = !empty($esCentroSg); $esPlanPropio = $esClub || $esCentroSg; ?>
<h1><?= _("Configuración") ?></h1>
<form id="form-config" class="grid-form">
    <div class="config-sigla">
        <label><?= _("Sigla") ?> <input name="centro" required></label>
        <?php if (empty($soloConsulta)): ?>
        <button type="button" id="btn-guardar-sigla"><?= _("Guardar sigla") ?></button>
        <?php endif; ?>
    </div>
    <p class="muted config-aviso-ejercicio"><?= _("El ejercicio contable (año, fechas, alta y cierre) se gestiona en Plan y ejercicio → Ejercicios. La fecha de corte del mes se cambia en Fecha cierre.") ?></p>
    <?php if (!$esPlanPropio): ?>
    <label><?= _("Tipo de centro") ?>
        <select name="tipo">
            <option value="n"><?= _("centro n") ?></option>
            <option value="sg"><?= _("centro sg") ?></option>
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
    <?php if (!$esPlanPropio && empty($soloConsulta)): ?>
    <button type="submit"><?= _("Guardar") ?></button>
    <?php endif; ?>
    <p class="ok" id="msg-sigla" hidden><?= _("Guardado") ?></p>
    <?php if (!$esPlanPropio): ?>
    <p class="ok" id="msg" hidden><?= _("Guardado") ?></p>
    <?php endif; ?>
</form>
<?php if ($esCentroSg && empty($soloConsulta)): ?>
<section>
    <h2><?= _("Importar Excel") ?></h2>
    <p class="muted"><?= _("Al empezar de cero, carga el libro Secretario sg (.xlsm o .xlsx). Entran los nombres, el talonario y los destinos de este centro. Si se vuelve a importar, se sustituyen los apuntes que vinieron de un Excel anterior.") ?></p>
    <form id="form-import-sg" class="grid-form">
        <label><?= _("Fichero Excel") ?> <input name="excel" type="file" accept=".xlsm,.xlsx" required></label>
        <label class="inline casilla-legal">
            <input type="checkbox" name="asumo_responsable_nombres" value="1" required>
            <span><?= htmlspecialchars((string) ($textoAsumoNombres ?? _('Declaro que el centro, y yo como secretario, somos responsables del tratamiento de los datos de las personas que doy de alta, importo o vinculo. Secretario es un programa gratuito que solo aloja la información. Tengo base legal para ese tratamiento.')), ENT_QUOTES) ?></span>
        </label>
        <button type="submit"><?= _("Importar Excel") ?></button>
    </form>
    <p class="ok" id="msg-import-sg" hidden></p>
</section>
<?php endif; ?>
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
    <?php if (empty($soloConsulta)): ?>
    <p>
        <button type="button" id="btn-add-tramo"><?= _("Añadir tramo") ?></button>
        <button type="button" id="btn-save-tramos"><?= _("Guardar tramos") ?></button>
    </p>
    <?php endif; ?>
    <p class="ok" id="msg-tramos" hidden><?= _("Tramos guardados") ?></p>
</section>
<?php endif; ?>
<?php if ($esClub || $esCentroSg): ?>
<section>
    <h2><?= $esCentroSg
        ? _("Usuarios de este centro")
        : ($esFundacion ? _("Usuarios de esta fundación") : _("Usuarios de esta associació")) ?></h2>
    <p class="muted"><?= $esCentroSg
        ? _("Quien lleve otro centro no ve los datos de éste.")
        : ($esFundacion
            ? _("Quien lleve otra fundación no ve los datos de ésta.")
            : _("Quien lleve otra associació no ve los datos de ésta.")) ?></p>
    <p id="centro-actual" class="muted"><?= _("Cargando…") ?></p>
    <table id="tabla-usuarios">
        <thead>
        <tr><th><?= _("Alias") ?></th><th><?= _("Correo") ?></th><th><?= _("Nombre") ?></th><th><?= _("Rol") ?></th><th></th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <?php if (empty($soloConsulta)): ?>
    <h3><?= _("Invitar secretario") ?></h3>
    <?php require __DIR__ . '/../../shared/view/_form_invitar_secretario.php'; ?>
    <?php endif; ?>
    <?php if ($esClub && empty($soloConsulta)): ?>
    <h3><?= _("Importar Grisbi") ?></h3>
    <p class="muted"><?= $esFundacion
        ? _("Carga un fichero .gsb en esta fundación. Las categorías nuevas se crean como cuentas y los movimientos como asientos. Volver a importar el mismo fichero no duplica.")
        : _("Carga un fichero .gsb en esta associació. Las categorías nuevas se crean como cuentas y los movimientos como asientos. Volver a importar el mismo fichero no duplica.") ?></p>
    <form id="form-grisbi" class="grid-form">
        <label><?= _("Fichero .gsb") ?> <input name="grisbi" type="file" accept=".gsb,.xml,text/xml" required></label>
        <button type="submit"><?= _("Importar") ?></button>
    </form>
    <p class="ok" id="msg-import" hidden></p>
    <?php endif; ?>
</section>
<?php endif; ?>
<script>
const I18N_CONFIG = {
  quitar: <?= json_encode(_("Quitar"), JSON_UNESCAPED_UNICODE) ?>,
  noCentro: <?= json_encode(_("No se pudo cargar el centro"), JSON_UNESCAPED_UNICODE) ?>,
  grisbiImportado: <?= json_encode(_("Grisbi importado."), JSON_UNESCAPED_UNICODE) ?>,
  excelSgImportado: <?= json_encode(_("Excel importado. Nombres: %s. Apuntes: %s. Fecha de cierre: %s."), JSON_UNESCAPED_UNICODE) ?>,
  faltaResponsable: <?= json_encode(_("Marque que el centro es responsable de los datos de las personas que da de alta."), JSON_UNESCAPED_UNICODE) ?>,
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
  const solo = document.body.classList.contains('solo-consulta');
  const miId = Number(r.mi_identidad_id || 0);
  (r.usuarios || []).forEach((u) => {
    const tr = document.createElement('tr');
    tr.innerHTML = filaUsuariosCentroHtml(u, solo, miId);
    tb.appendChild(tr);
  });
}
function filaTramo(t = {}) {
  const tr = document.createElement('tr');
  const hasta = t.hasta_cents == null ? '' : (Number(t.hasta_cents) / 100).toFixed(2);
  const solo = document.body.classList.contains('solo-consulta');
  const bloqueo = solo ? ' readonly' : '';
  tr.innerHTML = '<td><input name="hasta" inputmode="decimal" value="' + esc(hasta) + '"' + bloqueo + '></td>'
    + '<td><input name="pct" type="number" min="0" max="100" required value="' + esc(String(t.porcentaje ?? '')) + '"' + bloqueo + '></td>'
    + '<td>' + (solo ? '' : '<button type="button" class="btn-quitar">' + esc(I18N_CONFIG.quitar) + '</button>') + '</td>';
  tr.querySelector('.btn-quitar')?.addEventListener('click', () => tr.remove());
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
  document.getElementById('btn-guardar-sigla')?.addEventListener('click', async () => {
    const centro = document.querySelector('#form-config [name=centro]')?.value ?? '';
    const s = await api('/api/configuracion', { method: 'POST', body: { centro } });
    document.getElementById('msg-sigla').hidden = !s.ok;
    if (!s.ok) alert(s.error);
  });
  document.getElementById('form-config').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const body = formObj(ev.target);
    const s = await api('/api/configuracion', {method:'POST', body});
    const msg = document.getElementById('msg');
    if (msg) msg.hidden = !s.ok;
    if (!s.ok) alert(s.error);
  });
  if (document.getElementById('tabla-usuarios')) {
    cargarAssociacio();
    document.addEventListener('centro-usuarios-actualizados', () => cargarAssociacio());
  }
  if (!document.getElementById('tabla-tramos')) return;
  loadTramos();
  const btnAddTramo = document.getElementById('btn-add-tramo');
  if (!btnAddTramo) return;
  btnAddTramo.onclick = () => {
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
document.getElementById('form-import-sg')?.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  if (!ev.target.querySelector('[name=asumo_responsable_nombres]')?.checked) {
    return alert(I18N_CONFIG.faltaResponsable);
  }
  const btn = ev.target.querySelector('button[type=submit]');
  const msg = document.getElementById('msg-import-sg');
  btn.disabled = true;
  msg.hidden = true;
  try {
    const s = await api('/api/centros/import-sg', { method: 'POST', body: new FormData(ev.target) });
    if (!s.ok) return alert(s.error || 'Error');
    const imp = s.importacion || {};
    msg.hidden = false;
    msg.textContent = I18N_CONFIG.excelSgImportado
      .replace('%s', imp.personas || 0)
      .replace('%s', imp.apuntes || 0)
      .replace('%s', fmtFecha(imp.fecha_cierre || ''));
    ev.target.reset();
  } finally {
    btn.disabled = false;
  }
});
</script>
