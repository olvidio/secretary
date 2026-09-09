(function () {
  const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  const COLORES = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6',
    '#3b82f6', '#8b5cf6', '#ec4899', '#64748b', '#0f766e', '#b45309', '#7c3aed'];

  const nav = document.body.getAttribute('data-nav') || '';
  const state = {
    anio: new Date().getFullYear(),
    mes: new Date().getMonth() + 1,
    categorias: [],
    tesoreria: [],
    sentido: 'gasto',
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
  function tituloMes() {
    return MESES[state.mes - 1] + ' ' + state.anio;
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
  function bindMes() {
    const t = qs('#yo-mes-titulo');
    if (t) t.textContent = tituloMes();
    const ant = qs('#yo-mes-ant');
    const sig = qs('#yo-mes-sig');
    if (ant) ant.onclick = () => { moverMes(-1); recargar(); };
    if (sig) sig.onclick = () => { moverMes(1); recargar(); };
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

  async function pintarResumen() {
    const r = await api('/api/yo/resumen?' + paramsMes());
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

  async function pintarLista() {
    const r = await api('/api/yo/movimientos?' + paramsMes());
    const ul = qs('#yo-lista');
    const vacia = qs('#yo-lista-vacia');
    if (!ul) return;
    const movs = r.movimientos || [];
    if (vacia) vacia.hidden = movs.length > 0;
    ul.innerHTML = '';
    movs.slice().reverse().forEach((m) => {
      const li = document.createElement('li');
      const letra = (m.categoria || m.sentido || '?').slice(0, 1).toUpperCase();
      const cls = m.sentido === 'ingreso' ? 'ing' : (m.sentido === 'gasto' ? 'gas' : '');
      const signo = m.sentido === 'ingreso' ? '+' : (m.sentido === 'gasto' ? '−' : '');
      li.innerHTML = '<span class="yo-chip" style="background:' + colorDe(m.categoria_codigo || m.sentido) + '">' + esc(letra)
        + '</span><span class="meta"><strong>' + esc(m.categoria || (m.sentido === 'traspaso' ? 'Traspaso' : m.sentido))
        + '</strong><small>' + esc(fmtFecha(m.fecha)) + (m.nota ? ' · ' + esc(m.nota) : '')
        + (m.tesoreria ? ' · ' + esc(m.tesoreria) : '') + '</small></span>'
        + '<span class="imp ' + cls + '">' + signo + esc(m.cantidad_es) + '</span>'
        + '<button type="button" class="del" data-id="' + m.id + '">✕</button>';
      li.querySelector('.del').onclick = async () => {
        if (!confirm('¿Borrar este movimiento?')) return;
        const del = await api('/api/yo/movimientos/' + m.id, { method: 'DELETE' });
        if (!del.ok) { alert(del.error || 'No se pudo borrar'); return; }
        recargar();
      };
      ul.appendChild(li);
    });
  }

  function catsDelSentido(sentido) {
    const tipo = sentido === 'ingreso' ? 'ingreso' : 'gasto';
    return state.categorias.filter((c) => c.tipo === tipo);
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
    if (first) first.click();
  }

  function abrirModal(sentido) {
    const modal = qs('#yo-modal');
    const form = qs('#yo-form');
    if (!modal || !form) return;
    state.sentido = sentido;
    form.reset();
    form.sentido.value = sentido;
    form.fecha.value = hoyIso();
    qs('#yo-form-err').hidden = true;
    const titulo = qs('#yo-form-titulo');
    const traspaso = qs('#yo-traspaso-campos');
    const tes = qs('.yo-tesoreria');
    const grid = qs('#yo-cats-grid');
    const cuenta = qs('#yo-form [name="cuenta_id"]');
    if (sentido === 'traspaso') {
      if (titulo) titulo.textContent = 'Traspaso';
      if (traspaso) traspaso.hidden = false;
      if (tes) tes.hidden = true;
      if (grid) grid.hidden = true;
      if (cuenta) { cuenta.required = false; cuenta.value = ''; }
    } else {
      if (titulo) titulo.textContent = sentido === 'ingreso' ? 'Ingreso' : 'Gasto';
      if (traspaso) traspaso.hidden = true;
      if (tes) tes.hidden = false;
      if (grid) grid.hidden = false;
      if (cuenta) cuenta.required = true;
      pintarGridCats(sentido);
    }
    modal.hidden = false;
  }

  function cerrarModal() {
    const modal = qs('#yo-modal');
    if (modal) modal.hidden = true;
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
    if (!form) return;
    form.onsubmit = async (ev) => {
      ev.preventDefault();
      const err = qs('#yo-form-err');
      err.hidden = true;
      const body = formObj(form);
      const r = await api('/api/yo/movimientos', { method: 'POST', body });
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
    const maestros = (r.categorias || []).filter((c) => c.codigo === c.codigo_maestro);
    if (sel) {
      sel.innerHTML = maestros.map((c) =>
        '<option value="' + esc(c.codigo) + '">' + esc(c.codigo + ' · ' + c.nombre) + '</option>'
      ).join('');
    }
    if (list) {
      list.innerHTML = (r.categorias || []).map((c) =>
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

  async function recargar() {
    bindMes();
    if (nav === 'yo') {
      await cargarCategorias();
      await pintarResumen();
    }
    if (nav === 'yo-movimientos') {
      await pintarLista();
    }
    if (nav === 'yo-remesas') {
      await pintarRemesas();
    }
  }

  async function pintarRemesas() {
    const msg = qs('#yo-remesa-msg');
    const err = qs('#yo-remesa-err');
    if (msg) msg.hidden = true;
    if (err) err.hidden = true;
    const r = await api('/api/yo/remesas?' + paramsMes());
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
      ul.innerHTML = lineas.map((l) =>
        '<li><span class="meta"><strong>' + esc(l.codigo_maestro + ' · ' + (l.nombre || ''))
        + '</strong></span><span class="imp">' + esc(l.importe_es) + '</span></li>'
      ).join('');
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

  document.addEventListener('DOMContentLoaded', async () => {
    bindMes();
    bindAlta();
    if (nav === 'yo') {
      await cargarCategorias();
      await pintarResumen();
    }
    if (nav === 'yo-movimientos') {
      await pintarLista();
    }
    if (nav === 'yo-categorias') {
      await pintarCategorias();
    }
    if (nav === 'yo-remesas') {
      await pintarRemesas();
    }
  });
})();
