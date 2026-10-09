/** Filas del 613 P (informe y presupuesto anual). */
(function (global) {
  const SEP = '<td class="sep" aria-hidden="true"></td>';

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

  function filaSaldoCc(clase, etiqueta, l, modoPresupuesto) {
    if (modoPresupuesto) {
      return filaPresupuesto(clase, etiqueta, l, '9');
    }
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

  function sumLineas(items) {
    const valid = items.filter(Boolean);
    if (valid.length === 0) return null;
    let prev = 0;
    let real = 0;
    valid.forEach((l) => {
      prev += parseFloat(String(l.previsto)) || 0;
      real += parseFloat(String(l.realizado)) || 0;
    });
    return {
      previsto: prev.toFixed(2),
      realizado: real.toFixed(2),
      pct: prev > 0 ? real / prev : null,
    };
  }

  function nombreLinea(l) {
    return l.etiqueta.replace(/^\d+\.\s*/, '');
  }

  function mostrar212(r, modoPresupuesto) {
    const l = linea(r, '212');
    if (!l) return false;
    if (modoPresupuesto) {
      return true;
    }
    const n = parseFloat(String(l.realizado));
    return Number.isFinite(n) && n !== 0;
  }

  function bloqueViviendaP(r, modoPresupuesto) {
    const l211 = linea(r, '211');
    const l212 = linea(r, '212');
    const incluir212 = mostrar212(r, modoPresupuesto);
    if (!l211 && !incluir212) return [];
    const partes = [l211, incluir212 ? l212 : null].filter(Boolean);
    const tot = sumLineas(partes);
    if (!tot) return [];
    const out = [modoPresupuesto
      ? filaPresupuesto('sub sub-total', '1. Vivienda', tot, null)
      : fila('sub sub-total', '1. Vivienda', tot)];
    if (l211) {
      out.push(modoPresupuesto
        ? filaPresupuesto('sub sub-sub', `211. ${nombreLinea(l211)}`, l211, '211')
        : fila('sub sub-sub', `211. ${nombreLinea(l211)}`, l211));
    }
    if (incluir212 && l212) {
      out.push(modoPresupuesto
        ? filaPresupuesto('sub sub-sub', `212. ${nombreLinea(l212)}`, l212, '212')
        : fila('sub sub-sub', `212. ${nombreLinea(l212)}`, l212));
    }
    return out;
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

  /**
   * @param {object} r respuesta 613 o vista_613 del presupuesto
   * @param {{ presupuesto?: boolean }} opts
   */
  function bloquesP(r, opts = {}) {
    const presu = !!opts.presupuesto;
    const tot = r.totales || {};
    const out = [];
    const cab = (clase, etq, l) => (presu ? filaPresupuesto(clase, etq, l, null) : fila(clase, etq, l));
    const det = (clase, etq, l, cod) => (presu ? filaPresupuesto(clase, etq, l, cod) : fila(clase, etq, l));

    out.push(cab('sec sec-cab', 'I. Ingresos', tot.ingresos));
    out.push(fila('sub-grp', '1. Personales', null));
    lineas(r, ['111', '112', '113']).forEach((l) => {
      out.push(presu
        ? filaPresupuesto('sub sub-italic', l.etiqueta, l, l.codigo)
        : fila('sub sub-italic', l.etiqueta, l));
    });
    out.push(det('sub', '2. Extraordinarios', linea(r, '12'), '12'));
    out.push(cab('sec sec-cab sec-divide', 'II. Gastos personales', tot.gastos));
    bloqueViviendaP(r, presu).forEach((html) => out.push(html));
    lineas(r, ['22', '23', '24', '25', '26', '27', '28']).forEach((l) => {
      out.push(det('sub', etiquetaSub(l, '2'), l, l.codigo));
    });
    out.push(cab('sec sec-cab sec-total sec-divide', 'III. Disponible (ingresos-gastos)', tot.disponible));
    out.push(det('sec sec-cab sec-divide', 'IV. Ayudas familiares', linea(r, '4'), '4'));
    out.push(cab('sec sec-cab sec-divide', 'V. Atención labores', tot.atencion_labores));
    lineas(r, ['51', '52']).forEach((l, i) => {
      out.push(det('sub', `${i + 1}. ${l.etiqueta}`, l, l.codigo));
    });
    out.push(det('sec sec-cab sec-divide', 'VI. Necesidades de la sede del ctr', linea(r, '6'), '6'));
    out.push(cab('sec sec-cab sec-divide', 'VII. Otras labores apostólicas', tot.labores));
    const laboresCodigos = r.partidas_labores || ['71', '72', '73', '74', '75', '76', '77', '78', '79'];
    lineas(r, laboresCodigos).forEach((l) => {
      out.push(det('sub', etiquetaSub(l, '7'), l, l.codigo));
    });
    out.push(cab('sec sec-cab sec-total sec-divide', 'VIII. Saldo final (III-IV-V-VI-VII)', tot.saldo_final));
    out.push(filaSaldoCc('sec sec-cab sec-azul sec-divide', 'IX. Saldo en las c/c personales', linea(r, '9'), presu));
    return out.join('');
  }

  global.Bloques613P = { bloquesP, enteroEs };
})(typeof window !== 'undefined' ? window : globalThis);
