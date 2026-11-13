<?php if (isset($_GET['ver']) && ctype_digit((string) $_GET['ver'])): ?>
<h1 id="titulo-ver"></h1>
<p class="print-hide">
    <a href="/listados"><?= _("Volver a los listados") ?></a>
    <a href="/listados?editar=<?= (int) $_GET['ver'] ?>"><?= _("Editar") ?></a>
    <button type="button" id="btn-imprimir"><?= _("Imprimir") ?></button>
</p>
<div id="resultado-listado"></div>
<script>
document.getElementById('btn-imprimir').addEventListener('click', () => window.print());
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/listados/' + <?= (int) $_GET['ver'] ?>);
  if (!r.ok) { alert(r.error || 'Error'); return; }
  const titulo = r.listado.nombre + (r.periodo ? ' (' + r.periodo + ')' : '');
  document.getElementById('titulo-ver').textContent = titulo;
  document.title = titulo;
  const box = document.getElementById('resultado-listado');
  let html = '';
  if (r.totales && r.totales.length) {
    html += '<table><thead><tr><th>Cuenta</th><th class="num">Importe</th></tr></thead><tbody>';
    r.totales.forEach(t => {
      html += '<tr><td>' + esc(t.codigo + ' ' + t.nombre) + '</td><td class="num">' + esc(fmtImporteEs(t.importe / 100)) + '</td></tr>';
    });
    html += '</tbody></table>';
  }
  GRUPOS = r.grupos || {};
  SALDOS = r.saldos || null;
  if (r.movimientos && r.movimientos.length) {
    html += pintarMovimientos(r.listado, r.movimientos);
  }
  box.innerHTML = html;
});

const COLS = {
  fecha: <?= json_encode(_("Fecha"), JSON_UNESCAPED_UNICODE) ?>,
  tesoreria: <?= json_encode(_("Cuenta"), JSON_UNESCAPED_UNICODE) ?>,
  categoria: <?= json_encode(_("Categoría"), JSON_UNESCAPED_UNICODE) ?>,
  glosa: <?= json_encode(_("Glosa"), JSON_UNESCAPED_UNICODE) ?>,
  debe: <?= json_encode(_("Debe"), JSON_UNESCAPED_UNICODE) ?>,
  haber: <?= json_encode(_("Haber"), JSON_UNESCAPED_UNICODE) ?>,
  importe: <?= json_encode(_("Importe"), JSON_UNESCAPED_UNICODE) ?>,
};
const ORDEN_COLS = ['fecha', 'tesoreria', 'categoria', 'glosa', 'debe', 'haber', 'importe'];
const COLS_NUM = ['debe', 'haber', 'importe'];
const esNum = (c) => COLS_NUM.includes(c);

/** Importe = haber − debe: los ingresos salen en positivo y los gastos en negativo. */
function celdaNum(c, debe, haber) {
  const v = c === 'debe' ? debe : (c === 'haber' ? haber : haber - debe);
  return '<td class="num">' + esc(fmtImporteEs(v / 100)) + '</td>';
}
const TXT = {
  ingreso: <?= json_encode(_("Ingresos"), JSON_UNESCAPED_UNICODE) ?>,
  gasto: <?= json_encode(_("Gastos"), JSON_UNESCAPED_UNICODE) ?>,
  total: <?= json_encode(_("Total"), JSON_UNESCAPED_UNICODE) ?>,
  resultado: <?= json_encode(_("Resultado"), JSON_UNESCAPED_UNICODE) ?>,
  saldoInicial: <?= json_encode(_("Saldo inicial"), JSON_UNESCAPED_UNICODE) ?>,
  saldoFinal: <?= json_encode(_("Saldo final"), JSON_UNESCAPED_UNICODE) ?>,
  otros: <?= json_encode(_("Otros"), JSON_UNESCAPED_UNICODE) ?>,
};

let GRUPOS = {};
let SALDOS = null;

function columnasDe(listado) {
  const pedidas = listado.columnas && listado.columnas.length ? listado.columnas : ['fecha', 'categoria', 'glosa', 'debe', 'haber'];
  return ORDEN_COLS.filter(c => pedidas.includes(c));
}

