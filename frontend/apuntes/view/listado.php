<h1>Apuntes</h1>
<p class="print-hide arqueo-volver" id="apuntes-volver" hidden>
    <a href="#">← Volver</a>
</p>
<form id="filtros" class="filters">
    <select name="cuenta"><option value="">P y G</option><option>P</option><option>G</option></select>
    <select name="origen"><option value="">A/B/C</option><option>A</option><option>B</option><option>C</option></select>
    <input name="iniciales" placeholder="Iniciales">
    <input name="concepto" placeholder="Concepto">
    <input type="date" name="desde">
    <input type="date" name="hasta">
    <button type="submit">Filtrar</button>
</form>
<p id="apuntes-cuadre-ayuda" class="muted" hidden>
    Gasto suma, ingreso resta. El saldo de cada fecha debería ser 0; las fechas que no cuadran se marcan para localizar el desajuste.
</p>
<p id="apuntes-cuadre-total" class="apuntes-cuadre-total" hidden></p>
<table id="tabla-apuntes">
    <thead>
    <tr>
        <th>Fecha</th>
        <th>P/G</th>
        <th>A/B/C</th>
        <th>Inic.</th>
        <th>Concepto</th>
        <th>Observaciones</th>
        <th class="num">Cantidad</th>
        <th class="num col-efecto" hidden>Efecto</th>
        <th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<script>
function parseImporte(raw) {
  if (raw === null || raw === undefined || raw === '') return 0;
  const n = Number(String(raw).replace(',', '.'));
  return Number.isFinite(n) ? n : 0;
}

