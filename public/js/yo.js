(function () {
  const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  const COLORES = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6',
    '#3b82f6', '#8b5cf6', '#ec4899', '#64748b', '#0f766e', '#b45309', '#7c3aed'];

  const nav = document.body.getAttribute('data-nav') || '';
  const state = {
    anio: new Date().getFullYear(),
    mes: new Date().getMonth() + 1,
    fechaCierre: '',
    categorias: [],
    conceptosGenerales: [],
    tesoreria: [],
    sentido: 'gasto',
    generalesActivo: false,
  };

  function qs(sel) { return document.querySelector(sel); }
  function fmtCents(cents) {
    const n = (Math.abs(cents) / 100).toFixed(2).replace('.', ',');
    return (cents < 0 ? '-' : '') + n;
  }
  function colorDe(codigo) {
    let h = 0;
    for (let i = 0; i < String(codigo).length; i++) h = (h * 31 + String(codigo).charCodeAt(i)) >>> 0;
    return COLORES[h % COLORES.length];
  }
  function paramsMes() {
    return 'anio=' + state.anio + '&mes=' + state.mes;
  }
  function fmtFecha(iso) {
    if (!iso) return '';
    const p = String(iso).split('-');
    if (p.length !== 3) return iso;
    return p[2] + '/' + p[1] + '/' + p[0];
  }
  function tituloMes() {
    let t = MESES[state.mes - 1] + ' ' + state.anio;
    if (state.fechaCierre) t += ' · cierra ' + fmtFecha(state.fechaCierre);
    return t;
  }
  function actualizarTituloMes(fechaCierre) {
    if (fechaCierre) state.fechaCierre = fechaCierre;
    const t = qs('#yo-mes-titulo');
    if (t) t.textContent = tituloMes();
  }
  function hoyIso() {
    const d = new Date();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return d.getFullYear() + '-' + m + '-' + day;
  }
  function moverMes(delta) {
    state.mes += delta;
    if (state.mes < 1) { state.mes = 12; state.anio -= 1; }
    if (state.mes > 12) { state.mes = 1; state.anio += 1; }
  }
  function bindMes(onChange) {
    const t = qs('#yo-mes-titulo');
    if (t) t.textContent = tituloMes();
    const ant = qs('#yo-mes-ant');
    const sig = qs('#yo-mes-sig');
    const rec = typeof onChange === 'function' ? onChange : recargar;
    if (ant) ant.onclick = () => { moverMes(-1); rec(); };
    if (sig) sig.onclick = () => { moverMes(1); rec(); };
  }

  function conic(segmentos) {
    const total = segmentos.reduce((a, s) => a + s.cents, 0);
    if (total <= 0) return 'conic-gradient(#e2e8f0 0 100%)';
    let acc = 0;
    const parts = segmentos.map((s) => {
      const from = (acc / total) * 360;
      acc += s.cents;
      const to = (acc / total) * 360;
      return s.color + ' ' + from.toFixed(2) + 'deg ' + to.toFixed(2) + 'deg';
    });
    return 'conic-gradient(' + parts.join(', ') + ')';
  }

  async function cargarCategorias() {
    const r = await api('/api/yo/categorias');
    state.categorias = r.categorias || [];
    state.tesoreria = r.tesoreria || [];
    return r;
  }

  async function cargarConceptosGenerales() {
    if (state.conceptosGenerales.length) return;
    const r = await api('/api/yo/conceptos-generales');
    if (r.ok === false) return;
    state.conceptosGenerales = r.conceptos || [];
    const sel = qs('#yo-form [name="concepto_generales"]');
    if (!sel) return;
    sel.innerHTML = '<option value="">— Elija —</option>'
      + state.conceptosGenerales.map((c) =>
        '<option value="' + esc(c.codigo) + '">' + esc(c.codigo + ' ' + (c.nombre || '')) + '</option>'
      ).join('');
  }

  function nombreConceptoGenerales(codigo) {
    const c = state.conceptosGenerales.find((x) => x.codigo === codigo);
    return c ? (c.codigo + ' ' + (c.nombre || '')) : codigo;
  }

  function fijarCategoriaGenerales() {
    const cats = catsDelSentido('gasto');
    const vivienda = cats.find((c) => c.codigo_maestro === '21' || c.codigo === '21');
    const cat = vivienda || cats.find((c) => !c.pendiente && !c.otra);
    const cuenta = qs('#yo-form [name="cuenta_id"]');
    if (cat && cuenta) cuenta.value = String(cat.id);
  }

  function resaltarCategoriaSeleccionada() {
    const grid = qs('#yo-cats-grid');
    const cuenta = qs('#yo-form [name="cuenta_id"]');
    if (!grid || !cuenta) return;
    grid.querySelectorAll('button').forEach((b) => {
      b.classList.toggle('on', b.getAttribute('data-id') === cuenta.value);
    });
  }

  function actualizarPillGenerales() {
    const form = qs('#yo-form');
    const pill = qs('#yo-pill-generales');
    const grid = qs('#yo-cats-grid');
    const hidden = qs('#yo-form [name="gasto_generales"]');
    const cuenta = qs('#yo-form [name="cuenta_id"]');
    const esGasto = state.sentido === 'gasto';
    const enGenerales = esGasto && state.generalesActivo;
    if (form) form.classList.toggle('yo-es-generales', enGenerales);
    if (pill) {
      pill.hidden = !esGasto;
      pill.classList.toggle('on', state.generalesActivo);
      pill.textContent = state.generalesActivo ? 'Generales' : 'Personal';
      pill.setAttribute('aria-pressed', state.generalesActivo ? 'true' : 'false');
    }
    if (cuenta) cuenta.required = esGasto && !enGenerales;
    if (hidden) hidden.value = state.generalesActivo ? '1' : '0';
    if (enGenerales) {
      fijarCategoriaGenerales();
    } else if (grid && esGasto) {
      resaltarCategoriaSeleccionada();
    }
  }

  function resetGenerales() {
    state.generalesActivo = false;
    const sel = qs('#yo-form [name="concepto_generales"]');
    if (sel) sel.value = '';
    actualizarPillGenerales();
  }

  async function pintarResumen() {
    const r = await api('/api/yo/resumen?' + paramsMes());
    actualizarTituloMes(r.fecha_cierre);
    const saldo = qs('#yo-saldo');
    if (saldo) saldo.textContent = r.saldo?.importe_es || '0,00';
    const caja = qs('#yo-caja');
    if (caja) caja.textContent = r.caja?.importe_es || '0,00';
    const banco = qs('#yo-banco');
    if (banco) banco.textContent = r.banco?.importe_es || '0,00';
    const ing = qs('#yo-ingresos');
    if (ing) ing.textContent = r.ingresos?.importe_es || '0,00';
    const gas = qs('#yo-gastos');
    if (gas) gas.textContent = r.gastos?.importe_es || '0,00';
    const segs = (r.gastos_por_categoria || []).map((c) => ({
      ...c,
      color: colorDe(c.codigo),
    })).filter((c) => c.cents > 0);
    const chart = qs('#yo-chart');
    if (chart) chart.style.background = conic(segs);
    const ley = qs('#yo-leyenda');
    if (ley) {
      ley.innerHTML = segs.map((c) =>
        '<li><span class="yo-dot" style="background:' + c.color + '"></span>'
        + esc(c.nombre) + '<span class="num">' + fmtCents(c.cents) + '</span></li>'
      ).join('');
    }
  }

  function parseCantidadInput(s) {
    const n = Number(String(s ?? '').trim().replace(/\s/g, '').replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
  }

  function fmtCantidadInput(n) {
    if (!Number.isFinite(n) || n <= 0) return '';
    return n.toFixed(2).replace('.', ',');
  }

  function menuMovimientoHtml(m) {
    const puedeDesdoblar = m.sentido !== 'traspaso' && !m.par_id;
    return '<details class="yo-mov-menu">'
      + '<summary aria-label="Opciones">⋮</summary>'
      + '<ul>'
      + '<li><button type="button" data-edit>Editar</button></li>'
      + '<li><button type="button" data-split' + (puedeDesdoblar ? '' : ' disabled')
      + ' title="' + (puedeDesdoblar ? 'Dividir en dos categorías' : 'No aplicable') + '">Desdoblar</button></li>'
      + '<li><button type="button" class="peligro" data-del>Borrar</button></li>'
      + '</ul></details>';
  }

  function cerrarMenusMovimiento() {
    document.querySelectorAll('.yo-mov-menu[open]').forEach((el) => { el.open = false; });
  }

  function enlazarMenuMovimiento(li, m) {
    const menu = li.querySelector('.yo-mov-menu');
    if (menu) {
      menu.addEventListener('toggle', () => {
        if (!menu.open) return;
        document.querySelectorAll('.yo-mov-menu[open]').forEach((el) => {
          if (el !== menu) el.open = false;
        });
      });
    }
    const edit = li.querySelector('[data-edit]');
    if (edit) edit.onclick = () => { cerrarMenusMovimiento(); abrirModalEdicion(m); };
    const split = li.querySelector('[data-split]');
    if (split && !split.disabled) {
      split.onclick = () => { cerrarMenusMovimiento(); abrirModalDesdoblar(m); };
    }
    const del = li.querySelector('[data-del]');
    if (del) {
      del.onclick = async () => {
        cerrarMenusMovimiento();
        if (!confirm('¿Borrar este movimiento?')) return;
        const borrado = await api('/api/yo/movimientos/' + m.id, { method: 'DELETE' });
        if (!borrado.ok) { alert(borrado.error || 'No se pudo borrar'); return; }
        recargar();
      };
    }
  }

  async function pintarLista() {
    const r = await api('/api/yo/movimientos?' + paramsMes());
    const ul = qs('#yo-lista');
    const vacia = qs('#yo-lista-vacia');
    if (!ul) return;
    if (!r.ok) return alert(r.error || 'Error');
    actualizarTituloMes(r.fecha_cierre);
    const movs = r.movimientos || [];
    if (vacia) vacia.hidden = movs.length > 0;
    ul.innerHTML = '';
    movs.slice().reverse().forEach((m) => {
      const li = document.createElement('li');
      const letra = (m.categoria || m.sentido || '?').slice(0, 1).toUpperCase();
      const cls = m.sentido === 'ingreso' ? 'ing' : (m.sentido === 'gasto' ? 'gas' : '');
      const signo = m.sentido === 'ingreso' ? '+' : (m.sentido === 'gasto' ? '−' : '');
      const fechaTxt = m.fecha_operacion && m.fecha_operacion !== m.fecha
        ? fmtFecha(m.fecha_operacion) + ' → ' + fmtFecha(m.fecha)
        : fmtFecha(m.fecha);
      let tesTxt = '';
      if (m.sentido === 'traspaso' && m.tesoreria_origen && m.tesoreria_destino) {
        tesTxt = ' · ' + esc(m.tesoreria_origen) + ' → ' + esc(m.tesoreria_destino);
      } else if (m.tesoreria) {
        tesTxt = ' · ' + esc(m.tesoreria);
      }
      const genTxt = m.gasto_generales && m.concepto_generales
        ? ' · <span class="yo-lista-generales">G ' + esc(m.concepto_generales) + '</span>' : '';
      li.innerHTML = '<span class="yo-chip" style="background:' + colorDe(m.categoria_codigo || m.sentido) + '">' + esc(letra)
        + '</span><span class="meta"><strong>' + esc(m.categoria || (m.sentido === 'traspaso' ? 'Traspaso' : m.sentido))
        + genTxt + '</strong><small>' + esc(fechaTxt) + (m.nota ? ' · ' + esc(m.nota) : '')
        + tesTxt + '</small></span>'
        + '<span class="imp ' + cls + '">' + signo + esc(m.cantidad_es) + '</span>'
        + menuMovimientoHtml(m);
      enlazarMenuMovimiento(li, m);
      ul.appendChild(li);
    });
  }

  function catsDelSentido(sentido) {
    const tipo = sentido === 'ingreso' ? 'ingreso' : 'gasto';
    return state.categorias.filter((c) => c.tipo === tipo && !c.pendiente && !c.otra);
  }

  function acortarEtiqueta(texto, max) {
    const t = String(texto || '').trim();
    if (t.length <= max) return t;
    return t.slice(0, Math.max(1, max - 3)).trimEnd() + '...';
  }

  function etiquetaCategoria(nombre, sugerida) {
    const base = String(nombre || '').trim();
    const max = sugerida ? 14 : 18;
    return acortarEtiqueta(base, max) + (sugerida ? ' · sugerido' : '');
  }

  function opcionesCategoria(sentido, seleccion) {
    const cats = catsDelSentido(sentido);
    const sel = seleccion ? String(seleccion) : '';
    let opts = cats.map((c) => {
      const on = sel !== '' && String(c.id) === sel;
      const nombre = c.nombre || c.codigo;
      return '<option value="' + c.id + '"' + (on ? ' selected' : '')
        + ' title="' + esc(nombre) + '">'
        + esc(etiquetaCategoria(nombre, on)) + '</option>';
    }).join('');
    const tipo = sentido === 'ingreso' ? 'ingreso' : 'gasto';
    const otra = (state.categorias || []).find((c) => c.tipo === tipo && c.otra);
    if (otra) {
      const on = sel !== '' && String(otra.id) === sel;
      const nombre = otra.nombre || 'Otra contabilidad';
      opts += '<option disabled>────────</option>';
      opts += '<option value="' + otra.id + '"' + (on ? ' selected' : '')
        + ' title="' + esc(nombre) + '">'
        + esc(etiquetaCategoria(nombre, on)) + '</option>';
    }
    return opts;
  }

  function pintarGridCats(sentido) {
    const grid = qs('#yo-cats-grid');
    if (!grid) return;
    const cats = catsDelSentido(sentido);
    grid.innerHTML = cats.map((c) =>
      '<button type="button" data-id="' + c.id + '" style="border-top: 3px solid ' + colorDe(c.codigo) + '">'
      + esc(c.nombre || c.codigo) + '</button>'
    ).join('');
    grid.querySelectorAll('button').forEach((b) => {
      b.onclick = () => {
        grid.querySelectorAll('button').forEach((x) => x.classList.remove('on'));
        b.classList.add('on');
        qs('#yo-form [name="cuenta_id"]').value = b.getAttribute('data-id');
      };
    });
    const first = grid.querySelector('button');
    if (first && !(sentido === 'gasto' && state.generalesActivo)) first.click();
  }

  function prepararModalBase() {
    const form = qs('#yo-form');
    if (!form) return null;
    qs('#yo-form-err').hidden = true;
    const impWrap = qs('#yo-imputacion-wrap');
    if (impWrap) impWrap.open = false;
    form.querySelectorAll('input, select, button[type="submit"]').forEach((el) => {
      el.disabled = false;
      if (el.readOnly) el.readOnly = false;
    });
    return form;
  }

  /** @param {'ingreso'|'gasto'|'traspaso'} modo */
  function configurarFormulario(modo) {
    const form = qs('#yo-form');
    if (!form) return;
    form.classList.remove('yo-es-traspaso');
    if (modo === 'traspaso') form.classList.add('yo-es-traspaso');
    const impWrap = qs('#yo-imputacion-wrap');
    if (impWrap) impWrap.hidden = modo === 'traspaso';
    const cuenta = form.querySelector('[name="cuenta_id"]');
    const esTraspaso = modo === 'traspaso';
    if (cuenta) cuenta.required = !esTraspaso;
    if (esTraspaso && cuenta) cuenta.value = '';
    actualizarPillGenerales();
  }

  function abrirModal(sentido) {
    const modal = qs('#yo-modal');
    const form = prepararModalBase();
    if (!modal || !form) return;
    state.sentido = sentido;
    form.reset();
    form.asiento_id.value = '';
    form.sentido.value = sentido;
    form.fecha.value = hoyIso();
    form.fecha_imputacion.value = '';
    resetGenerales();
    const titulo = qs('#yo-form-titulo');
    if (sentido === 'traspaso') {
      if (titulo) titulo.textContent = 'Traspaso';
      configurarFormulario('traspaso');
    } else {
      if (titulo) titulo.textContent = sentido === 'ingreso' ? 'Ingreso' : 'Gasto';
      configurarFormulario(sentido);
      pintarGridCats(sentido);
    }
    modal.hidden = false;
  }

  function abrirModalEdicion(m) {
    const modal = qs('#yo-modal');
    const form = prepararModalBase();
    if (!modal || !form) return;
    state.sentido = m.sentido;
    form.reset();
    form.asiento_id.value = String(m.id);
    form.sentido.value = m.sentido;
    form.cantidad.value = m.cantidad || m.cantidad_es || '';
    form.nota.value = m.nota || '';
    form.fecha.value = m.fecha_operacion || m.fecha || hoyIso();
    form.fecha_imputacion.value = (m.fecha_operacion && m.fecha && m.fecha !== m.fecha_operacion) ? m.fecha : '';
    state.generalesActivo = !!m.gasto_generales;
    const selG = form.concepto_generales;
    if (selG) selG.value = m.concepto_generales || '';
    const titulo = qs('#yo-form-titulo');
    const grid = qs('#yo-cats-grid');
    if (m.sentido === 'traspaso') {
      if (titulo) titulo.textContent = 'Editar traspaso';
      configurarFormulario('traspaso');
      if (form.tesoreria_origen) {
        form.tesoreria_origen.value = String(m.tesoreria_origen || 'CAJA').toUpperCase();
      }
      if (form.tesoreria_destino) {
        form.tesoreria_destino.value = String(m.tesoreria_destino || 'BANCO').toUpperCase();
      }
    } else {
      form.cuenta_id.value = m.categoria_id || '';
      const tes = String(m.tesoreria || 'CAJA').toUpperCase();
      form.querySelectorAll('[name="tesoreria"]').forEach((el) => {
        el.checked = el.value === tes;
      });
      if (titulo) titulo.textContent = 'Editar movimiento';
      configurarFormulario(m.sentido);
      pintarGridCats(m.sentido);
      if (m.categoria_id && grid && !state.generalesActivo) {
        form.cuenta_id.value = String(m.categoria_id);
        grid.querySelectorAll('button').forEach((b) => {
          b.classList.toggle('on', b.getAttribute('data-id') === String(m.categoria_id));
        });
      }
      actualizarPillGenerales();
    }
    modal.hidden = false;
  }

  function actualizarRestoDesdoblar() {
    const form = qs('#yo-desdoblar-form');
    const resto = qs('#yo-desdoblar-resto');
    if (!form || !resto || !state.desdoblar) return;
    const total = state.desdoblar.total;
    const p1 = parseCantidadInput(form.cantidad1.value);
    const p2 = parseCantidadInput(form.cantidad2.value);
    const diff = Math.round((total - p1 - p2) * 100) / 100;
    if (Math.abs(diff) < 0.005) {
      resto.textContent = 'Cuadra con el total (' + fmtCantidadInput(total) + ')';
    } else {
      resto.textContent = 'Faltan ' + fmtCantidadInput(Math.max(0, total - p1 - p2))
        + ' · sobran ' + fmtCantidadInput(Math.max(0, p1 + p2 - total));
    }
  }

  function abrirModalDesdoblar(m) {
    const modal = qs('#yo-modal-desdoblar');
    const form = qs('#yo-desdoblar-form');
    if (!modal || !form) return;
    if (m.sentido === 'traspaso' || m.par_id) return;
    form.reset();
    const total = parseCantidadInput(m.cantidad || m.cantidad_es);
    state.desdoblar = { id: m.id, total, sentido: m.sentido };
    form.asiento_id.value = String(m.id);
    const sel1 = form.cuenta_id1;
    const sel2 = form.cuenta_id2;
    if (sel1) sel1.innerHTML = opcionesCategoria(m.sentido, m.categoria_id);
    if (sel2) sel2.innerHTML = opcionesCategoria(m.sentido, '');
    form.nota1.value = m.nota || '';
    form.nota2.value = '';
    const resumen = qs('#yo-desdoblar-resumen');
    if (resumen) {
      resumen.textContent = 'Total: ' + (m.cantidad_es || m.cantidad || '')
        + (m.nota ? ' · ' + m.nota : (m.categoria ? ' · ' + m.categoria : ''));
    }
    const err = qs('#yo-desdoblar-err');
    if (err) err.hidden = true;
    actualizarRestoDesdoblar();
    modal.hidden = false;
    form.cantidad1.focus();
  }

  function cerrarModalDesdoblar() {
    const modal = qs('#yo-modal-desdoblar');
    if (modal) modal.hidden = true;
    state.desdoblar = null;
  }

  function cerrarModal() {
    const modal = qs('#yo-modal');
    if (modal) modal.hidden = true;
  }

  function bindDesdoblar() {
    const modal = qs('#yo-modal-desdoblar');
    const form = qs('#yo-desdoblar-form');
    const cancel = qs('#yo-desdoblar-cancelar');
    if (!form) return;
    if (cancel) cancel.onclick = cerrarModalDesdoblar;
    if (modal) modal.addEventListener('click', (ev) => { if (ev.target === modal) cerrarModalDesdoblar(); });
    const syncSegunda = () => {
      if (!state.desdoblar) return;
      const p1 = parseCantidadInput(form.cantidad1.value);
      if (p1 > 0 && p1 < state.desdoblar.total) {
        form.cantidad2.value = fmtCantidadInput(state.desdoblar.total - p1);
      }
      actualizarRestoDesdoblar();
    };
    form.cantidad1.addEventListener('input', syncSegunda);
    form.cantidad2.addEventListener('input', actualizarRestoDesdoblar);
    form.onsubmit = async (ev) => {
      ev.preventDefault();
      const err = qs('#yo-desdoblar-err');
      if (err) err.hidden = true;
      if (!state.desdoblar) return;
      const p1 = parseCantidadInput(form.cantidad1.value);
      const p2 = parseCantidadInput(form.cantidad2.value);
      if (p1 <= 0 || p2 <= 0) {
        if (err) { err.textContent = 'Cada parte debe ser mayor que cero'; err.hidden = false; }
        return;
      }
      if (Math.abs(p1 + p2 - state.desdoblar.total) > 0.005) {
        if (err) { err.textContent = 'Las dos partes deben sumar el total'; err.hidden = false; }
        return;
      }
      const id = form.asiento_id.value;
      const r = await api('/api/yo/movimientos/' + id + '/desdoblar', {
        method: 'POST',
        body: {
          partes: [
            { cantidad: form.cantidad1.value, cuenta_id: Number(form.cuenta_id1.value), nota: form.nota1.value },
            { cantidad: form.cantidad2.value, cuenta_id: Number(form.cuenta_id2.value), nota: form.nota2.value },
          ],
        },
      });
      if (!r.ok) {
        if (err) { err.textContent = r.error || 'No se pudo desdoblar'; err.hidden = false; }
        return;
      }
      cerrarModalDesdoblar();
      recargar();
    };
  }

  function bindAlta() {
    const ing = qs('#yo-btn-ingreso');
    const gas = qs('#yo-btn-gasto');
    const tra = qs('#yo-btn-traspaso');
    const cancel = qs('#yo-form-cancelar');
    const modal = qs('#yo-modal');
    const form = qs('#yo-form');
    if (ing) ing.onclick = () => abrirModal('ingreso');
    if (gas) gas.onclick = () => abrirModal('gasto');
    if (tra) tra.onclick = () => abrirModal('traspaso');
    if (cancel) cancel.onclick = cerrarModal;
    if (modal) modal.addEventListener('click', (ev) => { if (ev.target === modal) cerrarModal(); });
    const pill = qs('#yo-pill-generales');
    if (pill) {
      pill.onclick = () => {
        if (state.sentido !== 'gasto') return;
        state.generalesActivo = !state.generalesActivo;
        actualizarPillGenerales();
      };
    }
    if (!form) return;
    form.onsubmit = async (ev) => {
      ev.preventDefault();
      const err = qs('#yo-form-err');
      err.hidden = true;
      const body = formObj(form);
      body.gasto_generales = state.generalesActivo ? '1' : '0';
      if (!state.generalesActivo) {
        delete body.concepto_generales;
      } else if (!body.concepto_generales) {
        err.textContent = 'Elija el concepto de generales (p. ej. Gas)';
        err.hidden = false;
        return;
      }
      delete body.asiento_id;
      const id = form.asiento_id.value;
      const r = id
        ? await api('/api/yo/movimientos/' + id, { method: 'PUT', body })
        : await api('/api/yo/movimientos', { method: 'POST', body });
      if (!r.ok) {
        err.textContent = r.error || 'No se pudo guardar';
        err.hidden = false;
        return;
      }
      cerrarModal();
      recargar();
    };
  }

  async function pintarCategorias() {
    const r = await cargarCategorias();
    const sel = qs('#yo-cat-form [name="codigo_maestro"]');
    const list = qs('#yo-cat-list');
    const visibles = (r.categorias || []).filter((c) => !c.pendiente && !c.otra);
    const maestros = visibles.filter((c) => c.codigo === c.codigo_maestro);
    if (sel) {
      sel.innerHTML = maestros.map((c) =>
        '<option value="' + esc(c.codigo) + '">' + esc(c.codigo + ' · ' + c.nombre) + '</option>'
      ).join('');
    }
    if (list) {
      list.innerHTML = visibles.map((c) =>
        '<li><span>' + esc(c.nombre) + ' <small>' + esc(c.codigo) + ' · ' + esc(c.tipo) + '</small></span></li>'
      ).join('');
    }
    const form = qs('#yo-cat-form');
    if (!form) return;
    form.onsubmit = async (ev) => {
      ev.preventDefault();
      const msg = qs('#yo-cat-msg');
      const err = qs('#yo-cat-err');
      msg.hidden = true;
      err.hidden = true;
      const r2 = await api('/api/yo/categorias', { method: 'POST', body: formObj(form) });
      if (!r2.ok) {
        err.textContent = r2.error || 'No se pudo crear';
        err.hidden = false;
        return;
      }
      msg.textContent = 'Subcuenta creada';
      msg.hidden = false;
      form.reset();
      pintarCategorias();
    };
  }

  async function pintarCierre() {
    const r = await api('/api/yo/cierre?' + paramsMes());
    if (!r.ok) return alert(r.error || 'Error');
    actualizarTituloMes(r.fecha_cierre);
    const dia = qs('#form-cierre-defecto [name=dia_cierre]');
    const habil = qs('#form-cierre-defecto [name=dia_habil]');
    if (dia) dia.value = r.dia_cierre ?? '';
    if (habil) habil.checked = !!r.dia_habil;
    const calc = qs('#cierre-calculado');
    if (calc) {
      let txt = 'Periodo: ' + fmtFecha(r.desde) + ' – ' + fmtFecha(r.hasta);
      txt += r.es_personalizado ? ' (fecha concreta)' : ' (regla por defecto)';
      calc.textContent = txt;
    }
    const fMes = qs('#form-cierre-mes [name=fecha_cierre]');
    if (fMes) fMes.value = r.fecha_mes || r.fecha_cierre || '';
    const btnBorrar = qs('#btn-cierre-borrar');
    if (btnBorrar) btnBorrar.hidden = !r.es_personalizado;
  }

  function bindCierreForms() {
    const formDef = qs('#form-cierre-defecto');
    if (formDef && !formDef.dataset.bound) {
      formDef.dataset.bound = '1';
      formDef.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const body = formObj(ev.target);
        body.dia_habil = !!body.dia_habil;
        if (body.dia_cierre === '') body.dia_cierre = null;
        const s = await api('/api/yo/cierre/defecto', { method: 'POST', body });
        const msg = qs('#msg-defecto');
        if (msg) msg.hidden = !s.ok;
        if (!s.ok) return alert(s.error);
        await pintarCierre();
      });
    }
    const formMes = qs('#form-cierre-mes');
    if (formMes && !formMes.dataset.bound) {
      formMes.dataset.bound = '1';
      formMes.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const body = formObj(ev.target);
        body.anio = state.anio;
        body.mes = state.mes;
        const s = await api('/api/yo/cierre/mes', { method: 'POST', body });
        const msg = qs('#msg-mes');
        if (msg) msg.hidden = !s.ok;
        if (!s.ok) return alert(s.error);
        await pintarCierre();
      });
    }
    const btnBorrar = qs('#btn-cierre-borrar');
    if (btnBorrar && !btnBorrar.dataset.bound) {
      btnBorrar.dataset.bound = '1';
      btnBorrar.addEventListener('click', async () => {
        if (!confirm('¿Quitar la fecha concreta y volver a la regla por defecto?')) return;
        const s = await api('/api/yo/cierre/mes/borrar', {
          method: 'POST',
          body: { anio: state.anio, mes: state.mes },
        });
        if (!s.ok) return alert(s.error);
        await pintarCierre();
      });
    }
  }

  async function recargar() {
    bindMes();
    if (nav === 'yo') {
      await cargarCategorias();
      await pintarResumen();
    }
    if (nav === 'yo-movimientos') {
      await cargarCategorias();
      await pintarLista();
    }
    if (nav === 'yo-remesas') {
      await pintarRemesas();
    }
    if (nav === 'yo-cierre') {
      await pintarCierre();
    }
  }

  async function pintarRemesas() {
    const msg = qs('#yo-remesa-msg');
    const err = qs('#yo-remesa-err');
    if (msg) msg.hidden = true;
    if (err) err.hidden = true;
    const r = await api('/api/yo/remesas?' + paramsMes());
    actualizarTituloMes(r.fecha_cierre);
    const estado = qs('#yo-remesa-estado');
    const ul = qs('#yo-remesa-lineas');
    const vacia = qs('#yo-remesa-vacia');
    const hist = qs('#yo-remesa-hist');
    if (!r.ok) {
      if (err) { err.textContent = r.error || 'No se pudo cargar'; err.hidden = false; }
      return;
    }
    if (estado) {
      const env = r.enviada ? ('Enviada v' + r.enviada.version) : 'Sin envío pendiente';
      const ace = r.aceptada ? ('; aceptada v' + r.aceptada.version) : '';
      estado.textContent = env + ace + (r.puede_enviar ? '' : (' · ' + (r.motivo || '')));
    }
    const lineas = r.lineas || [];
    if (vacia) vacia.hidden = lineas.length > 0;
    if (ul) {
      ul.innerHTML = lineas.map((l) => {
        let gen = '';
        (l.detalle || []).forEach((d) => {
          (d.generales || []).forEach((g) => {
            gen += ' · G' + esc(g.concepto) + ' ' + esc(g.importe_es || '');
          });
        });
        return '<li><span class="meta"><strong>' + esc(l.codigo_maestro + ' · ' + (l.nombre || ''))
          + '</strong>' + (gen ? '<small>' + gen + '</small>' : '')
          + '</span><span class="imp">' + esc(l.importe_es) + '</span></li>';
      }).join('');
    }
    if (hist) {
      hist.innerHTML = (r.historial || []).map((h) =>
        '<li><span class="meta"><strong>v' + esc(String(h.version)) + ' · ' + esc(h.estado)
        + '</strong><small>' + esc(h.total_es) + '</small></span></li>'
      ).join('');
    }
    const btn = qs('#yo-remesa-enviar');
    if (btn) {
      btn.disabled = !r.puede_enviar;
      btn.onclick = async () => {
        if (!confirm('¿Enviar este mes al centro?')) return;
        if (err) err.hidden = true;
        if (msg) msg.hidden = true;
        const nota = (qs('#yo-remesa-nota') || {}).value || '';
        const envio = await api('/api/yo/remesas', {
          method: 'POST',
          body: { anio: state.anio, mes: state.mes, nota },
        });
        if (!envio.ok) {
          if (err) { err.textContent = envio.error || 'No se pudo enviar'; err.hidden = false; }
          return;
        }
        if (msg) { msg.textContent = 'Remesa enviada (v' + envio.remesa.version + ')'; msg.hidden = false; }
        pintarRemesas();
      };
    }
    const sols = await api('/api/yo/remesas/solicitudes');
    const ulS = qs('#yo-remesa-sols');
    const vacS = qs('#yo-remesa-sols-vacia');
    const lista = sols.solicitudes || [];
    if (vacS) vacS.hidden = lista.length > 0;
    if (ulS) {
      ulS.innerHTML = '';
      lista.forEach((s) => {
        const li = document.createElement('li');
        li.innerHTML = '<span class="meta"><strong>' + esc(s.codigo_maestro)
          + '</strong><small>' + esc(String(s.mes) + '/' + s.anio + ' v' + s.version) + '</small></span>'
          + '<button type="button" class="ok-btn" data-ok="1">Autorizar</button>'
          + '<button type="button" class="del" data-ok="0">Denegar</button>';
        li.querySelectorAll('button').forEach((b) => {
          b.onclick = async () => {
            const ok = b.getAttribute('data-ok') === '1';
            const r2 = await api('/api/yo/remesas/solicitudes/' + s.id, {
              method: 'POST',
              body: { estado: ok ? 'autorizada' : 'denegada' },
            });
            if (!r2.ok) { alert(r2.error || 'No se pudo responder'); return; }
            pintarRemesas();
          };
        });
        ulS.appendChild(li);
      });
    }
  }

  async function pintarBanco() {
    const sel = qs('#yo-banco-sel');
    const form = qs('#yo-banco-form');
    if (!sel || !form) return;
    const b = await api('/api/yo/banco/bancos');
    if (!b.ok) return alert(b.error || 'Error');
    const bancos = b.bancos || [];
    if (bancos.length) {
      const actual = sel.value;
      sel.innerHTML = bancos.map((x) =>
        '<option value="' + esc(x.id) + '">' + esc(x.nombre) + '</option>'
      ).join('');
      if (actual) sel.value = actual;
    }
    const inputFichero = qs('#yo-banco-fichero');
    function actualizarAcceptBanco() {
      if (!inputFichero) return;
      inputFichero.accept = sel.value === 'caixabank'
        ? '.csv,.xls,.xlsx,text/csv'
        : '.csv,text/csv';
    }
    sel.onchange = actualizarAcceptBanco;
    actualizarAcceptBanco();
    await cargarCategorias();
    await pintarListasBanco();
    form.onsubmit = async (ev) => {
      ev.preventDefault();
      const btn = qs('#yo-banco-enviar');
      if (btn) btn.disabled = true;
      const fd = new FormData(form);
      const r = await api('/api/yo/banco/csv', { method: 'POST', body: fd });
      if (btn) btn.disabled = false;
      const err = qs('#yo-banco-err');
      const msg = qs('#yo-banco-msg');
      if (err) err.hidden = true;
      if (msg) msg.hidden = true;
      if (!r.ok) {
        if (err) {
          err.hidden = false;
          err.textContent = r.error || 'Error';
        } else {
          alert(r.error || 'Error');
        }
        return;
      }
      if (msg) {
        msg.hidden = false;
        msg.textContent = 'Nuevos: ' + r.nuevos + '. Ya estaban: ' + r.repetidos
          + (r.omitidos ? '. Omitidos: ' + r.omitidos : '') + '.';
      }
      const bancoId = sel.value;
      form.reset();
      sel.value = bancoId;
      await pintarListasBanco();
    };
  }

  async function pintarListasBanco() {
    const r = await api('/api/yo/banco/pendientes');
    if (!r.ok) return alert(r.error || 'Error');
    pintarFilasBanco(qs('#yo-banco-pend'), qs('#yo-banco-vacio'), r.pendientes || []);
    pintarFilasBanco(qs('#yo-banco-otra'), qs('#yo-banco-otra-vacio'), r.otras || []);
  }

  function pintarFilasBanco(ul, vacio, rows) {
    if (!ul) return;
    if (vacio) vacio.hidden = rows.length > 0;
    ul.innerHTML = '';
    rows.forEach((p) => {
      const li = document.createElement('li');
      const opts = opcionesCategoria(p.sentido, p.sugerida_id);
      const imp = String(p.importe || '').replace('.', ',');
      const texto = String(p.nota || p.concepto || '');
      li.innerHTML = '<span><strong>' + esc(fmtFecha(p.fecha)) + ' · ' + esc(imp) + '</strong></span>'
        + '<div class="yo-banco-pend-acc"><div class="yo-banco-pend-row"><select>' + opts + '</select>'
        + '<button type="button" class="yo-banco-asignar">Asignar</button></div>'
        + '<div class="yo-banco-obs-wrap">'
        + '<input type="text" class="yo-banco-obs" placeholder="Observaciones" maxlength="250" autocomplete="off" value="' + esc(texto) + '">'
        + '<button type="button" class="yo-banco-obs-clear" hidden aria-label="Borrar observaciones">×</button>'
        + '</div></div>';
      const btn = li.querySelector('.yo-banco-asignar');
      const choose = li.querySelector('select');
      const obs = li.querySelector('.yo-banco-obs');
      const clear = li.querySelector('.yo-banco-obs-clear');
      const syncClear = () => { if (clear) clear.hidden = !(obs && obs.value); };
      if (clear && obs) {
        clear.onclick = () => { obs.value = ''; syncClear(); obs.focus(); };
        obs.addEventListener('input', syncClear);
        syncClear();
      }
      if (btn && choose) {
        btn.onclick = async () => {
          const s = await api('/api/yo/banco/categorizar', {
            method: 'POST',
            body: {
              asiento_id: p.asiento_id,
              cuenta_id: Number(choose.value),
              observaciones: obs ? obs.value : '',
            },
          });
          if (!s.ok) return alert(s.error || 'Error');
          await pintarListasBanco();
        };
        if (obs) {
          obs.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') {
              ev.preventDefault();
              btn.click();
            }
          });
        }
      }
      ul.appendChild(li);
    });
  }

  document.addEventListener('click', (ev) => {
    document.querySelectorAll('details.yo-mov-menu[open]').forEach((d) => {
      if (!d.contains(ev.target)) d.open = false;
    });
  });

  async function iniciar() {
    bindMes();
    bindAlta();
    bindDesdoblar();
    await cargarConceptosGenerales();
    if (nav === 'yo') {
      await cargarCategorias();
      await pintarResumen();
    }
    if (nav === 'yo-movimientos') {
      await cargarCategorias();
      await pintarLista();
    }
    if (nav === 'yo-categorias') {
      await pintarCategorias();
    }
    if (nav === 'yo-banco') {
      await pintarBanco();
    }
    if (nav === 'yo-remesas') {
      await pintarRemesas();
    }
    if (nav === 'yo-cierre') {
      bindCierreForms();
      await pintarCierre();
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
})();