function celda(col, m) {
  if (col === 'fecha') return esc(m.fecha);
  if (col === 'tesoreria') return esc(m.tesoreria || '');
  if (col === 'categoria') return esc(m.codigo + ' ' + m.nombre);
  if (col === 'glosa') return esc(m.glosa);
  if (col === 'debe') return esc(fmtImporteEs(m.debe / 100));
  if (col === 'importe') return esc(fmtImporteEs((m.haber - m.debe) / 100));
  return esc(fmtImporteEs(m.haber / 100));
}

function tituloGrupo(nivel, m) {
  if (nivel === 'signo') return m.signo === 'ingreso' ? TXT.ingreso : TXT.gasto;
  if (nivel === 'periodo') return m.periodo_grupo || '';
  if (nivel === 'categoria') return m.codigo + ' ' + m.nombre;
  if (nivel === 'glosa') return m.grupo_glosa || TXT.otros;
  return m.tesoreria || '';
}

function claveGrupo(nivel, m) {
  if (nivel === 'signo') return m.signo;
  if (nivel === 'periodo') return m.periodo_orden || m.periodo_grupo;
  if (nivel === 'categoria') return m.codigo;
  if (nivel === 'glosa') return m.grupo_glosa_orden;
  return m.tesoreria || '';
}

function pintarMovimientos(listado, filas) {
  const niveles = [];
  if (listado.separar_periodo) niveles.push('periodo');
  if (listado.separar_signo) niveles.push('signo');
  const agrupar = listado.agrupar || [];
  agrupar.forEach(n => niveles.push(n));
  // Al agrupar sale una línea por grupo con su total; con el detalle pedido, cada grupo
  // lleva su título, sus movimientos y el total.
  const resumen = agrupar.length > 0 && !listado.detalle_grupos;
  let cols = columnasDe(listado);
  let cabecera = COLS;
  if (resumen) {
    const importes = cols.filter(esNum);
    cols = ['grupo', ...(importes.length ? importes : ['debe', 'haber'])];
    cabecera = Object.assign({}, COLS, { grupo: COLS[agrupar[agrupar.length - 1]] });
  }
  let html = '<table class="informe"><thead><tr>';
  cols.forEach(c => { html += '<th' + (esNum(c) ? ' class="num"' : '') + '>' + esc(cabecera[c]) + '</th>'; });
  html += '</tr></thead><tbody>';
  if (SALDOS) html += filaSaldo(cols, TXT.saldoInicial, SALDOS.inicial);
  html += filasNivel(filas, niveles, cols, resumen);
  if (SALDOS) {
    // Con un solo periodo, su total ya es el resultado; si no, se añade aquí.
    const periodos = new Set(filas.map(m => claveGrupo('periodo', m)));
    if (niveles[0] !== 'periodo' || periodos.size !== 1) {
      let debe = 0, haber = 0;
      filas.forEach(m => { debe += m.debe; haber += m.haber; });
      html += filaTotal(cols, TXT.resultado, debe, haber, 'subtotal seccion', false);
    }
    html += filaSaldo(cols, TXT.saldoFinal, SALDOS.final);
  }
  html += '</tbody></table>';
  return html;
}

function filaTotal(cols, titulo, debe, haber, clase, sangria) {
  let tr = clase ? '<tr class="' + clase + '">' : '<tr>';
  cols.forEach((c, i) => {
    if (esNum(c)) tr += celdaNum(c, debe, haber);
    else tr += '<td' + (sangria ? ' style="padding-left:1.5rem"' : '') + '>' + (i === 0 ? esc(titulo) : '') + '</td>';
  });
  return tr + '</tr>';
}

