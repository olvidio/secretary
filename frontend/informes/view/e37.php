<style>
@page { size: A4 landscape; margin: 8mm; }
</style>
<h1 class="print-hide"><?= _("Cuentas personales (E37)") ?></h1>
<p class="filters print-hide">
    <label><?= _("Persona") ?>
        <select name="iniciales"><option value=""><?= _("Todas") ?></option></select>
    </label>
    <button type="button" id="btn-imprimir" hidden><?= _("Imprimir") ?></button>
</p>

<table id="tabla-e37" class="print-hide">
    <thead>
    <tr><th><?= _("Fecha") ?></th><th><?= _("Inic.") ?></th><th><?= _("Concepto") ?></th><th><?= _("Observaciones") ?></th><th class="num"><?= _("Cantidad") ?></th></tr>
    </thead>
    <tbody></tbody>
</table>
<div id="tot" class="print-hide"></div>

<div id="hoja-e37" class="informe-e37-wrap" hidden>
    <article class="informe-e37" id="informe-e37">
        <header class="informe-e37-cab">
            <span><?= _("fecha cierre:") ?></span>
            <span id="e37-fecha-cierre"></span>
        </header>
        <div class="informe-e37-scroll">
            <table class="informe-e37-tabla">
                <thead>
                <tr class="informe-e37-codigos" id="e37-codigos"></tr>
                <tr class="informe-e37-etiquetas" id="e37-etiquetas"></tr>
                </thead>
                <tbody id="e37-body"></tbody>
                <tfoot id="e37-foot"></tfoot>
            </table>
        </div>
        <p id="e37-aviso-saldo" class="aviso-saldo-cc" hidden></p>
    </article>
</div>

<script>
function fechaCorta(iso) {
  if (!iso) return '';
  const [y, m, d] = String(iso).split('-');
  return d && m && y ? `${d}-${m}-${y.slice(2)}` : iso;
}

function fechaCierreEs(iso) {
  if (!iso) return '';
  const [y, m, d] = String(iso).split('-');
  return d && m && y ? `${d}-${m}-${y}` : iso;
}

/** Como el Excel: 1.500 si es entero; 55,06 si hay céntimos; vacío si cero. */
function fmtHoja(valorEs) {
  if (!valorEs || valorEs === '0,00' || valorEs === '-0,00') return '';
  return valorEs.endsWith(',00') ? valorEs.slice(0, -3) : valorEs;
}

function celdaNum(valorEs) {
  const t = fmtHoja(valorEs);
  return `<td class="num">${t ? esc(t) : ''}</td>`;
}

document.addEventListener('DOMContentLoaded', async () => {
  const pers = await api('/api/personas');
  if (!pers.ok) return alert(pers.error || <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>);
  const sel = document.querySelector('[name=iniciales]');
  (pers.personas || []).forEach(p => {
    const o = document.createElement('option');
    o.value = p.iniciales;
    o.textContent = p.nombre_completo + ' (' + p.iniciales + ')';
    sel.appendChild(o);
  });

  const params = new URLSearchParams(location.search);
  if (params.get('iniciales')) sel.value = params.get('iniciales');

  const lista = document.getElementById('tabla-e37');
  const tot = document.getElementById('tot');
  const hoja = document.getElementById('hoja-e37');
  const btnPrint = document.getElementById('btn-imprimir');

  function pintarLista(r) {
    hoja.hidden = true;
    document.body.classList.remove('e37-hoja');
    lista.hidden = false;
    tot.hidden = false;
    btnPrint.hidden = true;
    const tb = lista.querySelector('tbody');
    tb.innerHTML = '';
    (r.apuntes || []).forEach(a => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${esc(a.fecha_es)}</td><td>${esc(a.iniciales || '')}</td>
        <td>${esc(a.concepto_codigo)}</td><td>${esc(a.observaciones || '')}</td>
        <td class="num">${esc(a.cantidad_es)}</td>`;
      tb.appendChild(tr);
    });
    tot.innerHTML = '';
  }

  function pintarHoja(r) {
    lista.hidden = true;
    tot.hidden = true;
    hoja.hidden = false;
    document.body.classList.add('e37-hoja');
    btnPrint.hidden = false;

    const cols = r.columnas || [];
    document.getElementById('e37-fecha-cierre').textContent = fechaCierreEs(r.config && r.config.fecha_cierre);

    const trCod = document.getElementById('e37-codigos');
    trCod.innerHTML = '<th></th><th></th>' + cols.map(c =>
      `<th class="num">${esc(c.codigo || '')}</th>`
    ).join('');

    const trEt = document.getElementById('e37-etiquetas');
    trEt.innerHTML = '<th><?= _("Fecha") ?></th><th><?= _("Concepto") ?></th>' + cols.map(c =>
      `<th class="num">${esc(c.etiqueta)}</th>`
    ).join('');

    const tb = document.getElementById('e37-body');
    tb.innerHTML = '';
    (r.filas || []).forEach(f => {
      const tr = document.createElement('tr');
      const celdas = cols.map(c => celdaNum(f.celdas[c.clave + '_es'])).join('');
      tr.innerHTML = `<td class="e37-fecha">${esc(fechaCorta(f.fecha))}</td>
        <td class="e37-concepto">${esc(f.concepto)}</td>${celdas}`;
      tb.appendChild(tr);
    });

    const t = r.totales || {};
    const nombre = (r.persona && r.persona.nombre) || sel.value;
    const trTot = document.createElement('tr');
    trTot.className = 'informe-e37-total';
    trTot.innerHTML = `<td colspan="2">${esc(nombre)}</td>`
      + cols.map(c => {
          const es = t[c.clave + '_es'];
          if (c.clave === 'saldo_cc' && r.aviso_saldo_cc) {
            const txt = fmtHoja(es);
            return `<td class="num saldo-cc-neg">${txt ? esc(txt) : ''}</td>`;
          }
          return celdaNum(es);
        }).join('');
    document.getElementById('e37-foot').innerHTML = '';
    document.getElementById('e37-foot').appendChild(trTot);

    const avisoEl = document.getElementById('e37-aviso-saldo');
    if (r.aviso_saldo_cc) {
      avisoEl.hidden = false;
      avisoEl.textContent = r.aviso_saldo_cc;
    } else {
      avisoEl.hidden = true;
      avisoEl.textContent = '';
    }
  }

  async function load() {
    const ini = sel.value;
    const url = '/e37' + (ini ? '?iniciales=' + encodeURIComponent(ini) : '');
    history.replaceState(null, '', url);
    const r = await api('/api/informes/e37' + (ini ? '?iniciales=' + encodeURIComponent(ini) : ''));
    if (!r.ok) return alert(r.error || <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>);
    if (ini && r.columnas) pintarHoja(r);
    else pintarLista(r);
  }

  sel.onchange = () => load();
  btnPrint.onclick = () => window.print();
  load();
});
</script>
