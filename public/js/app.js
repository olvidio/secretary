function t(key) {
  return (window.I18N && window.I18N[key]) || key;
}

function secretaryLocale() {
  return window.SECRETARY_LOCALE || 'es-ES';
}

function htmlRolCentro(usuario, soloConsulta) {
  const rol = usuario.rol === 'consulta' ? 'consulta' : 'admin';
  if (soloConsulta) {
    return esc(rol === 'consulta' ? t('rol_consulta') : t('rol_modificar'));
  }
  return '<select data-rol-id="' + Number(usuario.id) + '" data-rol-previo="' + rol + '">'
    + '<option value="admin"' + (rol === 'admin' ? ' selected' : '') + '>' + esc(t('rol_modificar')) + '</option>'
    + '<option value="consulta"' + (rol === 'consulta' ? ' selected' : '') + '>' + esc(t('rol_consulta')) + '</option>'
    + '</select>';
}

function htmlQuitarUsuarioCentro(usuario, soloConsulta, miIdentidadId) {
  if (soloConsulta || !usuario || !usuario.id) return '';
  if (Number(miIdentidadId) === Number(usuario.id)) return '';
  return '<button type="button" class="btn-quitar-usuario-centro" data-identidad-id="'
    + Number(usuario.id) + '">' + esc(t('quitar')) + '</button>';
}

function filaUsuariosCentroHtml(usuario, soloConsulta, miIdentidadId) {
  return '<td>' + esc(usuario.alias) + '</td>'
    + '<td>' + esc(usuario.email) + '</td>'
    + '<td>' + esc(usuario.nombre) + '</td>'
    + '<td>' + htmlRolCentro(usuario, soloConsulta) + '</td>'
    + '<td class="acciones-centro-usuario">' + htmlQuitarUsuarioCentro(usuario, soloConsulta, miIdentidadId) + '</td>';
}

async function quitarUsuarioCentro(identidadId) {
  if (!identidadId) return { ok: false };
  if (!confirm(t('quitar_usuario_centro_confirm'))) {
    return { ok: false, cancelado: true };
  }
  return api('/api/centros/usuarios/quitar', {
    method: 'POST',
    body: { identidad_id: identidadId },
  });
}

async function api(url, opts = {}) {
  const method = String(opts.method || 'GET').toUpperCase();
  if (
    document.body.classList.contains('solo-consulta')
    && (method === 'POST' || method === 'PUT' || method === 'PATCH' || method === 'DELETE')
    && !String(url).startsWith('/api/preferencias')
    && !String(url).startsWith('/api/ayuda/')
  ) {
    return { ok: false, error: t('solo_consulta') };
  }
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const headers = {
    'Accept': 'application/json',
    'X-CSRF-Token': csrf,
    ...(opts.headers || {}),
  };
  let body = opts.body;
  if (body && typeof body === 'object' && !(body instanceof FormData)) {
    if (csrf && body._csrf === undefined) {
      body = Object.assign({ _csrf: csrf }, body);
    }
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(body);
  }
  const res = await fetch(url, {...opts, headers, body});
  const data = await res.json().catch(() => ({ok: false, error: t('respuesta_no_json')}));
  if (typeof data.ok === 'undefined') data.ok = res.ok;
  return data;
}

function formObj(form) {
  const o = {};
  new FormData(form).forEach((v, k) => { o[k] = v; });
  return o;
}

function fillForm(form, data) {
  if (!data) return;
  Object.keys(data).forEach((k) => {
    const el = form.querySelector(`[name="${k}"]`);
    if (el && data[k] !== null && data[k] !== undefined) el.value = data[k];
  });
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));
}

function fmtFecha(iso) {
  if (!iso) return '';
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
}

/** Parsea importe es-ES (1.234,56 · 1.234 · 1234,5). */
function parseImporteEs(valor) {
  const s = String(valor).trim().replace(/\s/g, '');
  if (!s) return NaN;
  if (s.includes(',')) {
    return Number(s.replace(/\./g, '').replace(',', '.'));
  }
  if (/^-?\d{1,3}(\.\d{3})+$/.test(s)) {
    return Number(s.replace(/\./g, ''));
  }
  return Number(s.replace(',', '.'));
}

/** Euros enteros con separador de miles (1.234). */
function fmtEnteroEs(valor) {
  if (valor === null || valor === undefined || String(valor).trim() === '') return '';
  const n = parseImporteEs(valor);
  if (!Number.isFinite(n)) return String(valor).trim();
  const entero = Math.round(n);
  if (entero === 0) return '';
  return entero.toLocaleString(secretaryLocale(), {
    useGrouping: true,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
}

/** Importe en formato es-ES (1.234,56). Acepta entrada con coma o punto decimal. */
function fmtImporteEs(valor) {
  if (valor === null || valor === undefined || String(valor).trim() === '') return '';
  const s = String(valor).trim().replace(/\s/g, '');
  const n = s.includes(',')
    ? Number(s.replace(/\./g, '').replace(',', '.'))
    : Number(s.replace(',', '.'));
  if (!Number.isFinite(n)) return String(valor);
  return n.toLocaleString(secretaryLocale(), {
    useGrouping: true,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

document.addEventListener('click', async (ev) => {
  const btn = ev.target.closest('.btn-quitar-usuario-centro');
  if (!btn || document.body.classList.contains('solo-consulta')) return;
  const identidadId = Number(btn.dataset.identidadId || 0);
  const s = await quitarUsuarioCentro(identidadId);
  if (s.cancelado) return;
  if (!s.ok) return alert(s.error || t('error'));
  document.dispatchEvent(new CustomEvent('centro-usuarios-actualizados', { detail: s }));
});

document.addEventListener('change', async (ev) => {
  const sel = ev.target.closest('select[data-rol-id]');
  if (!sel) return;
  const previo = sel.dataset.rolPrevio || 'admin';
  const s = await api('/api/centros/usuarios/rol', {
    method: 'POST',
    body: { identidad_id: Number(sel.dataset.rolId), rol: sel.value },
  });
  if (!s.ok) {
    sel.value = previo;
    alert(s.error || t('error'));
    return;
  }
  sel.dataset.rolPrevio = sel.value;
});

document.addEventListener('click', (ev) => {
  document.querySelectorAll('details.user-menu[open]').forEach((d) => {
    if (!d.contains(ev.target)) d.removeAttribute('open');
  });
});