/** Saldo de caja y banco: en Importe con su signo; en Debe/Haber, al haber si es positivo (como la apertura). */
function filaSaldo(cols, titulo, saldo) {
  if (!cols.some(esNum)) {
    return '<tr class="subtotal seccion seccion-saldo"><td colspan="' + cols.length + '">'
      + esc(titulo + ': ' + fmtImporteEs(saldo / 100)) + '</td></tr>';
  }
  const etiqueta = cols.findIndex(c => !esNum(c));
  let tr = '<tr class="subtotal seccion seccion-saldo">';
  cols.forEach((c, i) => {
    if (c === 'importe') tr += '<td class="num">' + esc(fmtImporteEs(saldo / 100)) + '</td>';
    else if (c === 'haber') tr += '<td class="num">' + (saldo >= 0 || !cols.includes('debe') ? esc(fmtImporteEs(saldo / 100)) : '') + '</td>';
    else if (c === 'debe') tr += '<td class="num">' + (saldo < 0 || !cols.includes('haber') ? esc(fmtImporteEs(-saldo / 100)) : '') + '</td>';
    else tr += '<td>' + (i === etiqueta ? esc(titulo) : '') + '</td>';
  });
  return tr + '</tr>';
}

function madreDe(codigo) {
  const i = codigo.indexOf('.');
  const madre = i > 0 ? codigo.slice(0, i) : '';
  return madre && GRUPOS[madre] ? madre : '';
}

/** Una línea por categoría; cada categoría madre (61…) abre con su título y el total de sus hijas. */
function filasCategorias(filas, cols) {
  const hojas = [];
  filas.forEach(m => {
    let h = hojas[hojas.length - 1];
    if (!h || h.codigo !== m.codigo) {
      h = { codigo: m.codigo, titulo: m.codigo + ' ' + m.nombre, madre: madreDe(m.codigo), debe: 0, haber: 0 };
      hojas.push(h);
    }
    h.debe += m.debe;
    h.haber += m.haber;
  });
  let html = '';
  for (let i = 0; i < hojas.length;) {
    const madre = hojas[i].madre;
    let j = i;
    while (j < hojas.length && hojas[j].madre === madre) j++;
    const hijas = hojas.slice(i, j);
    if (madre) {
      let debe = 0, haber = 0;
      hijas.forEach(h => { debe += h.debe; haber += h.haber; });
      html += filaTotal(cols, madre + ' ' + GRUPOS[madre], debe, haber, 'grupo', false);
    }
    hijas.forEach(h => { html += filaTotal(cols, h.titulo, h.debe, h.haber, '', !!madre); });
    i = j;
  }
  return html;
}

function filasNivel(filas, niveles, cols, resumen) {
  if (!niveles.length) {
    return filas.map(m => {
      let tr = '<tr>';
      cols.forEach(c => { tr += '<td' + (esNum(c) ? ' class="num"' : '') + '>' + celda(c, m) + '</td>'; });
      return tr + '</tr>';
    }).join('');
  }
  const nivel = niveles[0];
  const resto = niveles.slice(1);
  if (resumen && !resto.length && nivel === 'categoria') return filasCategorias(filas, cols);
  let html = '';
  let actual = null;
  let grupo = [];
  const volcar = () => {
    if (!grupo.length) return;
    const titulo = tituloGrupo(nivel, grupo[0]);
    const hoja = resumen && !resto.length;
    // Periodo e ingresos/gastos son secciones: cabecera y total más marcados que los de un grupo.
    const seccion = nivel === 'periodo' || nivel === 'signo' ? ' seccion seccion-' + nivel : '';
    if (!hoja) {
      html += '<tr class="grupo' + seccion + '"><td colspan="' + cols.length + '">' + esc(titulo) + '</td></tr>';
      html += filasNivel(grupo, resto, cols, resumen);
    }
    let debe = 0, haber = 0;
    grupo.forEach(m => { debe += m.debe; haber += m.haber; });
    html += hoja ? '<tr>' : '<tr class="subtotal' + seccion + '">';
    cols.forEach((c, i) => {
      if (esNum(c)) html += celdaNum(c, debe, haber);
      else html += '<td>' + (i === 0 ? esc(hoja ? titulo : (SALDOS && nivel === 'periodo' ? TXT.resultado : TXT.total) + ' ' + titulo) : '') + '</td>';
    });
    html += '</tr>';
    grupo = [];
  };
  filas.forEach(m => {
    const clave = claveGrupo(nivel, m);
    if (actual !== null && clave !== actual) volcar();
    actual = clave;
    grupo.push(m);
  });
  volcar();
  return html;
}
</script>
<?php return; endif; ?>
<h1><?= _("Listados") ?></h1>
<p class="muted"><?= _("Informes del club. En Existentes se abren los que ya hay. En Nuevo se crea uno: cuentas, un tercero que aparezca en la glosa, y el detalle, los totales, o los dos.") ?></p>
<div class="plan-cuenta-tabs" role="tablist">
    <button type="button" id="tab-existentes" role="tab" aria-selected="true" class="on"><?= _("Existentes") ?></button>
    <button type="button" id="tab-nuevo" role="tab" aria-selected="false"><?= _("Nuevo") ?></button>
