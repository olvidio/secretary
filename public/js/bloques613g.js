/** Filas del 613 G y G-D (informe y presupuesto anual). */
(function (global) {
  const SEP = '<td class="sep" aria-hidden="true"></td>';
  const GASTOS_G = ['201', '202', '203', '204', '205', '206', '207', '208', '209', '210', '211', '212', '213', '214', '215'];

  function enteroEs(valor) {
    if (valor === null || valor === undefined || valor === '') return '';
    let n = Math.round(Number(String(valor).replace(',', '.')));
    if (!Number.isFinite(n)) return '';
    if (n === 0) n = 0;
    return n.toLocaleString(typeof secretaryLocale === 'function' ? secretaryLocale() : 'es-ES', {
      useGrouping: true,
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    });
  }

  function pct(v) {
    if (v === null || v === undefined) return '';
    return Math.round(v * 100) + ' %';
  }

  function nums(l) {
    if (!l) return `<td class="num"></td>${SEP}<td class="num"></td>${SEP}<td class="num pct"></td>`;
    return `<td class="num">${esc(enteroEs(l.previsto))}</td>${SEP}
      <td class="num">${esc(enteroEs(l.realizado))}</td>${SEP}
      <td class="num pct">${esc(pct(l.pct))}</td>`;
  }

  function numsSoloPrevisto(l, codigo) {
    const valEs = l?.previsto_es ?? (l?.previsto != null ? enteroEs(l.previsto) : '');
    const inp = codigo
      ? `<input type="text" name="${esc(codigo)}" class="informe-613-cocina-input informe-613-tabla-input num" `
        + `value="${esc(valEs)}" aria-label="${esc(codigo)}">`
      : esc(enteroEs(l?.previsto));
    return `<td class="num">${inp}</td>${SEP}<td class="num"></td>${SEP}<td class="num pct"></td>`;
  }

  function fila(clase, etiqueta, l, extraTd) {
    return `<tr class="${clase}"><td>${extraTd ?? ''}${esc(etiqueta)}</td>${SEP}${nums(l)}</tr>`;
  }

  function filaPresupuesto(clase, etiqueta, l, codigoEditable) {
    return `<tr class="${clase}"><td>${esc(etiqueta)}</td>${SEP}${numsSoloPrevisto(l, codigoEditable)}</tr>`;
  }

  function linea(r, codigo) {
    return r.lineas.find((l) => l.codigo === codigo);
  }

  function lineas(r, codigos) {
    return r.lineas.filter((l) => codigos.includes(l.codigo));
  }

  function etiquetaGastoG(l) {
    const n = parseInt(String(l.codigo), 10) - 200;
    const sub = String(n).padStart(2, '0');
    const nombre = l.etiqueta.replace(/^\d+\.\s*/, '');
    return `${sub}. ${nombre}`;
  }

  function etiquetaSub(l, prefijoCapitulo, opts = {}) {
    const { ancho = null } = opts;
    const cod = String(l.codigo);
    let sub = cod.startsWith(prefijoCapitulo) ? cod.slice(prefijoCapitulo.length) : cod;
    sub = String(parseInt(sub, 10) || sub);
    if (ancho !== null) sub = sub.padStart(ancho, '0');
    const nombre = l.etiqueta.replace(/^\d+\.\s*/, '');
    return `${sub}. ${nombre}`;
  }

  function bloquesG(r, opts = {}) {
    const presu = !!opts.presupuesto;
    const tot = r.totales || {};
    const out = [];
    const cab = (clase, etq, l) => (presu ? filaPresupuesto(clase, etq, l, null) : fila(clase, etq, l));
    const det = (clase, etq, l, cod) => (presu ? filaPresupuesto(clase, etq, l, cod) : fila(clase, etq, l));

    out.push(cab('sec sec-cab sec-total', 'I. Ingresos', tot.ingresos));
    lineas(r, ['11', '12', '13', '14', '15']).forEach((l) => {
      out.push(det('sub', etiquetaSub(l, '1'), l, l.codigo));
    });
    out.push(cab('sec sec-cab sec-total sec-divide', 'II. Gastos', tot.gastos));
    lineas(r, GASTOS_G).forEach((l) => {
      out.push(det('sub', etiquetaGastoG(l), l, l.codigo));
    });
    out.push(cab('sec sec-cab sec-total sec-divide', 'III. Disponible', tot.disponible));
    out.push(presu
      ? filaPresupuesto('sub', '1. Saldo (ingresos-gastos)', tot.saldo_ingresos_gastos, null)
      : fila('sub', '1. Saldo (ingresos-gastos)', tot.saldo_ingresos_gastos));
    out.push(det('sub', '2. Disponible al inicio', linea(r, '32'), '32'));
    return out.join('');
  }

  function bloquesCentroSg(r, opts = {}) {
    const presu = !!opts.presupuesto;
    const tot = r.totales || {};
    const out = [];
    const cab = (clase, etq, l) => (presu ? filaPresupuesto(clase, etq, l, null) : fila(clase, etq, l));
    const det = (clase, etq, l, cod) => (presu ? filaPresupuesto(clase, etq, l, cod) : fila(clase, etq, l));

    out.push(cab('sec sec-cab sec-total', 'I. Ingresos', tot.ingresos));
    lineas(r, ['11', '12', '13', '14']).forEach((l) => {
      out.push(det('sub', etiquetaSub(l, '1'), l, l.codigo));
    });
    out.push(cab('sec sec-cab sec-total sec-divide', 'II. Gastos', tot.gastos));
    lineas(r, ['21', '22', '23', '24', '25', '26', '27', '28']).forEach((l) => {
      out.push(det('sub', etiquetaSub(l, '2'), l, l.codigo));
    });
    out.push(cab('sec sec-cab sec-total sec-divide', 'III. Disponible', tot.disponible));
    out.push(presu
      ? filaPresupuesto('sub', '1. Saldo (ingresos-gastos)', tot.saldo_ingresos_gastos, null)
      : fila('sub', '1. Saldo (ingresos-gastos)', tot.saldo_ingresos_gastos));
    out.push(det('sub', '2. Disponible a 1 de enero', linea(r, '32'), '32'));
    out.push(cab('sec sec-cab sec-total sec-divide', 'IV. Destinos', tot.destinos));
    (r.lineas || []).filter((l) => {
      const n = parseInt(String(l.codigo), 10);
      return n >= 41 && n <= 54;
    }).forEach((l) => {
      out.push(det('sub', etiquetaSub(l, '4'), l, l.codigo));
    });
    out.push(cab('sec sec-cab sec-total', 'V. Saldo final (III-IV)', tot.saldo_final));

    if (presu) {
      return out.join('');
    }

    const s = r.resumen_sg || {};
    out.push(fila('sub sec-divide', 'Nº de s del ctr', {
      previsto: s.num_s_previsto,
      realizado: s.num_s,
      pct: null,
    }));
    out.push(fila('sub', 'Nº acumulado de aportaciones ordinarias', {
      previsto: s.aportaciones_previsto,
      realizado: s.aportaciones,
      pct: s.aportaciones_pct,
    }));
    out.push(`<tr class="sub"><td>Media de las aportaciones ordinarias *</td>${SEP}
      <td class="num">${esc(s.media_prevista_es || '')}</td>${SEP}
      <td class="num">${esc(s.media_es || '')}</td>${SEP}
      <td class="num pct">${esc(pct(s.media_pct))}</td></tr>`);
    out.push(fila('sub', 'Nº de s sin aportación en el año', {
      previsto: '',
      realizado: s.sin_aportacion,
      pct: null,
    }));
    return out.join('');
  }

  global.Bloques613G = { bloquesG, bloquesCentroSg, enteroEs };
})(typeof window !== 'undefined' ? window : globalThis);
