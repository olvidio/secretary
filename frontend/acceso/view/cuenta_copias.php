<h1><?= _("Copia del libro personal") ?></h1>
<p class="muted">
    <?= _("Exporta y restaura solo tus movimientos del libro X (categorías propias, extracto banco y fechas de cierre). No afecta al centro ni a las remesas ya aceptadas allí.") ?>
</p>

<section>
    <h2><?= _("Nueva copia") ?></h2>
    <p class="muted"><?= _("Genera un fichero JSON con todos tus movimientos personales.") ?></p>
    <button type="button" id="btn-backup-personal"><?= _("Crear copia ahora") ?></button>
    <p class="ok" id="msg-backup-personal" hidden></p>
</section>

<section>
    <h2><?= _("Copias guardadas") ?></h2>
    <table id="tabla-copias-personal" class="yo-copias-table">
        <colgroup>
            <col class="col-fichero">
            <col class="col-fecha">
            <col class="col-bytes">
            <col class="col-acc">
        </colgroup>
        <thead>
        <tr><th><?= _("Fichero") ?></th><th><?= _("Fecha") ?></th><th><?= _("Tamaño") ?></th><th><?= _("Acciones") ?></th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <p class="muted" id="sin-copias-personal" hidden><?= _("Aún no hay copias guardadas.") ?></p>
</section>

<section>
    <h2><?= _("Restaurar") ?></h2>
    <p class="muted peligro">
        <?= _("Sustituye todos tus movimientos personales actuales por los de la copia. El centro y las remesas aceptadas no cambian.") ?>
    </p>
    <p class="muted"><?= _("Elija una copia de la tabla o suba un fichero .json descargado antes.") ?></p>
    <form id="form-restore-personal" class="grid-form">
        <label><?= _("O fichero local (.json)") ?>
            <input name="dump" type="file" accept=".json,application/json">
        </label>
        <button type="submit" class="peligro"><?= _("Restaurar desde fichero local") ?></button>
    </form>
    <p class="ok" id="msg-restore-personal" hidden></p>
</section>

<script>
const I18N_COPIAS_PERSONAL = {
  descargar: <?= json_encode(_("Descargar"), JSON_UNESCAPED_UNICODE) ?>,
  restaurar: <?= json_encode(_("Restaurar"), JSON_UNESCAPED_UNICODE) ?>,
  borrar: <?= json_encode(_("Borrar"), JSON_UNESCAPED_UNICODE) ?>,
  noListado: <?= json_encode(_("No se pudo cargar el listado"), JSON_UNESCAPED_UNICODE) ?>,
  confirmBorrar: <?= json_encode(_("¿Borrar «%s» del servidor?"), JSON_UNESCAPED_UNICODE) ?>,
  confirmRestaurar: <?= json_encode(_("¿Restaurar «%s»? Se borrarán tus movimientos personales actuales y se sustituirán por los de la copia."), JSON_UNESCAPED_UNICODE) ?>,
  restauracionOk: <?= json_encode(_("Restauración completada."), JSON_UNESCAPED_UNICODE) ?>,
  copiaCreada: <?= json_encode(_("Copia creada: %s (%s, %s movimientos)."), JSON_UNESCAPED_UNICODE) ?>,
  elijaFichero: <?= json_encode(_("Elija un fichero .json"), JSON_UNESCAPED_UNICODE) ?>,
  confirmLocal: <?= json_encode(_("¿Restaurar desde el fichero local? Se sustituirán todos sus movimientos personales."), JSON_UNESCAPED_UNICODE) ?>,
  errorRestaurar: <?= json_encode(_("Error al restaurar"), JSON_UNESCAPED_UNICODE) ?>,
  respuestaNoJson: <?= json_encode(_("Respuesta no JSON"), JSON_UNESCAPED_UNICODE) ?>,
};

function fmtBytes(n) {
  if (n < 1024) return n + ' B';
  if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
  return (n / (1024 * 1024)).toFixed(1) + ' MB';
}