</div>
<p id="msg-listados" class="muted"></p>

<section id="panel-existentes">
    <table id="tabla-listados">
        <thead><tr><th><?= _("Nombre") ?></th><th></th><th></th></tr></thead>
        <tbody></tbody>
    </table>
</section>

<section id="panel-nuevo" hidden>
    <form id="form-listado" class="grid-form">
        <input type="hidden" name="id" value="">
        <fieldset class="listado-seccion">
            <legend><?= _("General") ?></legend>
            <div class="listado-cabecera">
                <label><?= _("Nombre") ?> <input name="nombre" required></label>
                <label class="cuenta-fila"><input type="checkbox" name="mostrar_movimientos" checked> <?= _("Movimientos") ?></label>
                <label class="cuenta-fila"><input type="checkbox" name="mostrar_totales" checked> <?= _("Totales por cuenta") ?></label>
                <label class="cuenta-fila"><input type="checkbox" name="incluir_saldos"> <?= _("Saldo inicial y final") ?></label>
            </div>
            <p class="muted"><?= _("Saldo inicial y final: lo que hay en caja y banco al empezar y al acabar el periodo (el inicial es la apertura). No sale si el listado filtra por categorías, terceros o texto.") ?></p>
        </fieldset>
        <fieldset class="listado-seccion">
            <legend><?= _("Fechas") ?></legend>
            <div class="fechas-linea">
                <label><?= _("Ejercicio") ?>
                    <select name="periodo" id="sel-periodo">
                        <option value="actual"><?= _("Actual") ?></option>
                        <option value="anterior"><?= _("Anterior") ?></option>
                        <option value="otro"><?= _("Otro") ?></option>
                    </select>
                </label>
                <div id="fechas-periodo" hidden>
                    <label><?= _("Desde") ?> <input name="fecha_desde" type="date"></label>
                    <label><?= _("Hasta") ?> <input name="fecha_hasta" type="date"></label>
                </div>
            </div>
        </fieldset>
        <fieldset class="listado-seccion">
            <legend><?= _("Cuentas") ?></legend>
            <p class="muted"><?= _("Caja y banco. Si no se marca ninguna, entran todas.") ?></p>
            <div id="tesoreria-listado" class="cuentas-hojas"></div>
        </fieldset>
        <fieldset class="listado-seccion">
            <legend><?= _("Transferencias") ?></legend>
            <label class="cuenta-fila"><input type="checkbox" name="incluir_traspasos" checked> <?= _("Incluir traspasos entre caja y banco") ?></label>
        </fieldset>
        <fieldset class="listado-seccion">
            <legend><?= _("Categorías") ?></legend>
            <div id="cuentas-listado"></div>
        </fieldset>
        <fieldset class="listado-seccion">
            <legend><?= _("Terceros") ?></legend>
            <label><?= _("Uno por línea, tal como sale en la glosa") ?> <textarea name="terceros" rows="3"></textarea></label>
        </fieldset>
        <fieldset class="listado-seccion">
            <legend><?= _("Textos") ?></legend>
            <label><?= _("Texto en la nota") ?> <input name="texto"></label>
        </fieldset>
        <fieldset class="listado-seccion">
            <legend><?= _("Importes") ?></legend>
            <label class="cuenta-fila"><input type="checkbox" name="excluir_nulos"> <?= _("Excluir importes a cero") ?></label>
        </fieldset>
        <fieldset class="listado-seccion">
            <legend><?= _("Organización") ?></legend>
            <p class="muted"><?= _("Agrupar. Sale una línea por grupo con su total, sin el detalle de movimientos, salvo que se marque «Mostrar los movimientos de cada grupo». El primero es el grupo más general. Subir y bajar cambian el orden.") ?></p>
            <label class="cuenta-fila"><input type="checkbox" name="detalle_grupos"> <?= _("Mostrar los movimientos de cada grupo") ?></label>
            <ul id="niveles-agrupar" class="niveles-agrupar">
                <li data-nivel="categoria">
                    <button type="button" data-mover="-1"><?= _("Subir") ?></button>
                    <button type="button" data-mover="1"><?= _("Bajar") ?></button>
                    <label class="cuenta-fila"><input type="checkbox" checked> <?= _("Categoría") ?></label>
                </li>
                <li data-nivel="tesoreria">
                    <button type="button" data-mover="-1"><?= _("Subir") ?></button>
                    <button type="button" data-mover="1"><?= _("Bajar") ?></button>
                    <label class="cuenta-fila"><input type="checkbox"> <?= _("Cuenta") ?></label>
                </li>
                <li data-nivel="glosa">
                    <button type="button" data-mover="-1"><?= _("Subir") ?></button>
                    <button type="button" data-mover="1"><?= _("Bajar") ?></button>
                    <label class="cuenta-fila"><input type="checkbox"> <?= _("Texto de la glosa") ?></label>
                </li>
            </ul>
            <label><?= _("Grupos por texto de la glosa: uno por línea, nombre = textos separados con |") ?>
                <textarea name="grupos_glosa" rows="4" placeholder="gas = Gas Natural | NATURGY&#10;telf = Telefono | YOIGO"></textarea>
            </label>
            <p class="muted"><?= _("Solo se usan si se marca «Texto de la glosa». Cada texto es una expresión regular y no distingue mayúsculas. El movimiento va al primer grupo en que aparece alguno de sus textos; los que no entran en ninguno salen en «Otros».") ?></p>
            <label class="cuenta-fila"><input type="checkbox" name="separar_signo"> <?= _("Separar ingresos y gastos") ?></label>
            <div class="fechas-linea">
                <label><?= _("Separar por periodo") ?>
                    <select name="separar_periodo" id="sel-separar">
                        <option value=""><?= _("No separar") ?></option>
                        <option value="dia"><?= _("Día") ?></option>
                        <option value="semana"><?= _("Semana") ?></option>
                        <option value="mes"><?= _("Mes") ?></option>
                        <option value="ano"><?= _("Año") ?></option>
                    </select>
                </label>
                <label><?= _("Orden de los movimientos") ?>
                    <select name="orden">
                        <option value="fecha"><?= _("Fecha") ?></option>
                        <option value="glosa"><?= _("Glosa") ?></option>
                        <option value="importe"><?= _("Importe") ?></option>
                    </select>
                </label>
                <label><?= _("Sentido") ?>
                    <select name="orden_dir">
                        <option value="asc"><?= _("Ascendente") ?></option>
                        <option value="desc"><?= _("Descendente") ?></option>
                    </select>
                </label>
            </div>
        </fieldset>
        <fieldset class="listado-seccion">
            <legend><?= _("Columnas") ?></legend>
            <p class="muted"><?= _("Qué se muestra en cada movimiento.") ?></p>
            <div class="cuentas-hojas">
                <label class="cuenta-fila"><input type="checkbox" name="col_fecha" checked> <?= _("Fecha") ?></label>
                <label class="cuenta-fila"><input type="checkbox" name="col_tesoreria"> <?= _("Cuenta") ?></label>
                <label class="cuenta-fila"><input type="checkbox" name="col_categoria" checked> <?= _("Categoría") ?></label>
                <label class="cuenta-fila"><input type="checkbox" name="col_glosa" checked> <?= _("Glosa") ?></label>
                <label class="cuenta-fila"><input type="checkbox" name="col_debe" checked> <?= _("Debe") ?></label>
                <label class="cuenta-fila"><input type="checkbox" name="col_haber" checked> <?= _("Haber") ?></label>
                <label class="cuenta-fila"><input type="checkbox" name="col_importe"> <?= _("Importe (haber − debe)") ?></label>
            </div>
        </fieldset>
        <button type="submit"><?= _("Guardar") ?></button>
        <button type="button" id="btn-nuevo"><?= _("Limpiar") ?></button>
    </form>
