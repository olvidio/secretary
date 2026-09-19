<h1><?= _("Copias de seguridad") ?></h1>
<p class="muted">
    <?= _("Volcado completo de PostgreSQL") ?> (<span id="db-name">…</span>). <?= _("Afecta a todos los centros de esta base. Los Excel (.xlsm) no se incluyen; guarde también el .env si usa TOTP.") ?>
</p>

<section>
    <h2><?= _("Nueva copia") ?></h2>
    <p class="muted"><?= _("Genera un fichero SQL (.sql) en el servidor y lo añade al listado.") ?></p>
    <button type="button" id="btn-backup"><?= _("Crear copia ahora") ?></button>
    <p class="peligro" id="aviso-limite-copias" hidden><?= _("Solo se permite tener 5 copias en el servidor") ?></p>
    <button type="button" id="btn-backup-reemplazar" hidden><?= _("Borrar la más antigua y guardar") ?></button>
    <p class="ok" id="msg-backup" hidden></p>
</section>

<section>
    <h2><?= _("Copias en el servidor") ?></h2>
    <table id="tabla-copias">
        <thead>
        <tr><th><?= _("Fichero") ?></th><th><?= _("Fecha") ?></th><th><?= _("Tamaño") ?></th><th></th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <p class="muted" id="sin-copias" hidden><?= _("Aún no hay copias guardadas en el servidor.") ?></p>
</section>

<section>
    <h2><?= _("Restaurar") ?></h2>
    <p class="muted peligro">
        <?= _("La restauración sobrescribe toda la base. Cierre otras sesiones antes de continuar.") ?>
    </p>
    <p class="muted"><?= _("Elija una copia ya guardada en el servidor y pulse Restaurar en la tabla.") ?></p>
    <form id="form-restore" class="grid-form">
        <label><?= _("O fichero local (.sql o .dump)") ?>
            <input name="dump" type="file" accept=".sql,.dump,text/plain,application/octet-stream">
        </label>
        <button type="submit" class="peligro"><?= _("Restaurar desde fichero local") ?></button>
    </form>
    <p class="ok" id="msg-restore" hidden></p>
</section>

<script>
const I18N_COPIAS = {
  descargar: <?= json_encode(_("Descargar"), JSON_UNESCAPED_UNICODE) ?>,
  restaurar: <?= json_encode(_("Restaurar"), JSON_UNESCAPED_UNICODE) ?>,
  borrar: <?= json_encode(_("Borrar"), JSON_UNESCAPED_UNICODE) ?>,
  noListado: <?= json_encode(_("No se pudo cargar el listado"), JSON_UNESCAPED_UNICODE) ?>,
  confirmBorrar: <?= json_encode(_("¿Borrar «%s» del servidor? Esta acción no se puede deshacer."), JSON_UNESCAPED_UNICODE) ?>,
  confirmRestaurar: <?= json_encode(_("¿Restaurar «%s»? Se sobrescribirá toda la base %s."), JSON_UNESCAPED_UNICODE) ?>,
  restauracionOk: <?= json_encode(_("Restauración completada."), JSON_UNESCAPED_UNICODE) ?>,
  copiaCreada: <?= json_encode(_("Copia creada: %s (%s)."), JSON_UNESCAPED_UNICODE) ?>,
  elijaFichero: <?= json_encode(_("Elija un fichero .sql o .dump"), JSON_UNESCAPED_UNICODE) ?>,
  confirmLocal: <?= json_encode(_("¿Restaurar desde el fichero local? Se sobrescribirá toda la base."), JSON_UNESCAPED_UNICODE) ?>,
  errorRestaurar: <?= json_encode(_("Error al restaurar"), JSON_UNESCAPED_UNICODE) ?>,
  respuestaNoJson: <?= json_encode(_("Respuesta no JSON"), JSON_UNESCAPED_UNICODE) ?>,
  limiteCopias: <?= json_encode(_("Solo se permite tener 5 copias en el servidor"), JSON_UNESCAPED_UNICODE) ?>,
};

function fmtBytes(n) {
  if (n < 1024) return n + ' B';
  if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
  return (n / (1024 * 1024)).toFixed(1) + ' MB';
}

