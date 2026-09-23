<h1 class="print-hide"><?= _("Previsión personal") ?></h1>
<p class="muted print-hide"><?= _("Hoja 613 P por persona. acumulado(anterior): acumulado del ejercicio abierto y, entre paréntesis, la previsión ya guardada para ese ejercicio. Previsión: importes del año elegido (por defecto el ejercicio siguiente). Casilla vacía al guardar: no se guarda importe para ese concepto.") ?></p>
<p class="filters print-hide">
    <label><?= _("Persona") ?>
        <select id="sel-persona"><option value=""><?= _("Elegir…") ?></option></select>
    </label>
    <label><?= _("Año") ?>
        <select id="sel-anio-prevision" disabled></select>
    </label>
    <button type="button" id="btn-imprimir" hidden><?= _("Imprimir") ?></button>
</p>
<p id="hint-print-todos" class="muted print-hide" hidden><?= _("Impresión en A4 horizontal: dos hojas tamaño A5 por página (recortar después).") ?></p>
<p class="prevision-print-cab" id="print-cab-personal"></p>
<div id="prevision-todos-contenedor" class="prevision-todos-contenedor" hidden></div>
<form id="form-prevision-personal" hidden>
<table class="tabla-prevision tabla-prevision-personal">
    <colgroup>
        <col class="col-concepto">
        <col class="col-calculado">
        <col class="col-importe">
    </colgroup>
    <thead>
    <tr>
        <th class="col-concepto"><?= _("Concepto") ?></th>
        <th class="num col-calculado" title="<?= htmlspecialchars(_("Acumulado del ejercicio abierto (previsión guardada de ese ejercicio)"), ENT_QUOTES) ?>"><?= _("acumulado(anterior)") ?></th>
        <th class="num col-importe"><?= _("Previsión") ?></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<p class="filters print-hide">
    <button type="submit"><?= _("Guardar") ?></button>
</p>
<p id="msg-prevision-personal" class="ok print-hide" hidden><?= _("Guardado") ?></p>
<p id="msg-prevision-personal-error" class="error print-hide" hidden></p>
</form>
<script>
const I18N_PREV = {
  acum: <?= json_encode(_("acum."), JSON_UNESCAPED_UNICODE) ?>,
  puntual: <?= json_encode(_("año anterior"), JSON_UNESCAPED_UNICODE) ?>,
  errorCarga: <?= json_encode(_("No se pudo cargar la previsión"), JSON_UNESCAPED_UNICODE) ?>,
  cabecera: <?= json_encode(_("Previsión 613 P"), JSON_UNESCAPED_UNICODE) ?>,
};
const CENTRO_PREV = <?= json_encode((string) ($centroNombre ?? ''), JSON_UNESCAPED_UNICODE) ?>;

let sincAniosPrevision = false;

function prepararPaginaImpresion(todos) {
  let el = document.getElementById('prevision-print-page');
  if (!el) {
    el = document.createElement('style');
    el.id = 'prevision-print-page';
    document.head.appendChild(el);
  }
  el.textContent = todos
    ? '@page { size: A4 landscape; margin: 5mm; }'
    : '@page { size: A5 portrait; margin: 7mm; }';
}

function modoEtiqueta(modo) {
  return modo === 'puntual' ? I18N_PREV.puntual : '';
}

function fmtPrev(valorEs) {
  if (!valorEs || valorEs === '0,00' || valorEs === '-0,00') return '';
  return valorEs.endsWith(',00') ? valorEs.slice(0, -3) : valorEs;
}

function parseImporteEs(valorEs) {
  const s = String(valorEs).trim().replace(/\s/g, '');
  if (!s) return NaN;
  if (s.includes(',')) {
    return Number(s.replace(/\./g, '').replace(',', '.'));
  }
  if (/^-?\d{1,3}(\.\d{3})+$/.test(s)) {
    return Number(s.replace(/\./g, ''));
  }
  return Number(s.replace(',', '.'));
}

function fmtReferencia(l) {
  if (l.referencia_es) return l.referencia_es;
  const acum = fmtEntero(l.acumulado_es);
  const prev = fmtEntero(l.previsto_ejercicio_actual_es);
  if (!acum && !prev) return '';
  if (!prev) return acum;
  if (!acum) return '(' + prev + ')';
  return acum + ' (' + prev + ')';
}