</section>
<script>
const ORDEN_COLS = ['fecha', 'tesoreria', 'categoria', 'glosa', 'debe', 'haber', 'importe'];
const I18N_LIST = {
  ver: <?= json_encode(_("Ver"), JSON_UNESCAPED_UNICODE) ?>,
  editar: <?= json_encode(_("Editar"), JSON_UNESCAPED_UNICODE) ?>,
  borrar: <?= json_encode(_("Borrar"), JSON_UNESCAPED_UNICODE) ?>,
  confirm: <?= json_encode(_("¿Borrar este listado?"), JSON_UNESCAPED_UNICODE) ?>,
  guardado: <?= json_encode(_("Listado guardado"), JSON_UNESCAPED_UNICODE) ?>,
};
let cuentas = [];
let tesoreria = [];
let listados = [];

function pestana(cual) {
  const nuevo = cual === 'nuevo';
  document.getElementById('panel-nuevo').hidden = !nuevo;
  document.getElementById('panel-existentes').hidden = nuevo;
  document.getElementById('tab-nuevo').classList.toggle('on', nuevo);
  document.getElementById('tab-existentes').classList.toggle('on', !nuevo);
  document.getElementById('tab-nuevo').setAttribute('aria-selected', nuevo ? 'true' : 'false');
  document.getElementById('tab-existentes').setAttribute('aria-selected', nuevo ? 'false' : 'true');
}

