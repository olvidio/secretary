<h1>Copias de seguridad</h1>
<p class="muted">
    Volcado completo de PostgreSQL (<span id="db-name">…</span>). Afecta a todos los centros
    de esta base. Los Excel (<code>.xlsm</code>) no se incluyen; guarde también el
    <code>.env</code> si usa TOTP.
</p>

<section>
    <h2>Nueva copia</h2>
    <p class="muted">Genera un fichero SQL (<code>.sql</code>) en el servidor y lo añade al listado.</p>
    <button type="button" id="btn-backup">Crear copia ahora</button>
    <p class="ok" id="msg-backup" hidden></p>
</section>

<section>
    <h2>Copias en el servidor</h2>
    <table id="tabla-copias">
        <thead>
        <tr><th>Fichero</th><th>Fecha</th><th>Tamaño</th><th></th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <p class="muted" id="sin-copias" hidden>Aún no hay copias guardadas en el servidor.</p>
</section>

<section>
    <h2>Restaurar</h2>
    <p class="muted peligro">
        La restauración <strong>sobrescribe</strong> toda la base. Cierre otras sesiones antes
        de continuar.
    </p>
    <p class="muted">Elija una copia ya guardada en el servidor y pulse Restaurar en la tabla.</p>
    <form id="form-restore" class="grid-form">
        <label>O fichero local (.sql o .dump)
            <input name="dump" type="file" accept=".sql,.dump,text/plain,application/octet-stream">
        </label>
        <button type="submit" class="peligro">Restaurar desde fichero local</button>
    </form>
    <p class="ok" id="msg-restore" hidden></p>
</section>

<script>
function fmtBytes(n) {
  if (n < 1024) return n + ' B';
  if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
  return (n / (1024 * 1024)).toFixed(1) + ' MB';
}

async function loadCopias() {
  const r = await api('/api/copias');
  if (!r.ok) return alert(r.error || 'No se pudo cargar el listado');
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
        '<a href="/api/copias/descargar?fichero=' + encodeURIComponent(c.filename) + '">Descargar</a> ' +
        '<button type="button" class="btn-restore-server peligro" data-fichero="' + esc(c.filename) + '">Restaurar</button> ' +
        '<button type="button" class="btn-borrar-server peligro" data-fichero="' + esc(c.filename) + '">Borrar</button>' +
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
  if (!confirm('¿Borrar «' + fichero + '» del servidor? Esta acción no se puede deshacer.')) {
    return;
  }
  const s = await api('/api/copias/borrar', { method: 'POST', body: { fichero } });
  if (!s.ok) return alert(s.error);
  await loadCopias();
}

async function restaurarServidor(fichero) {
  if (!fichero) return;
  if (!confirm('¿Restaurar «' + fichero + '»? Se sobrescribirá toda la base ' + (document.getElementById('db-name').textContent || '') + '.')) {
    return;
  }
  const msg = document.getElementById('msg-restore');
  msg.hidden = true;
  const s = await api('/api/copias/restore', { method: 'POST', body: { fichero, confirmar: true } });
  if (!s.ok) return alert(s.error);
  msg.hidden = false;
  msg.textContent = s.mensaje || 'Restauración completada.';
}

document.addEventListener('DOMContentLoaded', () => {
  loadCopias();
  document.getElementById('btn-backup').addEventListener('click', async () => {
    const btn = document.getElementById('btn-backup');
    const msg = document.getElementById('msg-backup');
    btn.disabled = true;
    msg.hidden = true;
    try {
      const s = await api('/api/copias/backup', { method: 'POST', body: {} });
      if (!s.ok) return alert(s.error);
      msg.hidden = false;
      msg.textContent = 'Copia creada: ' + (s.filename || '') + ' (' + fmtBytes(s.bytes || 0) + ').';
      await loadCopias();
    } finally {
      btn.disabled = false;
    }
  });
  document.getElementById('form-restore').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const msg = document.getElementById('msg-restore');
    msg.hidden = true;
    const input = ev.target.querySelector('[name=dump]');
    if (!input.files || !input.files[0]) {
      return alert('Elija un fichero .sql o .dump');
    }
    if (!confirm('¿Restaurar desde el fichero local? Se sobrescribirá toda la base.')) {
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
    const s = await res.json().catch(() => ({ ok: false, error: 'Respuesta no JSON' }));
    if (!s.ok) return alert(s.error || 'Error al restaurar');
    msg.hidden = false;
    msg.textContent = s.mensaje || 'Restauración completada.';
    ev.target.reset();
  });
});
</script>
