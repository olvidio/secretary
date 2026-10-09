(function () {
  const CUENTA = 'G';

  function esCentroSg(v) {
    return v?.plan_contable === 'H16s';
  }

  function pintarVista613(r) {
    const v = r.vista_613;
    if (!v) return;
    document.getElementById('presu-ctr-nombre').textContent = v.centro || r.config?.centro || '';
    document.getElementById('presu-ejercicio').textContent = v.etiqueta_ejercicio || r.etiqueta_presupuesto || '';
    const pie = document.getElementById('presu-pie-etiqueta');
    if (pie) {
      const i18n = window.I18N_PRESU_G || {};
      pie.textContent = esCentroSg(v) ? (i18n.pieGd || 'Presupuesto G-D') : (i18n.pieG || 'Presupuesto G');
    }
    const tb = document.getElementById('presupuesto-613-body');
    if (tb) {
      tb.innerHTML = esCentroSg(v)
        ? Bloques613G.bloquesCentroSg(v, { presupuesto: true })
        : Bloques613G.bloquesG(v, { presupuesto: true });
    }
    tb?.querySelectorAll('input[name]').forEach((inp) => {
      inp.addEventListener('blur', () => {
        if (inp.value.trim()) inp.value = fmtEnteroEs(inp.value);
      });
    });
  }

  function etiquetaPresupuestoSeleccionada() {
    const sel = document.getElementById('sel-anio-presupuesto');
    if (!sel || sel.disabled || !sel.value) return '';
    return sel.value;
  }

  function listaEtiquetasPresu(r) {
    const etiquetas = [];
    const push = (valor) => {
      const et = String(valor || '').trim();
      if (et && !etiquetas.includes(et)) etiquetas.push(et);
    };
    const raw = (r && r.etiquetas) || [];
    (Array.isArray(raw) ? raw : Object.values(raw)).forEach(push);
    if (r) {
      push(r.etiqueta_trabajo);
      push(r.etiqueta_defecto);
      push(r.etiqueta_presupuesto);
    }
    return etiquetas;
  }

  function rellenarSelectAniosPresu(r, forzarDefecto) {
    const sel = document.getElementById('sel-anio-presupuesto');
    if (!sel) return;
    const prev = sel.value;
    const etiquetas = listaEtiquetasPresu(r);
    [...sel.options].forEach((o) => {
      if (o.value && !etiquetas.includes(o.value)) etiquetas.push(o.value);
    });
    etiquetas.sort();
    const defecto = r && (r.etiqueta_trabajo || r.etiqueta_presupuesto || r.etiqueta_defecto);
    const activa = r && r.etiqueta_presupuesto;
    sel.innerHTML = '';
    etiquetas.forEach((et) => {
      const opt = document.createElement('option');
      opt.value = et;
      opt.textContent = et;
      sel.appendChild(opt);
    });
    if (forzarDefecto && defecto && etiquetas.includes(String(defecto))) {
      sel.value = String(defecto);
    } else if (activa && etiquetas.includes(String(activa))) {
      sel.value = String(activa);
    } else if (prev && etiquetas.includes(prev)) {
      sel.value = prev;
    } else if (defecto && etiquetas.includes(String(defecto))) {
      sel.value = String(defecto);
    } else if (etiquetas.length) {
      sel.value = etiquetas[etiquetas.length - 1];
    }
    sel.disabled = etiquetas.length === 0;
  }

  async function cargarPresupuesto(forzarDefecto) {
    let url = '/api/presupuestos/' + CUENTA;
    if (!forzarDefecto) {
      const etiqueta = etiquetaPresupuestoSeleccionada();
      if (etiqueta) url += '?etiqueta=' + encodeURIComponent(etiqueta);
    }
    const r = await api(url);
    if (!r.ok) {
      return alert(r.error || 'Error');
    }
    rellenarSelectAniosPresu(r, !!forzarDefecto);
    pintarVista613(r);
  }

  document.addEventListener('DOMContentLoaded', async () => {
    document.body.classList.add('informe-613-hoja', 'presupuesto-g-hoja');
    await cargarPresupuesto(true);
    document.getElementById('sel-anio-presupuesto')?.addEventListener('change', () => cargarPresupuesto(false));
    document.getElementById('form-presu-g')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const lineas = {};
      ev.target.querySelectorAll('input[name]').forEach((i) => {
        lineas[i.name] = i.value.trim() ? fmtEnteroEs(i.value) : '';
      });
      const body = { lineas };
      const etiqueta = etiquetaPresupuestoSeleccionada();
      if (etiqueta) body.etiqueta = etiqueta;
      const s = await api('/api/presupuestos/' + CUENTA, { method: 'POST', body });
      const msg = document.getElementById('msg-presu-g');
      if (msg) msg.hidden = !s.ok;
      if (!s.ok) return alert(s.error);
      rellenarSelectAniosPresu(s, false);
      pintarVista613(s);
    });
    document.getElementById('btn-imprimir-presu-g')?.addEventListener('click', () => {
      document.body.classList.add('informe-613-imprimiendo');
      window.print();
    });
    window.addEventListener('afterprint', () => {
      document.body.classList.remove('informe-613-imprimiendo');
    });
  });
})();
