(function () {
  const CUENTA = window.CUENTA_613 || 'P';

  function enteroEs(valor) {
    if (valor === null || valor === undefined || valor === '') return '';
    const n = Math.round(Number(String(valor).replace(',', '.')));
    if (!Number.isFinite(n)) return '';
    return n.toLocaleString('es-ES', {
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

  function nums(l) {
    if (!l) return `<td class="num"></td>${SEP}<td class="num"></td>${SEP}<td class="num pct"></td>`;
    return `<td class="num">${esc(enteroEs(l.previsto))}</td>${SEP}
      <td class="num">${esc(enteroEs(l.realizado))}</td>${SEP}
      <td class="num pct">${esc(pct(l.pct))}</td>`;
  }

  function fila(clase, etiqueta, l, extraTd) {
    return `<tr class="${clase}"><td>${extraTd ?? ''}${esc(etiqueta)}</td>${SEP}${nums(l)}</tr>`;
  }

  /** IX. Saldo c/c personales — realizado editable (celda azul). */
  function filaSaldoCc(clase, etiqueta, l) {
    const prev = l ? esc(enteroEs(l.previsto)) : '';
    const pctVal = l ? esc(pct(l.pct)) : '';
    return `<tr class="${clase}"><td>${esc(etiqueta)}</td>${SEP}
      <td class="num">${prev}</td>${SEP}
      <td class="num"><input type="text" name="saldo_cc_personales" id="saldo-cc-personales"
        class="informe-613-cocina-input informe-613-tabla-input" aria-label="Saldo en las c/c personales"></td>${SEP}
      <td class="num pct">${pctVal}</td></tr>`;
  }

  function linea(r, codigo) {
    return r.lineas.find((l) => l.codigo === codigo);
  }

  function lineas(r, codigos) {
    return r.lineas.filter((l) => codigos.includes(l.codigo));
  }

  const GASTOS_G = ['201', '202', '203', '204', '205', '206', '207', '208', '209', '210', '211', '212', '213', '214', '215'];

  /** Gastos G (201–215) → 01. Agua … 15. Otros. */
  function etiquetaGastoG(l) {
    const n = parseInt(String(l.codigo), 10) - 200;
    const sub = String(n).padStart(2, '0');
    const nombre = l.etiqueta.replace(/^\d+\.\s*/, '');
    return `${sub}. ${nombre}`;
  }

  /** Prefijo de capítulo (1, 2, 7…) → subnúmero visible (23 → 3. Ropa). */
  function etiquetaSub(l, prefijoCapitulo, opts = {}) {
    const { ancho = null } = opts;
    const cod = String(l.codigo);
    let sub = cod.startsWith(prefijoCapitulo) ? cod.slice(prefijoCapitulo.length) : cod;
    sub = String(parseInt(sub, 10) || sub);
    if (ancho !== null) sub = sub.padStart(ancho, '0');
    const nombre = l.etiqueta.replace(/^\d+\.\s*/, '');
    return `${sub}. ${nombre}`;
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
    return n.toLocaleString('es-ES', {
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

  function bloquesP(r) {
    const tot = r.totales || {};
    const out = [];
    out.push(fila('sec sec-cab', 'I. Ingresos', tot.ingresos));
    out.push(fila('sub-grp', '1. Personales', null));
    lineas(r, ['111', '112', '113']).forEach((l) => {
      out.push(fila('sub sub-italic', l.etiqueta, l));
    });
    out.push(fila('sub', '2. Extraordinarios', linea(r, '12')));
    out.push(fila('sec sec-cab sec-divide', 'II. Gastos personales', tot.gastos));
    lineas(r, ['21', '22', '23', '24', '25', '26', '27', '28']).forEach((l) => {
      out.push(fila('sub', etiquetaSub(l, '2'), l));
    });
    out.push(fila('sec sec-cab sec-total sec-divide', 'III. Disponible (ingresos-gastos)', tot.disponible));
    out.push(fila('sec sec-cab sec-divide', 'IV. Ayudas familiares', linea(r, '4')));
    out.push(fila('sec sec-cab sec-divide', 'V. Atención labores', tot.atencion_labores));
    lineas(r, ['51', '52']).forEach((l, i) => {
      out.push(fila('sub', `${i + 1}. ${l.etiqueta}`, l));
    });
    out.push(fila('sec sec-cab sec-divide', 'VI. Necesidades de la sede del ctr', linea(r, '6')));
    out.push(fila('sec sec-cab sec-divide', 'VII. Otras labores apostólicas', tot.labores));
    const laboresCodigos = r.partidas_labores || ['71', '72', '73', '74', '75', '76', '77', '78', '79'];
    lineas(r, laboresCodigos).forEach((l) => {
      out.push(fila('sub', etiquetaSub(l, '7'), l));
    });
    out.push(fila('sec sec-cab sec-total sec-divide', 'VIII. Saldo final (III-IV-V-VI-VII)', tot.saldo_final));
    out.push(filaSaldoCc('sec sec-cab sec-azul sec-divide', 'IX. Saldo en las c/c personales', linea(r, '9')));
    return out.join('');
  }

  function bloquesG(r) {
    const tot = r.totales || {};
    const out = [];
    out.push(fila('sec sec-cab', 'I. Ingresos', tot.ingresos));
    lineas(r, ['11', '12', '13', '14', '15']).forEach((l) => {
      out.push(fila('sub', etiquetaSub(l, '1'), l));
    });
    out.push(fila('sec sec-cab sec-divide', 'II. Gastos', tot.gastos));
    lineas(r, GASTOS_G).forEach((l) => {
      out.push(fila('sub', etiquetaGastoG(l), l));
    });
    out.push(fila('sec sec-cab sec-total sec-divide', 'III. Disponible', tot.disponible));
    out.push(fila('sub', '1. Saldo (ingresos-gastos)', tot.saldo_ingresos_gastos));
    out.push(fila('sub', '2. Disponible al inicio', linea(r, '32')));
    return out.join('');
  }

  function renderResumenG(r, arqueoResp) {
    const el = document.getElementById('informe-613-resumen-g');
    if (!el) return;
    const arqueo = arqueoResp?.arqueo;
    document.getElementById('rg-personas').textContent = enteroEs(r.num_personas);
    document.getElementById('rg-gasto-viv').textContent = enteroEs(r.gasto_vivienda_persona_mes);
    const saldoCaja = Math.round(Number(r.saldo_caja || 0));
    const arqueoTotal = arqueo?.total ? Math.round(Number(arqueo.total)) : null;
    document.getElementById('rg-arqueo').textContent =
      arqueoTotal !== null ? enteroEs(arqueoTotal - saldoCaja) : '';
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
    if (!s.ok) alert(s.error);
    return s.ok;
  }

  function nombrePdf(r) {
    const centro = (r.config?.centro || 'centro').replace(/\s+/g, '_');
    const cierre = (r.config?.fecha_cierre || '').slice(0, 7);
    return `613_${CUENTA}_${centro}_${cierre}.pdf`;
  }

  async function generarPdf() {
    const el = document.getElementById('informe-613');
    if (!el || typeof html2pdf === 'undefined') {
      window.print();
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
    const [r, arqueoG] = await Promise.all([
      api('/api/informes/613/' + CUENTA),
      CUENTA === 'G' ? api('/api/arqueos/G') : Promise.resolve(null),
    ]);
    window.__resumen613 = r;

    document.getElementById('ctr-nombre').textContent = r.config.centro;
    document.getElementById('fecha-cierre').textContent = fmtFecha(r.config.fecha_cierre);
    document.getElementById('fecha-impresion').textContent = hoyEs();
    document.getElementById('codigo-informe').textContent = '613 ' + CUENTA;

    const tb = document.getElementById('informe-613-body');
    tb.innerHTML = CUENTA === 'P' ? bloquesP(r) : bloquesG(r);

    if (CUENTA === 'G') renderResumenG(r, arqueoG);

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
      const rawCaja = r.dinero_arqueo_caja || arqueoG?.arqueo?.total_es || '';
      dineroCaja.value = rawCaja ? decimalEs(rawCaja, { vacio: '' }) : '';
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

    document.getElementById('btn-imprimir')?.addEventListener('click', () => {
      window.print();
    });
    document.getElementById('btn-pdf')?.addEventListener('click', generarPdf);
  });
})();
