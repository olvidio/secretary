function t(key) {
  return (window.I18N && window.I18N[key]) || key;
}

function secretaryLocale() {
  return window.SECRETARY_LOCALE || 'es-ES';
}

async function api(url, opts = {}) {
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

document.addEventListener('click', (ev) => {
  document.querySelectorAll('details.user-menu[open]').forEach((d) => {
    if (!d.contains(ev.target)) d.removeAttribute('open');
  });
});
