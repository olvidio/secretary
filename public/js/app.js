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
    && !String(url).startsWith('/api/mensajes')
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

function fechaIsoAEs(iso) {
  const m = String(iso ?? '').trim().match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!m) return String(iso ?? '');
  return `${m[3]}/${m[2]}/${m[1]}`;
}

function fechaEsAIso(texto) {
  const s = String(texto ?? '').trim();
  let m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (m) return `${m[1]}-${m[2]}-${m[3]}`;
  // dd/mm/aaaa o dd/mm/aa (año corto = 20aa); también admite '-' o '.' como separador.
  m = s.match(/^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2}|\d{4})$/);
  if (!m) return s;
  const anio = m[3].length === 2 ? `20${m[3]}` : m[3];
  return `${anio}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`;
}

function fechaIsoValida(iso) {
  const m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!m) return false;
  const y = Number(m[1]);
  const mo = Number(m[2]);
  const d = Number(m[3]);
  const dt = new Date(Date.UTC(y, mo - 1, d));
  return dt.getUTCFullYear() === y && dt.getUTCMonth() === mo - 1 && dt.getUTCDate() === d;
}

/** Muestra dd/mm/aaaa y deja el icono del calendario nativo al lado. value sigue en aaaa-mm-dd. */
function presentarFechaEs(input) {
  if (!(input instanceof HTMLInputElement) || input.dataset.fechaEs === '1' || input.dataset.fechaPicker === '1') return;
  if (input.type !== 'date') return;
  const proto = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
  const actual = proto.get.call(input);
  input.dataset.fechaEs = '1';
  input.type = 'text';
  input.classList.add('fecha-es');
  if (!input.placeholder) input.placeholder = 'dd/mm/aaaa';
  input.autocomplete = 'off';
  input.inputMode = 'numeric';
  input.spellcheck = false;
  if (input.maxLength > 0 && input.maxLength < 10) input.removeAttribute('maxlength');

  const picker = document.createElement('input');
  picker.type = 'date';
  picker.className = 'fecha-picker';
  picker.tabIndex = -1;
  picker.dataset.fechaPicker = '1';
  picker.setAttribute('aria-label', t('calendario'));
  if (input.min) picker.min = input.min;
  if (input.max) picker.max = input.max;

  const campo = document.createElement('span');
  campo.className = 'fecha-campo';
  input.replaceWith(campo);
  campo.append(input, picker);

  function isoVisible() {
    const iso = fechaEsAIso(proto.get.call(input));
    return fechaIsoValida(iso) ? iso : '';
  }
  function syncPicker() {
    picker.value = isoVisible();
  }

  Object.defineProperty(input, 'value', {
    configurable: true,
    get() {
      return fechaEsAIso(proto.get.call(input));
    },
    set(v) {
      proto.set.call(input, v == null || v === '' ? '' : fechaIsoAEs(v));
      syncPicker();
    },
  });
  input.value = actual;
  picker.addEventListener('input', () => {
    input.value = picker.value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  });
  input.addEventListener('blur', () => {
    const crudo = proto.get.call(input).trim();
    if (crudo === '') {
      input.setCustomValidity('');
      syncPicker();
      return;
    }
    const iso = fechaEsAIso(crudo);
    if (!fechaIsoValida(iso)) {
      input.setCustomValidity('Usa el formato dd/mm/aaaa');
      return;
    }
    input.setCustomValidity('');
    proto.set.call(input, fechaIsoAEs(iso));
    syncPicker();
  });
}

function presentarFechasEs(raiz) {
  (raiz || document).querySelectorAll('input[type="date"]').forEach(presentarFechaEs);
}

