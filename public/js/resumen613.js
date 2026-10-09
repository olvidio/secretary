(function () {
  const CUENTA = window.CUENTA_613 || 'P';

  function enteroEs(valor) {
    if (valor === null || valor === undefined || valor === '') return '';
    let n = Math.round(Number(String(valor).replace(',', '.')));
    if (!Number.isFinite(n)) return '';
    if (n === 0) n = 0;
    return n.toLocaleString(secretaryLocale(), {
      useGrouping: true,
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    });
  }

  function pct(v) {
    if (v === null || v === undefined) return '';
    return Math.round(v * 100) + ' %';
  }

  const SEP = '<td class="sep" aria-hidden="true"></td>';

  function linea(r, codigo) {
    return r.lineas.find((l) => l.codigo === codigo);
  }

  function lineas(r, codigos) {
    return r.lineas.filter((l) => codigos.includes(l.codigo));
  }

  function fila(clase, etiqueta, l, extraTd) {
    if (!l) return `<tr class="${clase}"><td>${extraTd ?? ''}${esc(etiqueta)}</td>${SEP}<td class="num"></td>${SEP}<td class="num"></td>${SEP}<td class="num pct"></td></tr>`;
    return `<tr class="${clase}"><td>${extraTd ?? ''}${esc(etiqueta)}</td>${SEP}
      <td class="num">${esc(enteroEs(l.previsto))}</td>${SEP}
      <td class="num">${esc(enteroEs(l.realizado))}</td>${SEP}
      <td class="num pct">${esc(pct(l.pct))}</td></tr>`;
  }

  function parseEsNum(valor) {
    if (valor === null || valor === undefined || valor === '') return null;
    const s = String(valor).trim().replace(/\s/g, '');
    if (s === '') return null;
    if (s.includes(',')) {
      return Number(s.replace(/\./g, '').replace(',', '.'));
    }
    return Number(s.replace(',', '.'));
  }

  function decimalEs(valor, opts = {}) {
    const vacio = opts.vacio ?? '0,00';
    if (valor === null || valor === undefined || valor === '') return vacio;
    const n = parseEsNum(valor);
    if (n === null || !Number.isFinite(n)) return vacio;
    return n.toLocaleString(secretaryLocale(), {
      useGrouping: true,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  function formatearInputMoneda(el) {
    if (!el || !String(el.value).trim()) return;
    const fmt = decimalEs(el.value, { vacio: '' });
    if (fmt) el.value = fmt;
  }

  function renderResumenSg(r) {
    const saldo = document.getElementById('rg-saldo-sg');
    if (saldo) saldo.textContent = decimalEs(r.totales?.saldo_final?.realizado);
    const vales = document.getElementById('rg-dinero-caja');
    if (vales) {
      vales.value = r.dinero_arqueo_caja
        ? decimalEs(r.dinero_arqueo_caja, { vacio: '' }) : '';
    }
  }

  function renderResumenG(r) {
    const el = document.getElementById('informe-613-resumen-g');
    if (!el) return;
    if (r.plan_contable === 'H16s') {
      ['rg-personas', 'rg-gasto-viv', 'rg-cocina-mes', 'rg-cocina-acum'].forEach((id) => {
        document.getElementById(id)?.closest('.informe-613-resumen-fila')?.setAttribute('hidden', '');
      });
    }
    const personas = document.getElementById('rg-personas');
    const gastoViv = document.getElementById('rg-gasto-viv');
    if (personas) personas.textContent = enteroEs(r.num_personas);
    if (gastoViv) gastoViv.textContent = enteroEs(r.gasto_vivienda_persona_mes);
    document.getElementById('rg-arqueo').textContent =
      r.arqueo_diferencia != null && r.arqueo_diferencia !== ''
        ? enteroEs(Math.round(Number(String(r.arqueo_diferencia).replace(',', '.'))))
        : '';
    document.getElementById('rg-caja').textContent = decimalEs(r.saldo_caja);
    document.getElementById('rg-banco').textContent = decimalEs(r.saldo_banco);
  }

  function hoyEs() {
    const d = new Date();
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    return `${dd}/${mm}/${d.getFullYear()}`;
  }

  function bodyConfig613() {
    const body = {};
    const ta = document.getElementById('obs-print');
    if (ta) {
      body.observaciones = ta.value;
      if (CUENTA === 'P') body.observaciones_613_p = ta.value;
      else body.observaciones_613_g = ta.value;
    }
    if (CUENTA === 'P') {
      const scc = document.getElementById('saldo-cc-personales');
      if (scc) body.saldo_cc_personales = scc.value;
    } else {
      const mes = document.getElementById('rg-cocina-mes');
      const acum = document.getElementById('rg-cocina-acum');
      if (mes) body.media_cocina_mes = mes.value;
      if (acum) body.media_cocina_acum = acum.value;
      const dc = document.getElementById('rg-dinero-caja');
      const db = document.getElementById('rg-dinero-banco');
      if (dc) body.dinero_arqueo_caja = dc.value;
      if (db) body.dinero_arqueo_banco = db.value;
    }
    return body;
  }

  async function guardarConfig613() {
    const body = bodyConfig613();
    body.fecha_cierre = window.__resumen613?.config?.fecha_cierre;
    const s = await api('/api/informes/613/' + CUENTA + '/manual', { method: 'POST', body });
    if (!s.ok) alert(s.error || t('error'));
    return s.ok;
  }

  function etiqueta613(r) {
    if (CUENTA === 'G' && r?.plan_contable === 'H16s') return '613 G-D';
    return '613 ' + CUENTA;
  }

  function nombrePdf(r) {
    const centro = (r.config?.centro || 'centro').replace(/\s+/g, '_');
    const cierre = (r.config?.fecha_cierre || '').slice(0, 7);
    const libro = CUENTA === 'G' && r?.plan_contable === 'H16s' ? 'G-D' : CUENTA;
    return `613_${libro}_${centro}_${cierre}.pdf`;
  }

  function imprimirInforme613() {
    document.body.classList.add('informe-613-imprimiendo');
    window.print();
  }

  async function generarPdf() {
    const el = document.getElementById('informe-613');
    if (!el || typeof html2pdf === 'undefined') {
      imprimirInforme613();
      return;
    }
    const btn = document.getElementById('btn-pdf');
    if (btn) btn.disabled = true;
    try {
      await html2pdf().set({
        margin: [10, 10, 12, 10],
        filename: nombrePdf(window.__resumen613 || {}),
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, letterRendering: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] },
      }).from(el).save();
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  document.addEventListener('DOMContentLoaded', async () => {
    document.body.classList.add('informe-613-hoja');
    const r = await api('/api/informes/613/' + CUENTA);
    window.__resumen613 = r;

    document.getElementById('ctr-nombre').textContent = r.config.centro;
    document.getElementById('fecha-cierre').textContent = fmtFecha(r.config.fecha_cierre);
    document.getElementById('fecha-impresion').textContent = hoyEs();
    document.getElementById('codigo-informe').textContent = etiqueta613(r);

    const tb = document.getElementById('informe-613-body');
    tb.innerHTML = CUENTA === 'P'
      ? Bloques613P.bloquesP(r)
      : (r.plan_contable === 'H16s' ? Bloques613G.bloquesCentroSg(r) : Bloques613G.bloquesG(r));

    if (CUENTA === 'G' && r.plan_contable === 'H16s') renderResumenSg(r);
    else if (CUENTA === 'G') renderResumenG(r);

    const obs = document.getElementById('obs-print');
    const scc = document.getElementById('saldo-cc-personales');
    const cocinaMes = document.getElementById('rg-cocina-mes');
    const cocinaAcum = document.getElementById('rg-cocina-acum');
    const dineroCaja = document.getElementById('rg-dinero-caja');
    const dineroBanco = document.getElementById('rg-dinero-banco');
    if (obs) obs.value = r.observaciones || '';
    if (scc) {
      const l9 = r.lineas?.find((l) => l.codigo === '9');
      const raw = r.saldo_cc_personales ?? l9?.realizado ?? '';
      scc.value = raw !== '' && raw != null ? decimalEs(raw, { vacio: '' }) : '';
    }
    if (cocinaMes) cocinaMes.value = r.media_cocina_mes ? decimalEs(r.media_cocina_mes) : '';
    if (cocinaAcum) cocinaAcum.value = r.media_cocina_acum ? decimalEs(r.media_cocina_acum) : '';
    if (dineroCaja) {
      dineroCaja.value = r.dinero_arqueo_caja
        ? decimalEs(r.dinero_arqueo_caja, { vacio: '' }) : '';
    }
    if (dineroBanco) {
      dineroBanco.value = r.dinero_arqueo_banco
        ? decimalEs(r.dinero_arqueo_banco, { vacio: '' }) : '';
    }

    [obs, scc, cocinaMes, cocinaAcum, dineroCaja, dineroBanco].filter(Boolean).forEach((el) => {
      el.addEventListener('change', () => {
        if (el.classList.contains('informe-613-cocina-input')) formatearInputMoneda(el);
        guardarConfig613();
      });
    });

    document.getElementById('btn-imprimir')?.addEventListener('click', imprimirInforme613);
    document.getElementById('btn-pdf')?.addEventListener('click', generarPdf);
    window.addEventListener('afterprint', () => {
      document.body.classList.remove('informe-613-imprimiendo');
    });
  });
})();