async function loadCopias() {
  const r = await api('/api/copias');
  if (!r.ok) return alert(r.error || I18N_COPIAS.noListado);
  document.getElementById('db-name').textContent = r.database || 'secretario';
  const tb = document.querySelector('#tabla-copias tbody');
  const vacio = document.getElementById('sin-copias');
  tb.innerHTML = '';
  const copias = r.copias || [];
  vacio.hidden = copias.length > 0;
  copias.forEach((c) => {
    const tr = document.createElement('tr');
    tr.innerHTML =
      '<td><code>' + esc(c.filename) + '</code></td>' +
      '<td>' + esc(c.fecha) + '</td>' +
      '<td>' + esc(fmtBytes(c.bytes || 0)) + '</td>' +
      '<td class="acciones">' +
        '<a href="/api/copias/descargar?fichero=' + encodeURIComponent(c.filename) + '">' + esc(I18N_COPIAS.descargar) + '</a> ' +
        '<button type="button" class="btn-restore-server peligro" data-fichero="' + esc(c.filename) + '">' + esc(I18N_COPIAS.restaurar) + '</button> ' +
        '<button type="button" class="btn-borrar-server peligro" data-fichero="' + esc(c.filename) + '">' + esc(I18N_COPIAS.borrar) + '</button>' +
      '</td>';
    tb.appendChild(tr);
  });
  tb.querySelectorAll('.btn-restore-server').forEach((btn) => {
    btn.addEventListener('click', () => restaurarServidor(btn.dataset.fichero));
  });
  tb.querySelectorAll('.btn-borrar-server').forEach((btn) => {
    btn.addEventListener('click', () => borrarServidor(btn.dataset.fichero));
  });
}

async function borrarServidor(fichero) {
  if (!fichero) return;
  const msg = I18N_COPIAS.confirmBorrar.replace('%s', fichero);
  if (!confirm(msg)) {
    return;
  }
  const s = await api('/api/copias/borrar', { method: 'POST', body: { fichero } });
  if (!s.ok) return alert(s.error);
  await loadCopias();
}

async function restaurarServidor(fichero) {
  if (!fichero) return;
  const db = document.getElementById('db-name').textContent || '';
  const msg = I18N_COPIAS.confirmRestaurar.replace('%s', fichero).replace('%s', db);
  if (!confirm(msg)) {
    return;
  }
  const msgEl = document.getElementById('msg-restore');
  msgEl.hidden = true;
  const s = await api('/api/copias/restore', { method: 'POST', body: { fichero, confirmar: true } });
  if (!s.ok) return alert(s.error);
  msgEl.hidden = false;
  msgEl.textContent = s.mensaje || I18N_COPIAS.restauracionOk;
}

async function crearCopia(borrarMasAntigua) {
  const btn = document.getElementById('btn-backup');
  const btnReemplazar = document.getElementById('btn-backup-reemplazar');
  const aviso = document.getElementById('aviso-limite-copias');
  const msg = document.getElementById('msg-backup');
  btn.disabled = true;
  btnReemplazar.disabled = true;
  msg.hidden = true;
  if (!borrarMasAntigua) {
    aviso.hidden = true;
    btnReemplazar.hidden = true;
  }
  try {
    const body = borrarMasAntigua ? { borrar_mas_antigua: true } : {};
    const s = await api('/api/copias/backup', { method: 'POST', body });
    if (!s.ok) {
      if (s.codigo === 'limite_copias') {
        aviso.textContent = s.error || I18N_COPIAS.limiteCopias;
        aviso.hidden = false;
        btnReemplazar.hidden = false;
        return;
      }
      return alert(s.error);
    }
    aviso.hidden = true;
    btnReemplazar.hidden = true;
    msg.hidden = false;
    msg.textContent = I18N_COPIAS.copiaCreada
      .replace('%s', s.filename || '')
      .replace('%s', fmtBytes(s.bytes || 0));
    await loadCopias();
  } finally {
    btn.disabled = false;
    btnReemplazar.disabled = false;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  loadCopias();
  document.getElementById('btn-backup').addEventListener('click', () => crearCopia(false));
  document.getElementById('btn-backup-reemplazar').addEventListener('click', () => crearCopia(true));
  document.getElementById('form-restore').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const msg = document.getElementById('msg-restore');
    msg.hidden = true;
    const input = ev.target.querySelector('[name=dump]');
    if (!input.files || !input.files[0]) {
      return alert(I18N_COPIAS.elijaFichero);
    }
    if (!confirm(I18N_COPIAS.confirmLocal)) {
      return;
    }
    const fd = new FormData(ev.target);
    fd.append('confirmar', '1');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const res = await fetch('/api/copias/restore', {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf },
      body: fd,
    });
    const s = await res.json().catch(() => ({ ok: false, error: I18N_COPIAS.respuestaNoJson }));
    if (!s.ok) return alert(s.error || I18N_COPIAS.errorRestaurar);
    msg.hidden = false;
    msg.textContent = s.mensaje || I18N_COPIAS.restauracionOk;
    ev.target.reset();
  });
});
</script>