function formObj(form) {
  const o = {};
  new FormData(form).forEach((v, k) => { o[k] = v; });
  form.querySelectorAll('input[data-fecha-es]').forEach((el) => {
    if (el.name && Object.prototype.hasOwnProperty.call(o, el.name)) {
      o[el.name] = fechaEsAIso(o[el.name]);
    }
  });
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

async function pintarAvisosMensajes() {
  const badges = document.querySelectorAll('.msg-badge');
  if (!badges.length) return;
  const r = await api('/api/mensajes/contador');
  const n = r.ok ? Number(r.pendientes || 0) : 0;
  badges.forEach((el) => {
    el.hidden = n <= 0;
    el.textContent = n > 99 ? '99+' : (n > 0 ? String(n) : '');
  });
}

/*
 * Tablas ordenables: <table class="ordenable">. Clic en una cabecera con texto
 * ordena las filas del tbody (otro clic invierte). Detecta fechas dd/mm/aaaa e
 * importes; si no, orden alfabético con números naturales. Una celda puede fijar
 * su valor con data-orden. Si el JS repinta el tbody, se reaplica el orden.
 * Con filas agrupadas (celdas con colspan) no se ordena, para no romper grupos.
 */
function valorOrdenCelda(td) {
  if (!td) return { tipo: 'vacio', v: '' };
  if (td.dataset.orden !== undefined) {
    const n = Number(td.dataset.orden);
    return Number.isFinite(n) && td.dataset.orden !== '' ? { tipo: 'num', v: n } : { tipo: 'txt', v: td.dataset.orden };
  }
  const txt = td.textContent.trim();
  if (txt === '') return { tipo: 'vacio', v: '' };
  const f = txt.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
  if (f) return { tipo: 'fecha', v: Number(f[3]) * 10000 + Number(f[2]) * 100 + Number(f[1]) };
  if (/^-?[\d.]+(,\d+)?\s*\u20AC?$/.test(txt)) {
    const n = parseImporteEs(txt.replace(/\s*\u20AC$/, ''));
    if (Number.isFinite(n)) return { tipo: 'num', v: n };
  }
  return { tipo: 'txt', v: txt };
}

function compararOrden(a, b) {
  if (a.tipo !== 'txt' && b.tipo !== 'txt') return a.v - b.v;
  return String(a.v).localeCompare(String(b.v), 'es', { numeric: true, sensitivity: 'base' });
}

function aplicarOrdenTabla(table) {
  const estado = table._orden;
  const tb = table.tBodies[0];
  if (!estado || !tb) return;
  const filas = Array.from(tb.rows);
  if (filas.some((tr) => Array.from(tr.cells).some((td) => td.colSpan > 1))) return;
  const col = estado.col;
  const decoradas = filas.map((tr, i) => ({ tr, i, val: valorOrdenCelda(tr.cells[col]) }));
  decoradas.sort((x, y) => {
    const vacioX = x.val.tipo === 'vacio';
    const vacioY = y.val.tipo === 'vacio';
    if (vacioX !== vacioY) return vacioX ? 1 : -1; // vacías siempre al final
    const c = compararOrden(x.val, y.val);
    return (estado.desc ? -c : c) || x.i - y.i;
  });
  if (decoradas.every((d, i) => d.tr === filas[i])) return;
  // Los avisos del MutationObserver llegan después (asíncronos): se desconecta
  // mientras se mueven las filas para no reordenar en bucle.
  table._observadorOrden?.disconnect();
  const frag = document.createDocumentFragment();
  decoradas.forEach((d) => frag.appendChild(d.tr));
  tb.appendChild(frag);
  table._observadorOrden?.observe(tb, { childList: true });
}

function pintarIndicadoresOrden(table) {
  const estado = table._orden;
  Array.from(table.tHead?.rows[0]?.cells || []).forEach((th, i) => {
    const activo = estado && estado.col === i;
    th.setAttribute('aria-sort', activo ? (estado.desc ? 'descending' : 'ascending') : 'none');
    th.classList.toggle('orden-asc', !!activo && !estado.desc);
    th.classList.toggle('orden-desc', !!activo && estado.desc);
  });
}

function prepararTablaOrdenable(table) {
  if (!(table instanceof HTMLTableElement) || table.dataset.ordenableListo === '1') return;
  const cabecera = table.tHead?.rows[0];
  if (!cabecera) return;
  table.dataset.ordenableListo = '1';
  Array.from(cabecera.cells).forEach((th, i) => {
    if (th.textContent.trim() === '') return;
    th.classList.add('ordenable-th');
    th.tabIndex = 0;
    const ordenar = () => {
      const previo = table._orden;
      table._orden = { col: i, desc: previo && previo.col === i ? !previo.desc : false };
      pintarIndicadoresOrden(table);
      aplicarOrdenTabla(table);
    };
    th.addEventListener('click', ordenar);
    th.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter' || ev.key === ' ') {
        ev.preventDefault();
        ordenar();
      }
    });
  });
  const tb = table.tBodies[0];
  if (tb) {
    table._observadorOrden = new MutationObserver(() => {
      if (table._orden) aplicarOrdenTabla(table);
    });
    table._observadorOrden.observe(tb, { childList: true });
  }
}