function limpiarFormulario() {
  const f = document.getElementById('form-listado');
  f.reset();
  f.id.value = '';
  pintarCuentas([]);
  pintarTesoreria([]);
  ordenarNiveles(['categoria']);
  verFechas();
}

function pintarCuentas(marcadas) {
  const box = document.getElementById('cuentas-listado');
  box.replaceChildren();
  const grupos = new Map();
  cuentas.forEach(c => {
    const clave = c.codigo.includes('.') ? c.codigo.split('.')[0] : c.codigo;
    if (!grupos.has(clave)) grupos.set(clave, { titulo: null, hojas: [] });
    const g = grupos.get(clave);
    if (c.grupo || !c.codigo.includes('.')) g.titulo = c;
    else g.hojas.push(c);
  });
  grupos.forEach((g, codigo) => {
    if (g.hojas.length === 0 && !g.titulo) return;
    const sec = document.createElement('section');
    sec.className = 'cuentas-grupo';
    const h = document.createElement('h3');
    h.textContent = g.titulo ? (g.titulo.codigo + ' = ' + g.titulo.nombre) : codigo;
    sec.appendChild(h);
    const hojas = document.createElement('div');
    hojas.className = 'cuentas-hojas';
    sec.appendChild(hojas);
    g.hojas.forEach(c => {
      const label = document.createElement('label');
      label.className = 'cuenta-fila';
      const input = document.createElement('input');
      input.type = 'checkbox';
      input.value = c.codigo;
      input.checked = marcadas.includes(c.codigo);
      label.appendChild(input);
      label.appendChild(document.createTextNode(c.codigo + ' ' + c.nombre));
      hojas.appendChild(label);
    });
    box.appendChild(sec);
  });
}