function fmtEuro(n) {
  return n.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function aplicarFiltrosUrl(form) {
  const q = new URLSearchParams(location.search);
  ['cuenta', 'origen', 'iniciales', 'concepto', 'desde', 'hasta'].forEach((k) => {
    const v = q.get(k);
    const el = form.querySelector('[name="' + k + '"]');
    if (el && v !== null) el.value = v;
  });
}

function modoCuadreA(form) {
  const fd = formObj(form);
  return fd.cuenta === 'P' && fd.origen === 'A' && String(fd.iniciales || '').trim() !== '';
}

function signedImporte(a, naturalezas) {
  const nat = naturalezas[a.concepto_codigo] || '';
  const n = parseImporte(a.cantidad);
  if (nat === 'gasto') return n;
  if (nat === 'ingreso') return -n;
  return 0;
}

function cents(n) {
  return Math.round(n * 100);
}

function mismaCantidad(a, importe) {
  return cents(Math.abs(parseImporte(a.cantidad))) === cents(Math.abs(importe));
}

function etiquetaApunte(a, naturalezas) {
  const nat = naturalezas[a.concepto_codigo] || '';
  const n = parseImporte(a.cantidad);
  let tipo = nat === 'gasto' ? 'gasto' : (nat === 'ingreso' ? 'ingreso' : 'apunte');
  if (nat === 'gasto' && n < 0) tipo = 'abono/devolución';
  const obs = a.observaciones ? ' «' + a.observaciones + '»' : '';
  return tipo + ' G/' + a.origen + ' ' + a.concepto_codigo + obs + ' de ' + fmtEuro(Math.abs(n)) + ' €';
}

function destinoVolver() {
  const raw = new URLSearchParams(location.search).get('from');
  if (raw && /^\/[A-Za-z0-9/?=&._%-]*$/.test(raw) && !raw.startsWith('//')) {
    return raw;
  }
  return '';
}

function queryConFrom(form) {
  const q = new URLSearchParams(formObj(form));
  const from = new URLSearchParams(location.search).get('from');
  if (from) q.set('from', from);
  return q;
}

function mostrarVolver() {
  const wrap = document.getElementById('apuntes-volver');
  const a = wrap.querySelector('a');
  const dest = destinoVolver();
  if (dest) {
    a.href = dest;
    a.onclick = null;
    wrap.hidden = false;
    return;
  }
  if (history.length > 1) {
    a.href = '#';
    a.onclick = (ev) => { ev.preventDefault(); history.back(); };
    wrap.hidden = false;
    return;
  }
  wrap.hidden = true;
}

function candidatoSugerencia(saldoDia, fecha, apuntesG, natG) {
  const objetivo = Math.abs(saldoDia);
  if (cents(objetivo) === 0) return null;
  const delDia = (apuntesG || []).filter((a) => a.fecha === fecha);
  const exactos = delDia.filter((a) => mismaCantidad(a, objetivo));
  const gastos = exactos.filter((a) => (natG[a.concepto_codigo] || '') === 'gasto');
  const candidatos = (gastos.length ? gastos : exactos).slice();
  candidatos.sort((x, y) => (x.origen === 'A' ? 0 : 1) - (y.origen === 'A' ? 0 : 1));
  return candidatos[0] || null;
}

function mensajeSugerencia(saldoDia, a, natG) {
  const n = parseImporte(a.cantidad);
  let msg = 'Sugerencia: en G hay un ' + etiquetaApunte(a, natG) + '.';
  if ((natG[a.concepto_codigo] || '') === 'gasto' && n < 0) {
    msg += ' Es una devolución. En P sobran gastos por esa cantidad; falta un ingreso P/A 111 (el 111 de ese día no incluye este abono).';
  } else if ((natG[a.concepto_codigo] || '') === 'gasto' && saldoDia < 0) {
    msg += ' Falta el gasto P/A de esa cantidad (el 111 de ese día parece incluirlo).';
  } else if ((natG[a.concepto_codigo] || '') === 'ingreso') {
    msg += ' Si vivienda aporta a generales, debería haber un P/21 por el mismo importe.';
  } else {
    msg += ' Puede faltar el apunte P/A pareja.';
  }
  return msg;
}

function sePuedeAceptar21(saldoDia, a, natG) {
  const n = parseImporte(a.cantidad);
  if (n <= 0) return false;
  const nat = natG[a.concepto_codigo] || '';
  if (nat === 'ingreso') return true;
  return nat === 'gasto' && saldoDia < 0;
}

function sePuedeAceptar111(saldoDia, a, natG) {
  const n = parseImporte(a.cantidad);
  return (natG[a.concepto_codigo] || '') === 'gasto' && n < 0 && saldoDia > 0;
}

function cantidadPositiva(a) {
  return (Math.abs(parseImporte(a.cantidad))).toFixed(2);
}

function urlVerG(a, fecha) {
  const q = new URLSearchParams({
    cuenta: 'G',
    origen: a.origen || 'A',
    iniciales: a.iniciales || '',
    desde: fecha,
    hasta: fecha,
    from: '/apuntes?' + queryConFrom(document.getElementById('filtros')).toString(),
  });
  return '/apuntes?' + q.toString();
}

async function aceptarSugerencia21(a) {
  const cant = a.cantidad;
  const fecha = a.fecha;
  const ini = a.iniciales || '';
  const obs = a.observaciones || '';
  const txt = 'Se anotará un gasto P/A 21 (vivienda) de ' + fmtEuro(parseImporte(cant))
    + ' € el ' + (a.fecha_es || fecha)
    + ' y, si esta persona aporta a generales, el ingreso G/A 11.';
  if (!confirm(txt)) return;
  const base = {
    fecha,
    origen: 'A',
    iniciales: ini,
    observaciones: obs,
    cantidad: cantidadPositiva(a),
  };
  const p = await api('/api/apuntes', {
    method: 'POST',
    body: Object.assign({ cuenta: 'P', concepto_codigo: '21' }, base),
  });
  if (!p.ok) return alert(p.error);
  const pers = await api('/api/personas');
  const persona = (pers.personas || []).find((x) => x.iniciales === ini);
  const aporta = persona ? !!persona.vivienda_aporta_generales : true;
  if (aporta) {
    const g = await api('/api/apuntes', {
      method: 'POST',
      body: Object.assign({ cuenta: 'G', concepto_codigo: '11' }, base),
    });
    if (!g.ok) return alert('P/21 creado, pero G/11 falló: ' + g.error);
  }
  loadApuntes();
}

async function aceptarSugerencia111(a) {
  const cant = cantidadPositiva(a);
  const fecha = a.fecha;
  const ini = a.iniciales || '';
  const obs = a.observaciones || '';
  const txt = 'Se anotará un ingreso P/A 111 de ' + fmtEuro(parseImporte(cant))
    + ' € el ' + (a.fecha_es || fecha)
    + ' (contrapartida de la devolución en G). El apunte G no se toca.';
  if (!confirm(txt)) return;
  const s = await api('/api/apuntes', {
    method: 'POST',
    body: {
      fecha,
      cuenta: 'P',
      origen: 'A',
      iniciales: ini,
      concepto_codigo: '111',
      observaciones: obs,
      cantidad: cant,
    },
  });
  if (!s.ok) return alert(s.error);
  loadApuntes();
}

function agruparPorFecha(apuntes) {
  const grupos = [];
  apuntes.forEach((a) => {
    const f = a.fecha || '';
    if (!grupos.length || grupos[grupos.length - 1].fecha !== f) {
      grupos.push({ fecha: f, fecha_es: a.fecha_es || f, apuntes: [] });
    }
    grupos[grupos.length - 1].apuntes.push(a);
  });
  return grupos;
}

function filaApunte(a, efecto, conEfecto) {
  const tr = document.createElement('tr');
  if (a.es_cierre) tr.classList.add('cierre');
  const efectoTd = conEfecto
    ? `<td class="num">${efecto === 0 ? '' : esc(fmtEuro(efecto))}</td>`
    : '';
  tr.innerHTML = `<td>${fechaCelda(a)}</td><td>${esc(a.cuenta)}</td><td>${esc(a.origen)}</td>
    <td>${esc(a.iniciales || '')}</td><td>${esc(a.concepto_codigo)}</td>
    <td>${esc(a.observaciones || '')}</td><td class="num">${esc(a.cantidad_es)}</td>
    ${efectoTd}
    <td class="col-acc">${accionesApunteHtml(a)}</td>`;
  enlazarAccionesApunte(tr, a, loadApuntes);
  return tr;
}

async function loadApuntes() {
  const form = document.getElementById('filtros');
  const q = new URLSearchParams(formObj(form));
  const diagnostic = modoCuadreA(form);
  document.getElementById('apuntes-cuadre-ayuda').hidden = !diagnostic;
  document.querySelectorAll('.col-efecto').forEach((el) => { el.hidden = !diagnostic; });

  const r = await api('/api/apuntes?' + q.toString());
  const tb = document.querySelector('#tabla-apuntes tbody');
  tb.innerHTML = '';

  let naturalezas = {};
  let natG = {};
  let apuntesG = [];
  if (diagnostic) {
    const fd = formObj(form);
    const qG = new URLSearchParams({
      cuenta: 'G',
      iniciales: String(fd.iniciales || '').trim(),
    });
    if (fd.desde) qG.set('desde', fd.desde);
    if (fd.hasta) qG.set('hasta', fd.hasta);
    const [cP, cG, rG] = await Promise.all([
      api('/api/conceptos?cuenta=P'),
      api('/api/conceptos?cuenta=G'),
      api('/api/apuntes?' + qG.toString()),
    ]);
    (cP.conceptos || []).forEach((x) => { naturalezas[x.codigo] = x.naturaleza; });
    (cG.conceptos || []).forEach((x) => { natG[x.codigo] = x.naturaleza; });
    apuntesG = rG.apuntes || [];
  }

  const apuntes = r.apuntes || [];
  const totalEl = document.getElementById('apuntes-cuadre-total');

  if (!diagnostic) {
    totalEl.hidden = true;
    apuntes.forEach((a) => tb.appendChild(filaApunte(a, 0, false)));
    return;
  }

  let total = 0;
  let nFail = 0;
  agruparPorFecha(apuntes).forEach((g) => {
    let saldoDia = 0;
    g.apuntes.forEach((a) => { saldoDia += signedImporte(a, naturalezas); });
    total += saldoDia;
    const ok = Math.abs(saldoDia) < 0.005;
    if (!ok) nFail += 1;
    const cand = ok ? null : candidatoSugerencia(saldoDia, g.fecha, apuntesG, natG);
    const cab = document.createElement('tr');
    cab.className = 'apuntes-dia ' + (ok ? 'apuntes-dia-ok' : 'apuntes-dia-fail');
    const td = document.createElement('td');
    td.colSpan = 9;
    td.innerHTML = `<strong>${esc(g.fecha_es)}</strong>
      · saldo del día <span class="num">${esc(fmtEuro(saldoDia))}</span>
      ${ok ? '<span class="ok">cuadra</span>' : '<span class="error">no cuadra</span>'}`;
    if (cand) {
      const sug = document.createElement('span');
      sug.className = 'apuntes-dia-sug';
      const txt = document.createElement('span');
      txt.textContent = mensajeSugerencia(saldoDia, cand, natG) + ' ';
      sug.appendChild(txt);
      const ver = document.createElement('a');
      ver.href = urlVerG(cand, g.fecha);
      ver.textContent = 'Ver en G';
      sug.appendChild(ver);
      if (sePuedeAceptar21(saldoDia, cand, natG)) {
        sug.appendChild(document.createTextNode(' · '));
        const acc = document.createElement('button');
        acc.type = 'button';
        acc.textContent = 'Aceptar (P/21 y G/11)';
        acc.onclick = () => aceptarSugerencia21(cand);
        sug.appendChild(acc);
      } else if (sePuedeAceptar111(saldoDia, cand, natG)) {
        sug.appendChild(document.createTextNode(' · '));
        const acc = document.createElement('button');
        acc.type = 'button';
        acc.textContent = 'Aceptar (ingreso P/111)';
        acc.onclick = () => aceptarSugerencia111(cand);
        sug.appendChild(acc);
      }
      td.appendChild(sug);
    }
    cab.appendChild(td);
    tb.appendChild(cab);
    g.apuntes.forEach((a) => tb.appendChild(filaApunte(a, signedImporte(a, naturalezas), true)));
  });
  totalEl.hidden = false;
  const okTotal = Math.abs(total) < 0.005;
  totalEl.className = 'apuntes-cuadre-total ' + (okTotal ? 'ok' : 'error');
  totalEl.textContent = 'Saldo A de ' + String(formObj(form).iniciales).trim()
    + ': ' + fmtEuro(total)
    + (okTotal ? ' (cuadrado).' : ' · ' + nFail + ' fecha(s) sin cuadrar.');
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('filtros');
  aplicarFiltrosUrl(form);
  mostrarVolver();
  form.onsubmit = (ev) => {
    ev.preventDefault();
    history.replaceState(null, '', '/apuntes?' + queryConFrom(form).toString());
    loadApuntes();
  };
  loadApuntes();
});
function fechaCelda(a) {
  if (a.fecha_imputacion_es) {
    return esc(a.fecha_es) + ' <span class="muted">(imp. ' + esc(a.fecha_imputacion_es) + ')</span>';
  }
  return esc(a.fecha_es);
}
</script>
