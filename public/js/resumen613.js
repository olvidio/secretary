(function () {
  const CUENTA = window.CUENTA_613 || 'P';

  function pct(v) {
    if (v === null || v === undefined) return '';
    return (v * 100).toFixed(1).replace('.', ',') + ' %';
  }

  function nums(l) {
    if (!l) return '<td class="num"></td><td class="num"></td><td class="num"></td>';
    return `<td class="num">${esc(l.previsto_es ?? '')}</td>
      <td class="num">${esc(l.realizado_es ?? '')}</td>
      <td class="num">${pct(l.pct)}</td>`;
  }

  function fila(clase, etiqueta, l, extraTd) {
    return `<tr class="${clase}"><td>${extraTd ?? ''}${esc(etiqueta)}</td>${nums(l)}</tr>`;
  }

  function linea(r, codigo) {
    return r.lineas.find((l) => l.codigo === codigo);
  }

  function lineas(r, codigos) {
    return r.lineas.filter((l) => codigos.includes(l.codigo));
  }

  function bloquesP(r) {
    const tot = r.totales || {};
    const out = [];
    out.push(fila('sec', 'I. Ingresos', tot.ingresos));
    out.push(fila('sub-grp', '1. Personales', null));
    lineas(r, ['111', '112', '113']).forEach((l) => {
      out.push(fila('sub sub-italic', l.etiqueta, l));
    });
    out.push(fila('sub', '2. Extraordinarios', linea(r, '12')));
    out.push(fila('sec sec-gris sec-divide', 'II. Gastos personales', tot.gastos));
    lineas(r, ['21', '22', '23', '24', '25', '26', '27', '28']).forEach((l) => {
      out.push(fila('sub sec-gris', l.etiqueta, l));
    });
    out.push(fila('sec sec-total sec-divide', 'III. Disponible (ingresos-gastos)', tot.disponible));
    out.push(fila('sec sec-divide', 'IV. Ayudas familiares', linea(r, '4')));
    out.push(fila('sec sec-divide', 'V. Atención labores', tot.atencion_labores));
    lineas(r, ['51', '52']).forEach((l, i) => {
      out.push(fila('sub', `${i + 1}. ${l.etiqueta}`, l));
    });
    out.push(fila('sec sec-divide', 'VI. Necesidades de la sede del ctr', linea(r, '6')));
    out.push(fila('sec sec-divide', 'VII. Otras labores apostólicas', tot.labores));
    lineas(r, ['71', '72', '73', '74', '75', '76', '77', '78', '79']).forEach((l) => {
      out.push(fila('sub', l.etiqueta, l));
    });
    out.push(fila('sec sec-gris sec-total sec-divide', 'VIII. Saldo final (III-IV-V-VI-VII)', tot.saldo_final));
    out.push(fila('sec sec-azul sec-divide', 'IX. Saldo en las c/c personales', linea(r, '9')));
    return out.join('');
  }

  function bloquesG(r) {
    const tot = r.totales || {};
    const out = [];
    out.push(fila('sec', 'I. Ingresos', tot.ingresos));
    lineas(r, ['11', '12', '13', '14', '15']).forEach((l) => {
      out.push(fila('sub', l.etiqueta, l));
    });
    out.push(fila('sec sec-gris', 'II. Gastos', tot.gastos));
    r.lineas.filter((l) => String(l.codigo).startsWith('20')).forEach((l) => {
      out.push(fila('sub sec-gris', l.etiqueta, l));
    });
    out.push(fila('sec sec-total', 'Saldo ingresos-gastos', tot.saldo_ingresos_gastos));
    out.push(fila('sec', 'Disponible al inicio', linea(r, '32')));
    out.push(fila('sec sec-total', 'III. Disponible', tot.disponible));
    return out.join('');
  }

  function hoyEs() {
    const d = new Date();
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    return `${dd}/${mm}/${d.getFullYear()}`;
  }

  function syncObservaciones() {
    const ta = document.querySelector('#obs-form textarea[name="observaciones"]');
    const box = document.getElementById('obs-print');
    if (ta && box) box.textContent = ta.value.trim();
  }

  function nombrePdf(r) {
    const centro = (r.config?.centro || 'centro').replace(/\s+/g, '_');
    const cierre = (r.config?.fecha_cierre || '').slice(0, 7);
    return `613_${CUENTA}_${centro}_${cierre}.pdf`;
  }

  async function generarPdf() {
    syncObservaciones();
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
    const r = await api('/api/informes/613/' + CUENTA);
    window.__resumen613 = r;

    document.getElementById('ctr-nombre').textContent = r.config.centro;
    document.getElementById('fecha-cierre').textContent = fmtFecha(r.config.fecha_cierre);
    document.getElementById('fecha-impresion').textContent = hoyEs();
    document.getElementById('codigo-informe').textContent = '613 ' + CUENTA;

    const tb = document.getElementById('informe-613-body');
    tb.innerHTML = CUENTA === 'P' ? bloquesP(r) : bloquesG(r);

    if (CUENTA === 'G') {
      document.getElementById('extra').innerHTML =
        `<p>Personas: ${r.num_personas} · Gasto vivienda/persona/mes: ${esc(r.gasto_vivienda_persona_mes_es)}</p>
         <p>Saldo caja: <strong>${esc(r.saldo_caja_es)}</strong> · Saldo banco: <strong>${esc(r.saldo_banco_es)}</strong></p>`;
    }

    const f = document.getElementById('obs-form');
    if (f.observaciones) f.observaciones.value = r.observaciones || '';
    if (f.saldo_cc_personales) f.saldo_cc_personales.value = r.saldo_cc_personales || '';
    if (f.media_cocina_mes) f.media_cocina_mes.value = r.media_cocina_mes || '';
    if (f.media_cocina_acum) f.media_cocina_acum.value = r.media_cocina_acum || '';
    syncObservaciones();
    f.observaciones?.addEventListener('input', syncObservaciones);

    f.onsubmit = async (ev) => {
      ev.preventDefault();
      syncObservaciones();
      const body = formObj(ev.target);
      if (CUENTA === 'P') body.observaciones_613_p = body.observaciones;
      else body.observaciones_613_g = body.observaciones;
      const s = await api('/api/configuracion', { method: 'POST', body });
      if (!s.ok) alert(s.error);
    };

    document.getElementById('btn-imprimir')?.addEventListener('click', () => {
      syncObservaciones();
      window.print();
    });
    document.getElementById('btn-pdf')?.addEventListener('click', generarPdf);
  });
})();