function pintarTabla() {
  const tb = document.querySelector('#tabla-listados tbody');
  tb.innerHTML = '';
  listados.forEach(l => {
    const tr = document.createElement('tr');
    tr.innerHTML = '<td class="ver"><button type="button" data-act="abrir" data-id="' + l.id + '">' + esc(I18N_LIST.ver) + '</button></td>'
      + '<td>' + esc(l.nombre) + '</td><td class="acc">'
      + '<button type="button" data-act="editar" data-id="' + l.id + '">' + esc(I18N_LIST.editar) + '</button> '
      + '<button type="button" data-act="borrar" data-id="' + l.id + '">' + esc(I18N_LIST.borrar) + '</button></td>';
    tb.appendChild(tr);
  });
}

async function cargar() {
  const r = await api('/api/listados');
  const msg = document.getElementById('msg-listados');
  if (!r.ok) {
    msg.textContent = r.error || '';
    return;
  }
  msg.textContent = '';
  cuentas = r.cuentas || [];
  tesoreria = r.tesoreria || [];
  listados = r.listados || [];
  pintarCuentas([]);
  pintarTesoreria([]);
  pintarTabla();
  const editar = new URLSearchParams(location.search).get('editar');
  if (editar) {
    const l = listados.find(x => String(x.id) === editar);
    if (l) rellenarFormulario(l);
  }
}

function rellenarFormulario(l) {
  const f = document.getElementById('form-listado');
  f.id.value = l.id;
  f.nombre.value = l.nombre;
  f.periodo.value = l.periodo || 'actual';
  f.fecha_desde.value = l.fecha_desde || '';
  f.fecha_hasta.value = l.fecha_hasta || '';
  verFechas();
  f.mostrar_movimientos.checked = !!l.mostrar_movimientos;
  f.mostrar_totales.checked = !!l.mostrar_totales;
  f.terceros.value = (l.terceros || []).join('\n');
  f.texto.value = l.texto || '';
  f.grupos_glosa.value = (l.grupos_glosa || []).map(g => g.nombre === g.patron ? g.nombre : g.nombre + ' = ' + g.patron).join('\n');
  f.incluir_traspasos.checked = l.incluir_traspasos !== false;
  f.excluir_nulos.checked = !!l.excluir_nulos;
  f.incluir_saldos.checked = !!l.incluir_saldos;
  pintarTesoreria(l.cuentas_tesoreria || []);
  pintarCuentas(l.categorias || []);
  const cols = l.columnas && l.columnas.length ? l.columnas : ['fecha', 'categoria', 'glosa', 'debe', 'haber'];
  ORDEN_COLS.forEach(c => {
    f['col_' + c].checked = cols.includes(c);
  });
  ordenarNiveles(l.agrupar || []);
  f.separar_signo.checked = !!l.separar_signo;
  f.detalle_grupos.checked = !!l.detalle_grupos;
  f.separar_periodo.value = l.separar_periodo || '';
  f.orden.value = l.orden || 'fecha';
  f.orden_dir.value = l.orden_desc ? 'desc' : 'asc';
  pestana('nuevo');
}

function ordenarNiveles(activos) {
  const ul = document.getElementById('niveles-agrupar');
  const items = [...ul.children];
  const orden = [...activos, ...items.map(li => li.dataset.nivel).filter(n => !activos.includes(n))];
  orden.forEach(nivel => {
    const li = items.find(el => el.dataset.nivel === nivel);
    if (!li) return;
    li.querySelector('input').checked = activos.includes(nivel);
    ul.appendChild(li);
  });
}

document.getElementById('tab-nuevo').addEventListener('click', () => {
  limpiarFormulario();
  pestana('nuevo');
});
document.getElementById('tab-existentes').addEventListener('click', () => pestana('existentes'));

function pintarTesoreria(marcadas) {
  const box = document.getElementById('tesoreria-listado');
  box.replaceChildren();
  tesoreria.forEach(c => {
    const label = document.createElement('label');
    label.className = 'cuenta-fila';
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.value = c.codigo;
    input.checked = marcadas.includes(c.codigo);
    label.appendChild(input);
    label.appendChild(document.createTextNode(c.nombre));
    box.appendChild(label);
  });
}

