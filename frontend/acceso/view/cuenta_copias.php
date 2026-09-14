<h1>Copia del libro personal</h1>
<p class="muted">
    Exporta y restaura <strong>solo</strong> tus movimientos del libro X (categorías propias,
    extracto banco y fechas de cierre). No afecta al centro ni a las remesas ya aceptadas allí.
</p>

<section>
    <h2>Nueva copia</h2>
    <p class="muted">Genera un fichero JSON con todos tus movimientos personales.</p>
    <button type="button" id="btn-backup-personal">Crear copia ahora</button>
    <p class="ok" id="msg-backup-personal" hidden></p>
</section>

<section>
    <h2>Copias guardadas</h2>
    <table id="tabla-copias-personal" class="yo-copias-table">
        <colgroup>
            <col class="col-fichero">
            <col class="col-fecha">
            <col class="col-bytes">
            <col class="col-acc">
        </colgroup>
        <thead>
        <tr><th>Fichero</th><th>Fecha</th><th>Tamaño</th><th>Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <p class="muted" id="sin-copias-personal" hidden>Aún no hay copias guardadas.</p>
</section>

<section>
    <h2>Restaurar</h2>
    <p class="muted peligro">
        Sustituye <strong>todos</strong> tus movimientos personales actuales por los de la copia.
        El centro y las remesas aceptadas no cambian.
    </p>
    <p class="muted">Elija una copia de la tabla o suba un fichero .json descargado antes.</p>
    <form id="form-restore-personal" class="grid-form">
        <label>O fichero local (.json)
            <input name="dump" type="file" accept=".json,application/json">
        </label>
        <button type="submit" class="peligro">Restaurar desde fichero local</button>
    </form>
    <p class="ok" id="msg-restore-personal" hidden></p>
</section>

<script>
function fmtBytes(n) {
  if (n < 1024) return n + ' B';
  if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
  return (n / (1024 * 1024)).toFixed(1) + ' MB';
}

async function loadCopiasPersonal() {
  const r = await api('/api/yo/copias');
  if (!r.ok) return alert(r.error || 'No se pudo cargar el listado');
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
        '<a href="/api/yo/copias/descargar?fichero=' + encodeURIComponent(c.filename) + '">Descargar</a>' +
        '<button type="button" class="btn-restore-personal peligro" data-fichero="' + esc(c.filename) + '">Restaurar</button>' +
        '<button type="button" class="btn-borrar-personal peligro" data-fichero="' + esc(c.filename) + '">Borrar</button>' +
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
  if (!confirm('¿Borrar «' + fichero + '» del servidor?')) return;
  const s = await api('/api/yo/copias/borrar', { method: 'POST', body: { fichero } });
  if (!s.ok) return alert(s.error);
  await loadCopiasPersonal();
}

async function restaurarPersonal(fichero) {
  if (!fichero) return;
  if (!confirm('¿Restaurar «' + fichero + '»? Se borrarán tus movimientos personales actuales y se sustituirán por los de la copia.')) {
    return;
  }
  const msg = document.getElementById('msg-restore-personal');
  msg.hidden = true;
  const s = await api('/api/yo/copias/restore', { method: 'POST', body: { fichero, confirmar: true } });
  if (!s.ok) return alert(s.error);
  msg.hidden = false;
  msg.textContent = s.mensaje || 'Restauración completada.';
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
      msg.textContent = 'Copia creada: ' + (s.filename || '') + ' (' + fmtBytes(s.bytes || 0)
        + ', ' + (s.movimientos || 0) + ' movimientos).';
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
      return alert('Elija un fichero .json');
    }
    if (!confirm('¿Restaurar desde el fichero local? Se sustituirán todos sus movimientos personales.')) {
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
    const s = await res.json().catch(() => ({ ok: false, error: 'Respuesta no JSON' }));
    if (!s.ok) return alert(s.error || 'Error al restaurar');
    msg.hidden = false;
    msg.textContent = s.mensaje || 'Restauración completada.';
    ev.target.reset();
  });
});
</script>
