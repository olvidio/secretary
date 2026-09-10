function esRemesaApunte(a) {
  return !!(a.es_remesa || (a.observaciones && String(a.observaciones).indexOf('Remesa ') === 0));
}

function accionesApunteHtml(a) {
  if (esRemesaApunte(a)) return '';
  return '<button type="button" data-edit>Editar</button> '
    + '<button type="button" data-del>Borrar</button>';
}

function enlazarAccionesApunte(tr, a, onSaved) {
  const ed = tr.querySelector('[data-edit]');
  if (ed) ed.onclick = () => abrirEdicionApunte(a, onSaved);
  const del = tr.querySelector('[data-del]');
  if (del) {
    del.onclick = async () => {
      if (!confirm('¿Borrar apunte?')) return;
      const s = await api('/api/apuntes/' + a.id, { method: 'DELETE' });
      if (!s.ok) return alert(s.error || 'No se pudo borrar');
      onSaved();
    };
  }
}

function normCantidad(raw) {
  const s = String(raw ?? '').trim().replace(/\s/g, '').replace(',', '.');
  const n = Number(s);
  return Number.isFinite(n) ? n.toFixed(2) : s;
}

function snapshotCampos(a) {
  return {
    fecha: a.fecha || '',
    fecha_imputacion: a.fecha_imputacion || '',
    cuenta: a.cuenta || '',
    origen: a.origen || '',
    iniciales: String(a.iniciales || '').trim(),
    concepto_codigo: String(a.concepto_codigo || '').trim(),
    cantidad: normCantidad(a.cantidad),
  };
}

function snapshotForm(form) {
  return snapshotCampos({
    fecha: form.fecha.value,
    fecha_imputacion: form.fecha_imputacion.value,
    cuenta: form.cuenta.value,
    origen: form.origen.value,
    iniciales: form.iniciales.value,
    concepto_codigo: form.concepto_codigo.value,
    cantidad: form.cantidad.value,
  });
}

function camposDistintos(a, b) {
  return JSON.stringify(a) !== JSON.stringify(b);
}

let dlgApunteEstado = { original: null, onSaved: null, personas: null, conceptos: { P: null, G: null } };

function asegurarDialogoApunte() {
  let dlg = document.getElementById('dlg-apunte');
  if (dlg) return dlg;
  dlg = document.createElement('div');
  dlg.id = 'dlg-apunte';
  dlg.className = 'dlg-apunte';
  dlg.hidden = true;
  dlg.innerHTML =
    '<form id="form-editar-apunte" class="dlg-apunte-box">'
    + '<h2>Editar apunte</h2>'
    + '<div class="grid-form">'
    + '<label>Fecha <input name="fecha" type="date" required></label>'
    + '<label>F. imputación <input name="fecha_imputacion" type="date"></label>'
    + '<label>P/G <select name="cuenta"><option>P</option><option>G</option></select></label>'
    + '<label>A/B/C <select name="origen"><option>A</option><option>B</option><option>C</option></select></label>'
    + '<label>Iniciales <select name="iniciales"><option value=""></option></select></label>'
    + '<label>Concepto <select name="concepto_codigo" required></select></label>'
    + '<label class="dlg-apunte-obs">Observaciones <input name="observaciones" autocomplete="off"></label>'
    + '<label>Cantidad <input name="cantidad" required inputmode="decimal"></label>'
    + '</div>'
    + '<p id="dlg-apunte-err" class="error" hidden></p>'
    + '<div class="dlg-apunte-acciones">'
    + '<button type="submit">Guardar</button>'
    + '<button type="button" id="dlg-apunte-cancelar">Cancelar</button>'
    + '</div></form>';
  document.body.appendChild(dlg);
  const form = dlg.querySelector('#form-editar-apunte');
  dlg.querySelector('#dlg-apunte-cancelar').onclick = cerrarEdicionApunte;
  dlg.addEventListener('click', (ev) => { if (ev.target === dlg) cerrarEdicionApunte(); });
  form.cuenta.addEventListener('change', () => pintarConceptosEdicion(form.cuenta.value, form.concepto_codigo.value));
  form.addEventListener('submit', onGuardarEdicionApunte);
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape' && !dlg.hidden) {
      ev.preventDefault();
      cerrarEdicionApunte();
    }
  });
  return dlg;
}