async function loadCopiasPersonal() {
  const r = await api('/api/yo/copias');
  if (!r.ok) return alert(r.error || I18N_COPIAS_PERSONAL.noListado);
  const tb = document.querySelector('#tabla-copias-personal tbody');
  const vacio = document.getElementById('sin-copias-personal');
  tb.innerHTML = '';
  const copias = r.copias || [];
  vacio.hidden = copias.length > 0;
  copias.forEach((c) => {
    const tr = document.createElement('tr');
    tr.innerHTML =
      '<td><code class="copia-nombre" title="' + esc(c.filename) + '">' + esc(c.filename) + '</code></td>' +
      '<td class="copia-fecha" title="' + esc(c.fecha) + '">' + esc(c.fecha) + '</td>' +
      '<td class="num">' + esc(fmtBytes(c.bytes || 0)) + '</td>' +
      '<td class="acciones">' +
        '<a href="/api/yo/copias/descargar?fichero=' + encodeURIComponent(c.filename) + '">' + esc(I18N_COPIAS_PERSONAL.descargar) + '</a>' +
        '<button type="button" class="btn-restore-personal peligro" data-fichero="' + esc(c.filename) + '">' + esc(I18N_COPIAS_PERSONAL.restaurar) + '</button>' +
        '<button type="button" class="btn-borrar-personal peligro" data-fichero="' + esc(c.filename) + '">' + esc(I18N_COPIAS_PERSONAL.borrar) + '</button>' +
      '</td>';
    tb.appendChild(tr);
  });
  tb.querySelectorAll('.btn-restore-personal').forEach((btn) => {
    btn.addEventListener('click', () => restaurarPersonal(btn.dataset.fichero));
  });
  tb.querySelectorAll('.btn-borrar-personal').forEach((btn) => {
    btn.addEventListener('click', () => borrarPersonal(btn.dataset.fichero));
  });
}

async function borrarPersonal(fichero) {
  if (!fichero) return;
  if (!confirm(I18N_COPIAS_PERSONAL.confirmBorrar.replace('%s', fichero))) return;
  const s = await api('/api/yo/copias/borrar', { method: 'POST', body: { fichero } });
  if (!s.ok) return alert(s.error);
  await loadCopiasPersonal();
}

async function restaurarPersonal(fichero) {
  if (!fichero) return;
  if (!confirm(I18N_COPIAS_PERSONAL.confirmRestaurar.replace('%s', fichero))) {
    return;
  }
  const msg = document.getElementById('msg-restore-personal');
  msg.hidden = true;
  const s = await api('/api/yo/copias/restore', { method: 'POST', body: { fichero, confirmar: true } });
  if (!s.ok) return alert(s.error);
  msg.hidden = false;
  msg.textContent = s.mensaje || I18N_COPIAS_PERSONAL.restauracionOk;
}

document.addEventListener('DOMContentLoaded', () => {
  loadCopiasPersonal();
  document.getElementById('btn-backup-personal').addEventListener('click', async () => {
    const btn = document.getElementById('btn-backup-personal');
    const msg = document.getElementById('msg-backup-personal');
    btn.disabled = true;
    msg.hidden = true;
    try {
      const s = await api('/api/yo/copias/backup', { method: 'POST', body: {} });
      if (!s.ok) return alert(s.error);
      msg.hidden = false;
      msg.textContent = I18N_COPIAS_PERSONAL.copiaCreada
        .replace('%s', s.filename || '')
        .replace('%s', fmtBytes(s.bytes || 0))
        .replace('%s', s.movimientos || 0);
      await loadCopiasPersonal();
    } finally {
      btn.disabled = false;
    }
  });
  document.getElementById('form-restore-personal').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const msg = document.getElementById('msg-restore-personal');
    msg.hidden = true;
    const input = ev.target.querySelector('[name=dump]');
    if (!input.files || !input.files[0]) {
      return alert(I18N_COPIAS_PERSONAL.elijaFichero);
    }
    if (!confirm(I18N_COPIAS_PERSONAL.confirmLocal)) {
      return;
    }
    const fd = new FormData(ev.target);
    fd.append('confirmar', '1');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const res = await fetch('/api/yo/copias/restore', {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf },
      body: fd,
    });
    const s = await res.json().catch(() => ({ ok: false, error: I18N_COPIAS_PERSONAL.respuestaNoJson }));
    if (!s.ok) return alert(s.error || I18N_COPIAS_PERSONAL.errorRestaurar);
    msg.hidden = false;
    msg.textContent = s.mensaje || I18N_COPIAS_PERSONAL.restauracionOk;
    ev.target.reset();
  });
});
</script>