function prepararTablasOrdenables(raiz) {
  const r = raiz || document;
  if (r instanceof HTMLTableElement && r.classList.contains('ordenable')) prepararTablaOrdenable(r);
  r.querySelectorAll?.('table.ordenable').forEach(prepararTablaOrdenable);
}

function vigilarFechasEs() {
  presentarFechasEs(document);
  prepararTablasOrdenables(document);
  if (!document.body || document.body.dataset.fechasEs === '1') return;
  document.body.dataset.fechasEs = '1';
  new MutationObserver((recs) => {
    recs.forEach((rec) => {
      rec.addedNodes.forEach((nodo) => {
        if (!(nodo instanceof Element)) return;
        if (nodo.matches?.('input[type="date"]')) presentarFechaEs(nodo);
        presentarFechasEs(nodo);
        prepararTablasOrdenables(nodo);
      });
    });
  }).observe(document.body, { childList: true, subtree: true });
}

/**
 * Lo seleccionado, listo para el portapapeles, con el valor de las casillas
 * editables de las tablas (las celdas azules del presupuesto). El navegador, al
 * copiar, se salta el contenido de los <input>: sin esto la hoja pegada en una
 * hoja de cálculo sale con esas celdas vacías. Devuelve null si en la selección
 * no hay ninguna casilla de tabla (se deja copiar al navegador).
 */
function seleccionConCasillas(sel) {
  if (!sel || sel.isCollapsed || !sel.rangeCount) return null;
  const caja = document.createElement('div');
  for (let i = 0; i < sel.rangeCount; i++) {
    const trozo = sel.getRangeAt(i).cloneContents();
    // Una selección dentro de una tabla llega como filas o celdas sueltas: se les devuelve la tabla.
    const primero = trozo.firstElementChild;
    if (primero && /^(TR|TBODY|THEAD|TFOOT)$/.test(primero.tagName)) {
      const tabla = document.createElement('table');
      tabla.appendChild(trozo);
      caja.appendChild(tabla);
    } else if (primero && /^(TD|TH)$/.test(primero.tagName)) {
      const tabla = document.createElement('table');
      tabla.appendChild(document.createElement('tr')).appendChild(trozo);
      caja.appendChild(tabla);
    } else {
      caja.appendChild(trozo);
    }
  }
  const casillas = caja.querySelectorAll('td input, th input, td select, td textarea');
  if (!casillas.length) return null;
  casillas.forEach((c) => {
    if (c.type === 'hidden' || c.type === 'checkbox' || c.type === 'radio' || c.type === 'file') return;
    const texto = c.tagName === 'SELECT' ? (c.selectedOptions[0]?.textContent ?? '') : c.value;
    c.replaceWith(document.createTextNode(texto));
  });
  // Columnas que solo separan (sin contenido): al pegar serían columnas vacías.
  caja.querySelectorAll('td[aria-hidden="true"], th[aria-hidden="true"]').forEach((c) => c.remove());
  caja.querySelectorAll('script, style, button').forEach((c) => c.remove());
  // innerText necesita que el nodo esté en la página para dar tabuladores y saltos de línea de tabla.
  caja.style.cssText = 'position:fixed;left:-99999px;top:0;white-space:pre;';
  document.body.appendChild(caja);
  const texto = caja.innerText;
  caja.removeAttribute('style');
  const html = caja.innerHTML;
  caja.remove();
  return { texto, html };
}

document.addEventListener('copy', (ev) => {
  // Texto seleccionado dentro de una casilla: copia normal del navegador.
  const activo = document.activeElement;
  if (activo && /^(INPUT|TEXTAREA)$/.test(activo.tagName) && activo.selectionStart !== activo.selectionEnd) return;
  const datos = seleccionConCasillas(window.getSelection());
  if (!datos || !ev.clipboardData) return;
  ev.clipboardData.setData('text/plain', datos.texto);
  ev.clipboardData.setData('text/html', datos.html);
  ev.preventDefault();
});

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    vigilarFechasEs();
    pintarAvisosMensajes();
  });
} else {
  vigilarFechasEs();
  pintarAvisosMensajes();
}