function verFechas() {
  const otro = document.getElementById('sel-periodo').value === 'otro';
  document.getElementById('fechas-periodo').hidden = !otro;
}
document.getElementById('sel-periodo').addEventListener('change', verFechas);
document.getElementById('niveles-agrupar').addEventListener('click', (ev) => {
  const btn = ev.target.closest('button');
  if (!btn) return;
  const li = btn.closest('li');
  const ul = li.parentElement;
  const dir = Number(btn.dataset.mover);
  if (dir < 0 && li.previousElementSibling) ul.insertBefore(li, li.previousElementSibling);
  if (dir > 0 && li.nextElementSibling) ul.insertBefore(li.nextElementSibling, li);
});
document.getElementById('btn-nuevo').addEventListener('click', () => {
  limpiarFormulario();
  verFechas();
});

document.getElementById('form-listado').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const f = ev.target;
  const categorias = [...f.querySelectorAll('#cuentas-listado input:checked')].map(i => i.value);
  const cuentasTesoreria = [...f.querySelectorAll('#tesoreria-listado input:checked')].map(i => i.value);
  const terceros = f.terceros.value.split('\n').map(s => s.trim()).filter(Boolean);
  // «nombre = patrón»; una línea sin «=» es un grupo que se llama como su texto.
  const gruposGlosa = f.grupos_glosa.value.split('\n').map(s => s.trim()).filter(Boolean).map(linea => {
    const i = linea.indexOf('=');
    return i < 0 ? { nombre: linea, patron: linea } : { nombre: linea.slice(0, i).trim(), patron: linea.slice(i + 1).trim() };
  });
  const id = f.id.value ? Number(f.id.value) : undefined;
  const r = await api('/api/listados', {
    method: 'POST',
    body: {
      id,
      nombre: f.nombre.value,
      periodo: f.periodo.value,
      fecha_desde: f.periodo.value === 'otro' ? f.fecha_desde.value : '',
      fecha_hasta: f.periodo.value === 'otro' ? f.fecha_hasta.value : '',
      mostrar_movimientos: f.mostrar_movimientos.checked,
      mostrar_totales: f.mostrar_totales.checked,
      categorias,
      terceros,
      cuentas_tesoreria: cuentasTesoreria,
      incluir_traspasos: f.incluir_traspasos.checked,
      excluir_nulos: f.excluir_nulos.checked,
      incluir_saldos: f.incluir_saldos.checked,
      texto: f.texto.value,
      grupos_glosa: gruposGlosa,
      columnas: ORDEN_COLS.filter(c => f['col_' + c].checked),
      agrupar: [...document.querySelectorAll('#niveles-agrupar li')].filter(li => li.querySelector('input').checked).map(li => li.dataset.nivel),
      separar_signo: f.separar_signo.checked,
      detalle_grupos: f.detalle_grupos.checked,
      separar_periodo: f.separar_periodo.value,
      orden: f.orden.value,
      orden_desc: f.orden_dir.value === 'desc',
    },
  });
  if (!r.ok) return alert(r.error || 'Error');
  const msg = document.getElementById('msg-listados');
  msg.className = 'ok';
  msg.textContent = I18N_LIST.guardado;
  listados = r.listados || [];
  pintarTabla();
  limpiarFormulario();
  pestana('existentes');
});

document.querySelector('#tabla-listados').addEventListener('click', async (ev) => {
  const btn = ev.target.closest('button');
  if (!btn) return;
  const id = Number(btn.dataset.id);
  const l = listados.find(x => x.id === id);
  if (btn.dataset.act === 'editar' && l) {
    rellenarFormulario(l);
  }
  if (btn.dataset.act === 'borrar') {
    if (!confirm(I18N_LIST.confirm)) return;
    const r = await api('/api/listados/' + id + '/borrar', { method: 'POST', body: {} });
    if (!r.ok) return alert(r.error || 'Error');
    listados = r.listados || [];
    pintarTabla();
  }
  if (btn.dataset.act === 'abrir') {
    window.location.href = '/listados?ver=' + id;
  }
});

document.addEventListener('DOMContentLoaded', cargar);
</script>