async function cargarPersonasEdicion(sel) {
  if (!dlgApunteEstado.personas) {
    const r = await api('/api/personas');
    dlgApunteEstado.personas = r.personas || [];
  }
  const actual = sel.value;
  sel.innerHTML = '<option value=""></option>';
  dlgApunteEstado.personas.forEach((p) => {
    const o = document.createElement('option');
    o.value = p.iniciales;
    o.textContent = p.iniciales + ' — ' + p.nombre_completo;
    sel.appendChild(o);
  });
  sel.value = actual;
}

async function pintarConceptosEdicion(cuenta, seleccionado) {
  const sel = document.querySelector('#form-editar-apunte [name=concepto_codigo]');
  if (!sel) return;
  if (!dlgApunteEstado.conceptos[cuenta]) {
    const r = await api('/api/conceptos?cuenta=' + encodeURIComponent(cuenta));
    dlgApunteEstado.conceptos[cuenta] = r.conceptos || [];
  }
  sel.innerHTML = '';
  dlgApunteEstado.conceptos[cuenta].forEach((c) => {
    const o = document.createElement('option');
    o.value = c.codigo;
    o.textContent = c.etiqueta;
    sel.appendChild(o);
  });
  if (seleccionado) sel.value = seleccionado;
}

async function abrirEdicionApunte(a, onSaved) {
  const dlg = asegurarDialogoApunte();
  const form = dlg.querySelector('#form-editar-apunte');
  const err = dlg.querySelector('#dlg-apunte-err');
  err.hidden = true;
  dlgApunteEstado.original = a;
  dlgApunteEstado.onSaved = onSaved;
  form.fecha.value = a.fecha || '';
  form.fecha_imputacion.value = a.fecha_imputacion || '';
  form.cuenta.value = a.cuenta || 'P';
  form.origen.value = a.origen || 'A';
  form.cantidad.value = a.cantidad || '';
  form.observaciones.value = a.observaciones || '';
  await cargarPersonasEdicion(form.iniciales);
  form.iniciales.value = a.iniciales || '';
  await pintarConceptosEdicion(form.cuenta.value, a.concepto_codigo);
  dlg.hidden = false;
  setTimeout(() => {
    const obs = form.observaciones;
    obs.focus();
    if (typeof obs.select === 'function') obs.select();
  }, 0);
}

function cerrarEdicionApunte() {
  const dlg = document.getElementById('dlg-apunte');
  if (dlg) dlg.hidden = true;
  dlgApunteEstado.original = null;
  dlgApunteEstado.onSaved = null;
}

async function onGuardarEdicionApunte(ev) {
  ev.preventDefault();
  const form = ev.target;
  const original = dlgApunteEstado.original;
  const err = document.getElementById('dlg-apunte-err');
  err.hidden = true;
  if (!original) return;
  const obsOrig = String(original.observaciones || '');
  const obsNueva = form.observaciones.value;
  const otros = camposDistintos(snapshotCampos(original), snapshotForm(form));
  if (!otros && obsNueva === obsOrig) {
    cerrarEdicionApunte();
    return;
  }
  if (otros && !confirm('Ha modificado algún campo además de observaciones. ¿Guardar los cambios?')) {
    return;
  }
  const body = {
    fecha: form.fecha.value,
    fecha_imputacion: form.fecha_imputacion.value,
    cuenta: form.cuenta.value,
    origen: form.origen.value,
    iniciales: form.iniciales.value,
    concepto_codigo: form.concepto_codigo.value,
    observaciones: obsNueva,
    cantidad: form.cantidad.value,
  };
  const s = await api('/api/apuntes/' + original.id, { method: 'PUT', body });
  if (!s.ok) {
    err.hidden = false;
    err.textContent = s.error || 'No se pudo guardar';
    return;
  }
  const onSaved = dlgApunteEstado.onSaved;
  cerrarEdicionApunte();
  if (onSaved) onSaved();
}