function fmtEntero(valorEs) {
  if (!valorEs || valorEs === '0,00' || valorEs === '-0,00') return '';
  const n = parseImporteEs(valorEs);
  if (!Number.isFinite(n)) return fmtPrev(valorEs);
  const redondo = Math.round(n);
  if (redondo === 0) return '';
  return redondo.toLocaleString(secretaryLocale(), {
    useGrouping: true,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
}

/** Columna Previsión: euros enteros, sin céntimos ni separador de miles (evita confundir al guardar). */
function fmtPrevisionEs(valor) {
  if (valor === null || valor === undefined || String(valor).trim() === '') return '';
  const n = parseImporteEs(String(valor).trim());
  if (!Number.isFinite(n)) return String(valor).trim();
  const entero = Math.round(n);
  if (entero === 0) return '';
  return String(entero);
}

function etiquetaPrevisionSeleccionada() {
  const sel = document.getElementById('sel-anio-prevision');
  if (!sel || sel.disabled || !sel.value) return '';
  return sel.value;
}

function urlPrevisionPersonal(personaId) {
  let url = '/api/previsiones/personal/' + personaId;
  const etiqueta = etiquetaPrevisionSeleccionada();
  if (etiqueta) url += '?etiqueta=' + encodeURIComponent(etiqueta);
  return url;
}

function listaEtiquetas(r) {
  const etiquetas = [];
  const push = (valor) => {
    const et = String(valor || '').trim();
    if (et && !etiquetas.includes(et)) etiquetas.push(et);
  };
  const raw = (r && (r.etiquetas || r.anios_disponibles)) || [];
  (Array.isArray(raw) ? raw : Object.values(raw)).forEach(push);
  if (r) {
    push(r.etiqueta_trabajo);
    push(r.etiqueta_defecto);
    push(r.etiqueta_presupuesto);
  }
  return etiquetas;
}

function pintarEtiquetasPrevision(r, forzarDefecto) {
  const sel = document.getElementById('sel-anio-prevision');
  if (!sel) return;
  const prev = sel.value;
  const etiquetas = listaEtiquetas(r);
  [...sel.options].forEach((o) => {
    if (o.value && !etiquetas.includes(o.value)) etiquetas.push(o.value);
  });
  const defecto = r && (r.etiqueta_defecto || r.etiqueta_presupuesto || r.anio_presupuesto);
  sincAniosPrevision = true;
  sel.innerHTML = '';
  etiquetas.forEach((et) => {
    const o = document.createElement('option');
    o.value = et;
    o.textContent = et;
    sel.appendChild(o);
  });
  if (forzarDefecto && defecto && etiquetas.includes(String(defecto))) sel.value = String(defecto);
  else if (prev && etiquetas.includes(prev)) sel.value = prev;
  else if (defecto && etiquetas.includes(String(defecto))) sel.value = String(defecto);
  else if (etiquetas.length) sel.value = etiquetas[etiquetas.length - 1];
  sel.disabled = etiquetas.length === 0;
  sincAniosPrevision = false;
}

async function cargarOpcionesPrevision() {
  const r = await api('/api/previsiones/personal/opciones');
  if (!r.ok) return;
  pintarEtiquetasPrevision(r, true);
}

function filasDeRespuesta(r) {
  return r.filas && r.filas.length
    ? r.filas
    : (r.lineas || []).map((l) => Object.assign({ tipo: 'hijo', editable: true }, l));
}

function cabeceraPersonal(r) {
  const nom = (r.persona && (r.persona.nombre || r.persona.iniciales)) || '';
  const anio = r.etiqueta_presupuesto || r.anio_presupuesto || '';
  return [I18N_PREV.cabecera, nom, CENTRO_PREV, anio].filter(Boolean).join(' — ');
}

function appendFilasPrevision(tb, filas, editable) {
  filas.forEach((l) => {
    const tr = document.createElement('tr');
    tr.className = 'prevision-' + (l.tipo || 'hijo');
    const etq = l.codigo && l.tipo === 'hijo'
      ? esc(l.codigo) + ' ' + esc(l.etiqueta)
      : esc(l.etiqueta);
    const nota = modoEtiqueta(l.modo);
    const detalle = nota
      ? '<div class="muted detalle-acum">' + esc(nota) + '</div>'
      : '';
    const tdConc = document.createElement('td');
    tdConc.className = 'col-concepto';
    tdConc.innerHTML = etq + detalle;
    const tdCalc = document.createElement('td');
    tdCalc.className = 'num col-calculado';
    tdCalc.textContent = fmtReferencia(l);
    const tdImp = document.createElement('td');
    tdImp.className = 'num col-importe';
    if (editable && l.editable && l.codigo) {
      const inp = document.createElement('input');
      inp.name = l.codigo;
      inp.className = 'num print-hide';
      inp.value = l.previsto_es ? fmtPrevisionEs(l.previsto_es) : '';
      inp.dataset.calculado = l.calculado_es || '0,00';
      inp.addEventListener('blur', () => {
        if (inp.value.trim()) inp.value = fmtPrevisionEs(inp.value);
      });
      const impPrint = document.createElement('span');
      impPrint.className = 'prevision-print-imp';
      impPrint.textContent = fmtPrevisionEs(l.previsto_es || l.calculado_es);
      tdImp.appendChild(inp);
      tdImp.appendChild(impPrint);
    } else {
      tdImp.textContent = editable ? fmtPrevisionEs(l.previsto_es) : '';
    }
    tr.appendChild(tdConc);
    tr.appendChild(tdCalc);
    tr.appendChild(tdImp);
    tb.appendChild(tr);
  });
}

function crearTablaPrevision() {
  const table = document.createElement('table');
  table.className = 'tabla-prevision tabla-prevision-personal';
  const titCalc = <?= json_encode(_("Acumulado del ejercicio abierto (previsión guardada de ese ejercicio)"), JSON_UNESCAPED_UNICODE) ?>;
  table.innerHTML =
    '<colgroup><col class="col-concepto"><col class="col-calculado"><col class="col-importe"></colgroup>'
    + '<thead><tr>'
    + '<th class="col-concepto">' + esc(<?= json_encode(_("Concepto"), JSON_UNESCAPED_UNICODE) ?>) + '</th>'
    + '<th class="num col-calculado" title="' + esc(titCalc) + '">' + esc(<?= json_encode(_("acumulado(anterior)"), JSON_UNESCAPED_UNICODE) ?>) + '</th>'
    + '<th class="num col-importe">' + esc(<?= json_encode(_("Previsión"), JSON_UNESCAPED_UNICODE) ?>) + '</th>'
    + '</tr></thead>';
  table.appendChild(document.createElement('tbody'));
  return table;
}

function pintarHojaPersonal(r) {
  document.body.classList.remove('prevision-personal-todos');
  document.getElementById('hint-print-todos').hidden = true;
  prepararPaginaImpresion(false);
  document.getElementById('prevision-todos-contenedor').hidden = true;
  document.getElementById('prevision-todos-contenedor').innerHTML = '';
  const tb = document.querySelector('#form-prevision-personal tbody');
  tb.innerHTML = '';
  appendFilasPrevision(tb, filasDeRespuesta(r), true);
  document.getElementById('form-prevision-personal').hidden = false;
  document.getElementById('btn-imprimir').hidden = false;
  document.getElementById('print-cab-personal').textContent = cabeceraPersonal(r);
  pintarEtiquetasPrevision(r, false);
}

function bloqueHojaSoloLectura(r) {
  const bloque = document.createElement('div');
  bloque.className = 'prevision-hoja-mini';
  const cab = document.createElement('p');
  cab.className = 'prevision-print-cab';
  cab.textContent = cabeceraPersonal(r);
  const table = crearTablaPrevision();
  appendFilasPrevision(table.querySelector('tbody'), filasDeRespuesta(r), false);
  bloque.appendChild(cab);
  bloque.appendChild(table);
  return bloque;
}

async function cargarTodos() {
  document.getElementById('msg-prevision-personal').hidden = true;
  document.body.classList.add('prevision-personal-todos');
  document.getElementById('hint-print-todos').hidden = false;
  prepararPaginaImpresion(true);
  document.getElementById('form-prevision-personal').hidden = true;
  document.getElementById('print-cab-personal').textContent = '';
  const cont = document.getElementById('prevision-todos-contenedor');
  cont.hidden = false;
  cont.innerHTML = '';
  document.getElementById('btn-imprimir').hidden = true;
  const sel = document.getElementById('sel-persona');
  const ids = [...sel.options].map((o) => o.value).filter((v) => v && v !== 'todos');
  if (!ids.length) return;
  const respuestas = await Promise.all(ids.map((id) => api(urlPrevisionPersonal(id))));
  for (const r of respuestas) {
    if (!r.ok) return alert(r.error || I18N_PREV.errorCarga);
  }
  if (respuestas.length) pintarEtiquetasPrevision(respuestas[0], false);
  respuestas.forEach((r) => cont.appendChild(bloqueHojaSoloLectura(r)));
  document.getElementById('btn-imprimir').hidden = false;
}

async function cargarPersona(id) {
  document.getElementById('msg-prevision-personal').hidden = true;
  const msgErr = document.getElementById('msg-prevision-personal-error');
  if (msgErr) msgErr.hidden = true;
  if (!id) {
    document.body.classList.remove('prevision-personal-todos');
    document.getElementById('hint-print-todos').hidden = true;
    prepararPaginaImpresion(false);
    document.getElementById('form-prevision-personal').hidden = true;
    document.getElementById('prevision-todos-contenedor').hidden = true;
    document.getElementById('prevision-todos-contenedor').innerHTML = '';
    document.getElementById('btn-imprimir').hidden = true;
    document.getElementById('print-cab-personal').textContent = '';
    return;
  }
  if (id === 'todos') return cargarTodos();
  const r = await api(urlPrevisionPersonal(id));
  if (!r.ok) {
    const txt = r.error || I18N_PREV.errorCarga;
    if (msgErr) {
      msgErr.textContent = txt;
      msgErr.hidden = false;
    }
    return alert(txt);
  }
  pintarHojaPersonal(r);
}

document.addEventListener('DOMContentLoaded', async () => {
  document.body.classList.add('prevision-personal-hoja');
  prepararPaginaImpresion(false);
  await cargarOpcionesPrevision();
  const pers = await api('/api/personas');
  if (!pers.ok) return alert(pers.error || I18N_PREV.errorCarga);
  const sel = document.getElementById('sel-persona');
  (pers.personas || []).forEach((p) => {
    const o = document.createElement('option');
    o.value = p.id;
    o.textContent = (p.nombre_completo || p.nombre) + ' (' + p.iniciales + ')';
    sel.appendChild(o);
  });
  const oTodos = document.createElement('option');
  oTodos.value = 'todos';
  oTodos.textContent = <?= json_encode(_("Todos (imprimir)"), JSON_UNESCAPED_UNICODE) ?>;
  sel.appendChild(oTodos);
  const params = new URLSearchParams(location.search);
  if (params.get('persona')) sel.value = params.get('persona');
  sel.addEventListener('change', () => cargarPersona(sel.value));
  document.getElementById('sel-anio-prevision').addEventListener('change', () => {
    if (sincAniosPrevision) return;
    const personaId = sel.value;
    if (personaId && personaId !== 'todos') cargarPersona(personaId);
  });
  if (sel.value) await cargarPersona(sel.value);

  document.getElementById('btn-imprimir').addEventListener('click', () => {
    prepararPaginaImpresion(sel.value === 'todos');
    if (sel.value !== 'todos') {
      document.querySelectorAll('#form-prevision-personal input[name]').forEach((i) => {
        const span = i.parentElement && i.parentElement.querySelector('.prevision-print-imp');
        if (!span) return;
        const v = i.value.trim() ? i.value : (i.dataset.calculado || '');
        span.textContent = fmtPrevisionEs(v);
      });
    }
    window.print();
  });

  document.getElementById('form-prevision-personal').onsubmit = async (ev) => {
    ev.preventDefault();
    const id = sel.value;
    if (!id || id === 'todos') return;
    const lineas = {};
    ev.target.querySelectorAll('input[name]').forEach((i) => {
      lineas[i.name] = i.value.trim() ? fmtPrevisionEs(i.value) : '';
    });
    const body = { lineas };
    const etiqueta = etiquetaPrevisionSeleccionada();
    if (etiqueta) body.etiqueta = etiqueta;
    const s = await api('/api/previsiones/personal/' + id, { method: 'POST', body });
    document.getElementById('msg-prevision-personal').hidden = !s.ok;
    if (!s.ok) return alert(s.error);
    pintarHojaPersonal(s);
  };
});
</script>
