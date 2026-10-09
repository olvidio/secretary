<style>
@page { size: A4 landscape; margin: 8mm; }
</style>
<h1 class="print-hide"><?= _("Cuentas personales (E37)") ?></h1>
<p class="filters print-hide">
    <label><?= _("Persona") ?>
        <select name="iniciales">
            <option value=""></option>
            <option value="__todas__"><?= _("Todas") ?></option>
        </select>
    </label>
    <button type="button" id="btn-imprimir" hidden><?= _("Imprimir") ?></button>
</p>

<p id="e37-cargando" class="muted print-hide" hidden><?= _("Cargando hojas…") ?></p>

<div id="hoja-e37" class="informe-e37-wrap" hidden>
    <div id="e37-contenedor"></div>
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

  const TODAS = '__todas__';
  const params = new URLSearchParams(location.search);
  if (params.get('todas') === '1') sel.value = TODAS;
  else if (params.get('iniciales')) sel.value = params.get('iniciales');

  const hoja = document.getElementById('hoja-e37');
  const contenedor = document.getElementById('e37-contenedor');
  const cargando = document.getElementById('e37-cargando');
  const btnPrint = document.getElementById('btn-imprimir');
  const personasOrden = pers.personas || [];

  function crearArticuloE37(r) {
    const cols = r.columnas || [];
    const art = document.createElement('article');
    art.className = 'informe-e37 informe-e37-bloque';

    const cab = document.createElement('header');
    cab.className = 'informe-e37-cab';
    cab.innerHTML = `<span><?= _("fecha cierre:") ?></span><span>${esc(fechaCierreEs(r.config && r.config.fecha_cierre))}</span>`;
    art.appendChild(cab);

    const scroll = document.createElement('div');
    scroll.className = 'informe-e37-scroll';
    const tabla = document.createElement('table');
    tabla.className = 'informe-e37-tabla';

    const thead = document.createElement('thead');
    const trCod = document.createElement('tr');
    trCod.className = 'informe-e37-codigos';
    trCod.innerHTML = '<th></th><th></th>' + cols.map(c =>
      `<th class="num">${esc(c.codigo || '')}</th>`
    ).join('');
    const trEt = document.createElement('tr');
    trEt.className = 'informe-e37-etiquetas';
    trEt.innerHTML = '<th><?= _("Fecha") ?></th><th><?= _("Concepto") ?></th>' + cols.map(c =>
      `<th class="num">${esc(c.etiqueta)}</th>`
    ).join('');
    thead.appendChild(trCod);
    thead.appendChild(trEt);
    tabla.appendChild(thead);

    const tb = document.createElement('tbody');
    (r.filas || []).forEach(f => {
      const tr = document.createElement('tr');
      const celdas = cols.map(c => celdaNum(f.celdas[c.clave + '_es'])).join('');
      tr.innerHTML = `<td class="e37-fecha">${esc(fechaCorta(f.fecha))}</td>
        <td class="e37-concepto">${esc(f.concepto)}</td>${celdas}`;
      tb.appendChild(tr);
    });
    tabla.appendChild(tb);

    const tfoot = document.createElement('tfoot');
    const t = r.totales || {};
    const nombre = (r.persona && r.persona.nombre) || r.iniciales || '';
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
    tfoot.appendChild(trTot);
    tabla.appendChild(tfoot);
    scroll.appendChild(tabla);
    art.appendChild(scroll);

    const avisoEl = document.createElement('p');
    avisoEl.className = 'aviso-saldo-cc';
    if (r.aviso_saldo_cc) {
      avisoEl.textContent = r.aviso_saldo_cc;
    } else {
      avisoEl.hidden = true;
    }
    art.appendChild(avisoEl);

    return art;
  }

  function pintarHojas(lista) {
    contenedor.innerHTML = '';
    lista.forEach(r => contenedor.appendChild(crearArticuloE37(r)));
    hoja.hidden = lista.length === 0;
    document.body.classList.toggle('e37-hoja', lista.length > 0);
    btnPrint.hidden = lista.length === 0;
  }

  function vaciarHoja() {
    contenedor.innerHTML = '';
    hoja.hidden = true;
    btnPrint.hidden = true;
    document.body.classList.remove('e37-hoja');
  }

  async function load() {
    const ini = sel.value;
    let url = '/e37';
    if (ini === TODAS) url += '?todas=1';
    else if (ini) url += '?iniciales=' + encodeURIComponent(ini);
    history.replaceState(null, '', url);

    if (!ini) {
      vaciarHoja();
      return;
    }

    sel.disabled = true;
    cargando.hidden = false;
    hoja.hidden = true;
    btnPrint.hidden = true;

    try {
      if (ini !== TODAS) {
        const r = await api('/api/informes/e37?iniciales=' + encodeURIComponent(ini));
        if (!r.ok) return alert(r.error || <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>);
        if (!r.columnas) return alert(<?= json_encode(_("No se pudo cargar la hoja."), JSON_UNESCAPED_UNICODE) ?>);
        pintarHojas([r]);
        return;
      }
      const hojas = [];
      for (const p of personasOrden) {
        const r = await api('/api/informes/e37?iniciales=' + encodeURIComponent(p.iniciales));
        if (!r.ok) {
          alert(r.error || <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>);
          return;
        }
        if (r.columnas) hojas.push(r);
      }
      pintarHojas(hojas);
    } finally {
      sel.disabled = false;
      cargando.hidden = true;
    }
  }

  sel.onchange = () => load();
  btnPrint.onclick = () => window.print();
  load();
});
</script>
