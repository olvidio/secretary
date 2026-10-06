<?php $cuenta = $cuentaPresupuesto ?? 'P'; $esCentroSg = !empty($esCentroSg); ?>
<h1><?= sprintf(_("Presupuesto %s"), htmlspecialchars($cuenta, ENT_QUOTES)) ?></h1>
<p class="muted"><?= _("Celdas de previsto anual. El 613 prorratea × meses / 12.") ?></p>
<p class="filters print-hide">
    <label><?= _("Año") ?>
        <select id="sel-anio-presupuesto" disabled></select>
    </label>
</p>
<?php if ($esCentroSg && $cuenta === 'G'): ?>
<p class="muted"><?= _("Los destinos del 42 al 54 se nombran en") ?>
    <a href="/conceptos-g"><?= _("Conceptos") ?></a><?= _(", en Plan y ejercicio.") ?></p>
<?php endif; ?>
<?php if ($cuenta === 'P'): ?>
<p class="muted"><?= _("Para generar las cifras del libro P, use") ?>
    <a href="/prevision-personal"><?= _("Previsión personal") ?></a>
    <?= _("y") ?>
    <a href="/prevision"><?= _("Previsión") ?></a>.</p>
<?php endif; ?>
<form id="form-presu">
<table>
    <thead><tr><th><?= _("Concepto") ?></th><th class="num"><?= _("Previsto") ?></th></tr></thead>
    <tbody></tbody>
</table>
<button type="submit"><?= _("Guardar") ?></button>
<p id="msg" class="ok" hidden><?= _("Guardado") ?></p>
</form>
<script>
const CUENTA = <?= json_encode($cuenta) ?>;

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

function pintarLineasPresu(lineas) {
  const tb = document.querySelector('#form-presu tbody');
  tb.innerHTML = '';
  (lineas || []).forEach((l) => {
    const tr = document.createElement('tr');
    const inp = document.createElement('input');
    inp.name = l.concepto_codigo;
    inp.value = fmtImporteEs(l.previsto_es ?? l.previsto);
    inp.className = 'num';
    inp.addEventListener('blur', () => {
      if (inp.value.trim()) inp.value = fmtImporteEs(inp.value);
    });
    tr.innerHTML = `<td>${esc(l.concepto_codigo)} ${esc(l.nombre || '')}</td>`;
    const td = document.createElement('td');
    td.className = 'num';
    td.appendChild(inp);
    tr.appendChild(td);
    tb.appendChild(tr);
  });
}

async function cargarPresupuesto(forzarDefecto) {
  let url = '/api/presupuestos/' + CUENTA;
  if (!forzarDefecto) {
    const etiqueta = etiquetaPresupuestoSeleccionada();
    if (etiqueta) url += '?etiqueta=' + encodeURIComponent(etiqueta);
  }
  const r = await api(url);
  if (!r.ok) return alert(r.error || <?= json_encode(_("No se pudo cargar el presupuesto"), JSON_UNESCAPED_UNICODE) ?>);
  rellenarSelectAniosPresu(r, !!forzarDefecto);
  pintarLineasPresu(r.lineas);
}

document.addEventListener('DOMContentLoaded', async () => {
  await cargarPresupuesto(true);
  document.getElementById('sel-anio-presupuesto').addEventListener('change', () => cargarPresupuesto(false));
  document.getElementById('form-presu').onsubmit = async (ev) => {
    ev.preventDefault();
    const lineas = {};
    ev.target.querySelectorAll('input[name]').forEach((i) => {
      lineas[i.name] = i.value.trim() ? fmtImporteEs(i.value) : '';
    });
    const body = { lineas };
    const etiqueta = etiquetaPresupuestoSeleccionada();
    if (etiqueta) body.etiqueta = etiqueta;
    const s = await api('/api/presupuestos/' + CUENTA, { method: 'POST', body });
    document.getElementById('msg').hidden = !s.ok;
    if (!s.ok) return alert(s.error);
    rellenarSelectAniosPresu(s, false);
    pintarLineasPresu(s.lineas);
  };
});
</script>
