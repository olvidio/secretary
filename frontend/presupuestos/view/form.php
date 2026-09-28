<?php $cuenta = $cuentaPresupuesto ?? 'P'; $esCentroSg = !empty($esCentroSg); ?>
<h1><?= sprintf(_("Presupuesto %s"), htmlspecialchars($cuenta, ENT_QUOTES)) ?></h1>
<p class="muted"><?= _("Celdas de previsto anual. El 613 prorratea × meses / 12.") ?></p>
<?php if ($esCentroSg && $cuenta === 'G'): ?>
<p class="presu-num-s">
    <label><?= _("Nº de s del centro") ?>
        <input id="num-s" inputmode="numeric" class="num" value="0" maxlength="4" size="4">
    </label>
</p>
<?php endif; ?>
<?php if ($esCentroSg && $cuenta === 'G'): ?>
<section id="sec-destinos">
    <h2><?= _("Destinos del centro") ?></h2>
    <p class="muted"><?= _("Como las partidas del capítulo VII: cada centro añade las suyas, del 42 al 54, con su nombre. El 41, Necesidades generales, es fijo y sale en la lista de abajo.") ?></p>
    <table id="tabla-destinos">
        <thead>
        <tr><th><?= _("Código") ?></th><th><?= _("Nombre") ?></th><th></th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <p class="grid-form" style="margin-top:.5rem">
        <button type="button" id="btn-add-destino"><?= _("Añadir destino") ?></button>
        <button type="button" id="btn-save-destinos"><?= _("Guardar destinos") ?></button>
    </p>
    <p class="ok" id="msg-destinos" hidden><?= _("Destinos guardados.") ?></p>
</section>
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
const ES_CENTRO_SG = <?= ($esCentroSg && $cuenta === 'G') ? 'true' : 'false' ?>;
const I18N_DESTINOS = {
  quitar: <?= json_encode(_("Quitar"), JSON_UNESCAPED_UNICODE) ?>,
  guardados: <?= json_encode(_("Destinos guardados."), JSON_UNESCAPED_UNICODE) ?>,
};

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

function filaDestino(p = {}) {
  const tr = document.createElement('tr');
  tr.innerHTML =
    '<td><input name="codigo" required pattern="(4[2-9]|5[0-4])" maxlength="2" value="' + esc(p.codigo || '') + '"></td>' +
    '<td><input name="etiqueta" required value="' + esc(p.etiqueta || '') + '"></td>' +
    '<td><button type="button" class="btn-quitar">' + esc(I18N_DESTINOS.quitar) + '</button></td>';
  tr.querySelector('.btn-quitar')?.addEventListener('click', () => {
    tr.remove();
    refrescarBotonDestino();
  });
  return tr;
}

function destinosDelFormulario() {
  return [...document.querySelectorAll('#tabla-destinos tbody tr')].map((tr) => ({
    codigo: tr.querySelector('[name=codigo]').value.trim(),
    etiqueta: tr.querySelector('[name=etiqueta]').value.trim(),
  }));
}

function sugerirCodigoDestino() {
  const usados = new Set(destinosDelFormulario().map((p) => p.codigo));
  for (let n = 42; n <= 54; n++) {
    const c = String(n);
    if (!usados.has(c)) return c;
  }
  return '';
}

function refrescarBotonDestino() {
  const btn = document.getElementById('btn-add-destino');
  if (btn) btn.hidden = sugerirCodigoDestino() === '';
}

async function cargarDestinos() {
  const r = await api('/api/destinos-sg');
  if (!r.ok) return alert(r.error || <?= json_encode(_("No se pudieron cargar los destinos"), JSON_UNESCAPED_UNICODE) ?>);
  const tb = document.querySelector('#tabla-destinos tbody');
  tb.innerHTML = '';
  (r.partidas || []).forEach((p) => tb.appendChild(filaDestino(p)));
  refrescarBotonDestino();
}

async function cargarPresupuesto() {
  const r = await api('/api/presupuestos/' + CUENTA);
  if (!r.ok) return alert(r.error || <?= json_encode(_("No se pudo cargar el presupuesto"), JSON_UNESCAPED_UNICODE) ?>);
  pintarLineasPresu(r.lineas);
  const numS = document.getElementById('num-s');
  if (numS && r.num_s != null) numS.value = r.num_s;
}

document.addEventListener('DOMContentLoaded', async () => {
  if (ES_CENTRO_SG) {
    await cargarDestinos();
    document.getElementById('btn-add-destino')?.addEventListener('click', () => {
      const codigo = sugerirCodigoDestino();
      if (!codigo) return;
      const tb = document.querySelector('#tabla-destinos tbody');
      tb.appendChild(filaDestino({ codigo, etiqueta: '' }));
      tb.lastElementChild?.querySelector('[name=etiqueta]')?.focus();
      refrescarBotonDestino();
    });
    document.getElementById('btn-save-destinos')?.addEventListener('click', async () => {
      const msg = document.getElementById('msg-destinos');
      msg.hidden = true;
      const s = await api('/api/destinos-sg', { method: 'POST', body: { partidas: destinosDelFormulario() } });
      if (!s.ok) return alert(s.error);
      msg.hidden = false;
      msg.textContent = I18N_DESTINOS.guardados;
      await cargarDestinos();
      await cargarPresupuesto();
    });
  }
  await cargarPresupuesto();
  document.getElementById('form-presu').onsubmit = async (ev) => {
    ev.preventDefault();
    const lineas = {};
    ev.target.querySelectorAll('input[name]').forEach((i) => {
      lineas[i.name] = i.value.trim() ? fmtImporteEs(i.value) : '';
    });
    const body = { lineas };
    const numS = document.getElementById('num-s');
    if (numS) body.num_s = numS.value.trim();
    const s = await api('/api/presupuestos/' + CUENTA, { method: 'POST', body });
    document.getElementById('msg').hidden = !s.ok;
    if (!s.ok) return alert(s.error);
    pintarLineasPresu(s.lineas);
  };
});
</script>
