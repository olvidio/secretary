<?php $cuenta = $cuentaEntrada ?? 'P'; ?>
<h1><?= sprintf(_("Entrada apuntes %s"), htmlspecialchars($cuenta, ENT_QUOTES)) ?></h1>
<p class="muted"><?php if ($cuenta === 'G'): ?><?= _("Con iniciales elegidas, al escribir en observaciones aparecen las de esa persona (las más usadas primero); al elegir una se copian observaciones y concepto. 41 y 42 generan un solo asiento caja/banco. La fecha de imputación solo si hay que contarlo en otro día (p. ej. operación el 8/01 y gasto el 31/12): entonces se crean dos asientos enlazados, sin que haya que pensar en debe y haber. Un gasto de G con iniciales anota también P/111, P/211 y G/11.") ?><?php else: ?><?= _("Con iniciales elegidas, al escribir en observaciones aparecen las de esa persona (las más usadas primero); al elegir una se copian observaciones y concepto. 41 y 42 generan un solo asiento caja/banco. La fecha de imputación solo si hay que contarlo en otro día (p. ej. operación el 8/01 y gasto el 31/12): entonces se crean dos asientos enlazados, sin que haya que pensar en debe y haber.") ?><?php endif; ?></p>

<?php if ($cuenta === 'G'): ?>
<details class="yo-ayuda-bloque" id="entrada-banco-bloque">
    <summary><?= _("Importar extracto del banco") ?></summary>
    <div class="yo-ayuda-cuerpo">
        <p class="muted"><?= _("Sube el extracto del banco. Cada origen tiene un formato distinto. La siguiente vez solo se crean movimientos nuevos. Lo importado entra en Por categorizar hasta que le asignes concepto e iniciales si procede.") ?></p>
        <p class="muted"><?= _("N26: en la web, cuenta → Descargas → actividad de la cuenta → CSV.") ?></p>
        <p class="muted"><?= _("CaixaBank: CaixaBankNow → Cuentas → tu cuenta. Carga todo el periodo (pulsa «Ver más movimientos» si aparece) y elige Extraer movimientos / Descargar en Excel (.xls). También admite CSV si lo guardas así.") ?></p>
        <p class="muted"><?= _("BBVA: Cuentas → la cuenta → Movimientos → Descargar en Excel o CSV.") ?></p>
        <p class="muted"><?= _("Banco Sabadell: Operativa diaria → Cuentas → Saldos y movimientos → Descargar → Excel o CSV.") ?></p>
    </div>
    <form id="entrada-banco-form" class="yo-banco-form">
        <label><?= _("Banco") ?>
            <select name="banco" id="entrada-banco-sel" required></select>
        </label>
        <label id="wrap-banco-fisica" hidden><?= _("Cuenta de tesorería") ?>
            <select name="cuenta_fisica_id" id="entrada-banco-fisica" disabled></select>
        </label>
        <label><?= _("Fichero") ?>
            <input name="fichero" id="entrada-banco-fichero" type="file" accept=".csv,.xls,.xlsx,text/csv" required>
        </label>
        <button type="submit" id="entrada-banco-enviar"><?= _("Importar") ?></button>
    </form>
    <p class="ok" id="entrada-banco-msg" hidden></p>
    <h2><?= _("Por categorizar") ?></h2>
    <p class="error" id="entrada-banco-err" hidden></p>
    <p class="muted" id="entrada-banco-vacio" hidden><?= _("No hay movimientos pendientes.") ?></p>
    <ul id="entrada-banco-pend" class="yo-lista"></ul>
    <details class="yo-ayuda-bloque" id="entrada-banco-otra-bloque">
        <summary><?= _("Otra contabilidad") ?> <span class="muted entrada-banco-otra-cnt" id="entrada-banco-otra-cnt" hidden></span></summary>
        <p class="muted"><?= _("No suman en ingresos y gastos del plan general; sí mueven el banco. Puedes asignarles un concepto más adelante.") ?></p>
        <p class="muted" id="entrada-banco-otra-vacio" hidden><?= _("No hay movimientos en otra contabilidad.") ?></p>
        <ul id="entrada-banco-otra" class="yo-lista"></ul>
    </details>
</details>
<?php endif; ?>

<div id="entrada-apuntes">
    <div class="entrada-cabecera">
        <label><?= _("Iniciales") ?>
            <select id="hdr-iniciales"><option value=""></option></select>
        </label>
        <label>A/B/C
            <select id="hdr-origen" required>
                <option value="A"><?= _("Apunte") ?></option>
                <option value="B"><?= _("Banco") ?></option>
                <option value="C"><?= _("Caja") ?></option>
            </select>
        </label>
        <label><?= _("Fecha") ?> <input id="hdr-fecha" type="date" required></label>
        <label id="wrap-fisica" hidden><?= _("Cuenta de tesorería") ?>
            <select id="hdr-fisica" disabled></select>
        </label>
    </div>

    <table class="entrada-lineas">
        <thead>
            <tr>
                <th class="col-obs"><?= _("Observaciones") ?></th>
                <th class="col-concepto"><?= _("Concepto") ?></th>
                <th class="col-fimp"><?= _("F. imputación") ?></th>
                <th class="col-cant"><?= _("Cantidad") ?></th>
                <th class="col-acc"></th>
            </tr>
        </thead>
        <tbody id="cuerpo-lineas">
            <tr id="fila-nueva">
                <td class="col-obs sugerencias-wrap">
                    <input id="inp-obs" autocomplete="off">
                    <ul id="sugerencias-obs" class="sugerencias" hidden></ul>
                </td>
                <td class="col-concepto">
                    <select id="sel-concepto" required></select>
                </td>
                <td class="col-fimp">
                    <input id="inp-fimp" type="date" tabindex="-1" title="<?= htmlspecialchars(_("Vacío = la misma fecha de cabecera."), ENT_QUOTES) ?>">
                </td>
                <td class="col-cant">
                    <input id="inp-cant" required inputmode="decimal">
                </td>
                <td class="col-acc">
                    <button type="button" id="btn-anadir"><?= _("Añadir") ?></button>
                </td>
            </tr>
        </tbody>
    </table>
    <div id="entrada-pie" class="entrada-pie" hidden>
        <div id="cuadre-alerta" class="cuadre-alerta" hidden>
            <p id="cuadre-msg"></p>
            <p id="cuadre-sug" class="muted"></p>
        </div>
        <span id="pie-gastos"></span>
        <span id="pie-saldo"></span>
        <button type="button" id="btn-cuadrar" hidden><?= _("Cuadrar (111)") ?></button>
    </div>
    <p id="plantilla-preview" class="muted plantilla-preview" hidden></p>
    <p class="muted entrada-hint"><?= _("F. imputación vacía = la misma fecha de cabecera. Si difiere, el gasto/ingreso se imputa en ese día y la contrapartida (caja, banco o personal) en la fecha de cabecera; el tabulador la salta (de concepto a cantidad).") ?>
        <?= _("Las") ?> <a href="/plantillas-<?= strtolower($cuenta) ?>"><?= _("plantillas") ?></a> <?= _("recurrentes aparecen en el desplegable de concepto.") ?><?php if ($cuenta === 'G'): ?> <?= _("Gasto con iniciales: P/111 → P/211 → G/11 → el gasto.") ?><?php endif; ?></p>
</div>

<p id="msg" class="ok" hidden><?= _("Apunte guardado") ?></p>
<p id="err" class="error" hidden></p>

<script>
const CUENTA = <?= json_encode($cuenta) ?>;
const I18N_ENTRADA = {
  plantillas: <?= json_encode(_("Plantillas"), JSON_UNESCAPED_UNICODE) ?>,
  conceptos: <?= json_encode(_("Conceptos"), JSON_UNESCAPED_UNICODE) ?>,
  anadir: <?= json_encode(_("Añadir"), JSON_UNESCAPED_UNICODE) ?>,
  anadirPlantilla: <?= json_encode(_("Añadir plantilla"), JSON_UNESCAPED_UNICODE) ?>,
  apunteGuardado: <?= json_encode(_("Apunte guardado"), JSON_UNESCAPED_UNICODE) ?>,
  apuntesGuardados: <?= json_encode(_("%s apuntes guardados"), JSON_UNESCAPED_UNICODE) ?>,
  indiqueCantidad: <?= json_encode(_("Indique la cantidad en la cabecera de la fila"), JSON_UNESCAPED_UNICODE) ?>,
  noCuadre: <?= json_encode(_("No se pudo comprobar el cuadre"), JSON_UNESCAPED_UNICODE) ?>,
  noSugerencia: <?= json_encode(_("No hay sugerencia de cuadre disponible"), JSON_UNESCAPED_UNICODE) ?>,
  conIniciales: <?= json_encode(_("Con iniciales: "), JSON_UNESCAPED_UNICODE) ?>,
  enPantalla: <?= json_encode(_("En pantalla: "), JSON_UNESCAPED_UNICODE) ?>,
  saldoPantalla: <?= json_encode(_("Saldo pantalla: "), JSON_UNESCAPED_UNICODE) ?>,
  cuadrar: <?= json_encode(_("Cuadrar (%s · %s)"), JSON_UNESCAPED_UNICODE) ?>,
  cuadreOk: <?= json_encode(_("Apuntes A de %s cuadrados (saldo 0)."), JSON_UNESCAPED_UNICODE) ?>,
  cuadreMal: <?= json_encode(_("Los apuntes A de %s no cuadran: saldo %s (gastos − ingresos)."), JSON_UNESCAPED_UNICODE) ?>,
  cuadreAntesFecha: <?= json_encode(_(" Antes del %s cuadraba; el desajuste viene probablemente de los apuntes de esa fecha."), JSON_UNESCAPED_UNICODE) ?>,
  sugSoloGastos: <?= json_encode(_("Sugerencia: añadir ingreso 111 (Trabajo) por %s."), JSON_UNESCAPED_UNICODE) ?>,
  sugEquilibrar: <?= json_encode(_("Sugerencia: añadir ingreso 111 (Trabajo) por %s para equilibrar los apuntes A."), JSON_UNESCAPED_UNICODE) ?>,
  sugVivienda211: <?= json_encode(_("Sugerencia: añadir gasto 211 (vivienda general) por %s."), JSON_UNESCAPED_UNICODE) ?>,
  sugVivienda212: <?= json_encode(_("Sugerencia: añadir gasto 212 (vivienda personal) por %s."), JSON_UNESCAPED_UNICODE) ?>,
  sugNecesidades: <?= json_encode(_("Sugerencia: añadir gasto 6 (necesidades) por %s."), JSON_UNESCAPED_UNICODE) ?>,
  importNuevos: <?= json_encode(_("Nuevos: %s. Ya estaban: %s."), JSON_UNESCAPED_UNICODE) ?>,
  importOmitidos: <?= json_encode(_(" Omitidos: %s."), JSON_UNESCAPED_UNICODE) ?>,
  elijaConcepto: <?= json_encode(_("Elija un concepto"), JSON_UNESCAPED_UNICODE) ?>,
  traspasoCaja: <?= json_encode(_("Traspaso a caja"), JSON_UNESCAPED_UNICODE) ?>,
  otraContabilidad: <?= json_encode(_("Otra contabilidad"), JSON_UNESCAPED_UNICODE) ?>,
  conceptoG: <?= json_encode(_("Concepto G"), JSON_UNESCAPED_UNICODE) ?>,
  conceptoP: <?= json_encode(_("Concepto P"), JSON_UNESCAPED_UNICODE) ?>,
  cambiarAP: <?= json_encode(_("Cambiar a P"), JSON_UNESCAPED_UNICODE) ?>,
  cambiarAG: <?= json_encode(_("Cambiar a G"), JSON_UNESCAPED_UNICODE) ?>,
  elijaIniciales: <?= json_encode(_("Las iniciales son obligatorias"), JSON_UNESCAPED_UNICODE) ?>,
  asignar: <?= json_encode(_("Asignar"), JSON_UNESCAPED_UNICODE) ?>,
  iniciales: <?= json_encode(_("Iniciales"), JSON_UNESCAPED_UNICODE) ?>,
  concepto: <?= json_encode(_("Concepto"), JSON_UNESCAPED_UNICODE) ?>,
  observaciones: <?= json_encode(_("Observaciones"), JSON_UNESCAPED_UNICODE) ?>,
};
document.addEventListener('DOMContentLoaded', async () => {
  const cfg = await api('/api/configuracion');
  const hoy = new Date().toISOString().slice(0, 10);
  const cierre = cfg.config.fecha_cierre;
  document.getElementById('hdr-fecha').value = hoy.slice(0, 7) === cierre.slice(0, 7) ? hoy : cierre;

  const selI = document.getElementById('hdr-iniciales');
  const mapPersona = {};
  const pers = await api('/api/personas');
  (pers.personas || []).forEach(p => {
    mapPersona[p.iniciales] = p;
    const o = document.createElement('option');
    o.value = p.iniciales;
    o.textContent = p.iniciales + ' — ' + p.nombre_completo;
    selI.appendChild(o);
  });

  const selC = document.getElementById('sel-concepto');
  const btnAnadir = document.getElementById('btn-anadir');
  const mapConcepto = {};
  const mapEtiqueta = {};
  const plantillasById = {};
  let plantillaActiva = null;
  let optgroupPlantillas = null;
  const cons = await api('/api/conceptos?cuenta=' + CUENTA);
  (cons.conceptos || []).forEach(c => { mapConcepto[c.codigo] = c; mapEtiqueta[CUENTA + ':' + c.codigo] = c.etiqueta; });
  if (CUENTA === 'G') {
    const consP = await api('/api/conceptos?cuenta=P');
    (consP.conceptos || []).forEach(c => { mapEtiqueta['P:' + c.codigo] = c.etiqueta; });
  }

  function pintarSelectConceptos() {
    selC.innerHTML = '';
    selC.appendChild(Object.assign(document.createElement('option'), { value: '' }));
    optgroupPlantillas = document.createElement('optgroup');
    optgroupPlantillas.label = I18N_ENTRADA.plantillas;
    const ogConceptos = document.createElement('optgroup');
    ogConceptos.label = I18N_ENTRADA.conceptos;
    selC.appendChild(optgroupPlantillas);
    selC.appendChild(ogConceptos);
    (cons.conceptos || []).forEach(c => {
      const o = document.createElement('option');
      o.value = c.codigo;
      o.textContent = c.etiqueta;
      ogConceptos.appendChild(o);
    });
  }
  pintarSelectConceptos();

  async function cargarPlantillas() {
    if (!optgroupPlantillas) return;
    optgroupPlantillas.innerHTML = '';
    Object.keys(plantillasById).forEach(k => delete plantillasById[k]);
    const r = await api('/api/plantillas-apunte?cuenta=' + encodeURIComponent(CUENTA));
    (r.plantillas || []).forEach(p => {
      plantillasById[p.id] = p;
      const o = document.createElement('option');
      o.value = '@plantilla:' + p.id;
      o.textContent = p.etiqueta;
      optgroupPlantillas.appendChild(o);
    });
    optgroupPlantillas.hidden = optgroupPlantillas.children.length === 0;
  }
  await cargarPlantillas();

  const tes = await api('/api/tesoreria');
  const fisicas = (tes.cuentas_fisicas || []).filter(f => f.activo);
  const wrapFisica = document.getElementById('wrap-fisica');
  const selFisica = document.getElementById('hdr-fisica');
  const selOrigen = document.getElementById('hdr-origen');

  function syncFisica() {
    const origen = selOrigen.value;
    const tipo = origen === 'C' ? 'caja' : (origen === 'B' ? 'banco' : null);
    const opts = tipo ? fisicas.filter(f => f.tipo === tipo) : [];
    if (!tipo || opts.length <= 1) {
      wrapFisica.hidden = true;
      selFisica.disabled = true;
      return;
    }
    selFisica.innerHTML = opts.map(f =>
      `<option value="${f.id}">${esc(f.nombre)}</option>`).join('');
    selFisica.disabled = false;
    wrapFisica.hidden = false;
  }
  syncFisica();

  const inpObs = document.getElementById('inp-obs');
  const inpFimp = document.getElementById('inp-fimp');
  const inpCant = document.getElementById('inp-cant');
  const ulSug = document.getElementById('sugerencias-obs');
  const filaNueva = document.getElementById('fila-nueva');
  const cuerpoLineas = document.getElementById('cuerpo-lineas');
  const filasPantalla = [];
  let cuadreActual = null;
  let tSug = null;
  let sugItems = [];
  let sugIdx = -1;

  function etiquetaConcepto(codigo, cuenta) {
    const libro = cuenta || CUENTA;
    const et = mapEtiqueta[libro + ':' + codigo];
    if (et) return (libro !== CUENTA ? libro + ' ' : '') + et;
    return (libro !== CUENTA ? libro + ' ' : '') + codigo;
  }

  function ocultarSug() {
    ulSug.hidden = true;
    ulSug.innerHTML = '';
    sugItems = [];
    sugIdx = -1;
  }

  function pintarSug() {
    ulSug.innerHTML = '';
    sugItems.forEach((s, i) => {
      const li = document.createElement('li');
      const b = document.createElement('button');
      b.type = 'button';
      if (i === sugIdx) b.classList.add('on');
      b.innerHTML = '<span>' + esc(s.observaciones) + '</span> <span class="muted">' + esc(etiquetaConcepto(s.concepto_codigo)) + '</span>';
      b.onmousedown = (ev) => { ev.preventDefault(); aplicarSug(s); };
      li.appendChild(b);
      ulSug.appendChild(li);
    });
    ulSug.hidden = sugItems.length === 0;
  }

  function aplicarSug(s) {
    inpObs.value = s.observaciones;
    selC.value = s.concepto_codigo;
    ocultarSug();
    actualizarPreviewContrapartidas();
    inpObs.focus();
  }

  async function buscarSug() {
    const q = inpObs.value;
    const ini = selI.value;
    if (!ini || q.length < 2) {
      ocultarSug();
      return;
    }
    const r = await api('/api/apuntes/sugerencias?cuenta=' + encodeURIComponent(CUENTA)
      + '&iniciales=' + encodeURIComponent(ini) + '&q=' + encodeURIComponent(q));
    if (!r.ok) {
      ocultarSug();
      return;
    }
    sugItems = r.sugerencias || [];
    sugIdx = sugItems.length ? 0 : -1;
    pintarSug();
  }

  inpObs.addEventListener('input', () => {
    clearTimeout(tSug);
    tSug = setTimeout(buscarSug, 200);
    actualizarPreviewContrapartidas();
  });
  inpObs.addEventListener('keydown', (ev) => {
    if (ulSug.hidden) return;
    if (ev.key === 'ArrowDown') {
      ev.preventDefault();
      sugIdx = Math.min(sugItems.length - 1, sugIdx + 1);
      pintarSug();
    } else if (ev.key === 'ArrowUp') {
      ev.preventDefault();
      sugIdx = Math.max(0, sugIdx - 1);
      pintarSug();
    } else if (ev.key === 'Enter' && sugIdx >= 0) {
      ev.preventDefault();
      aplicarSug(sugItems[sugIdx]);
    } else if (ev.key === 'Escape') {
      ocultarSug();
    }
  });
  document.addEventListener('click', (ev) => {
    if (!ev.target.closest('.sugerencias-wrap')) ocultarSug();
  });

  function payloadFila() {
    const body = {
      cuenta: CUENTA,
      iniciales: selI.value,
      origen: selOrigen.value,
      fecha: document.getElementById('hdr-fecha').value,
      observaciones: inpObs.value,
      concepto_codigo: selC.value,
      fecha_imputacion: inpFimp.value,
      cantidad: inpCant.value,
    };
    if (!selFisica.disabled && selFisica.value) {
      body.cuenta_fisica_id = selFisica.value;
    }
    return body;
  }

  function limpiarFilaActiva() {
    inpObs.value = '';
    selC.value = '';
    inpFimp.value = '';
    inpCant.value = '';
    ocultarSug();
    limpiarPlantillaActiva();
  }

  function limpiarPlantillaActiva() {
    plantillaActiva = null;
    document.getElementById('plantilla-preview').hidden = true;
    btnAnadir.textContent = I18N_ENTRADA.anadir;
  }

  function resumenPlantilla(p) {
    return (p.lineas || []).map(l =>
      (l.cuenta || 'P') + ' ' + l.origen + ' ' + l.concepto_codigo
      + (l.observaciones ? ' · ' + l.observaciones : '')
    ).join(' → ');
  }

  function personaAporta(ini) {
    const p = mapPersona[ini];
    if (!p) return true;
    return p.vivienda_aporta_generales !== false;
  }

  function lineasContrapartidas() {
    if (CUENTA !== 'G' || plantillaActiva) return null;
    const ini = selI.value;
    const codigo = selC.value;
    if (!ini || !codigo || codigo.startsWith('@plantilla:')) return null;
    const c = mapConcepto[codigo];
    if (!c || c.naturaleza !== 'gasto') return null;
    const obs = inpObs.value;
    const origen = selOrigen.value || 'A';
    return [
      { cuenta: 'P', origen: 'A', concepto_codigo: '111', observaciones: '' },
      { cuenta: 'P', origen: 'A', concepto_codigo: '211', observaciones: obs },
      { cuenta: 'G', origen: 'A', concepto_codigo: '11', observaciones: obs },
      { cuenta: 'G', origen: origen, concepto_codigo: codigo, observaciones: obs },
    ];
  }

  function actualizarPreviewContrapartidas() {
    if (plantillaActiva) return;
    const prev = document.getElementById('plantilla-preview');
    const lineas = lineasContrapartidas();
    if (!lineas) {
      prev.hidden = true;
      btnAnadir.textContent = I18N_ENTRADA.anadir;
      return;
    }
    prev.textContent = I18N_ENTRADA.conIniciales + resumenPlantilla({ lineas: lineas });
    prev.hidden = false;
    btnAnadir.textContent = I18N_ENTRADA.anadir + ' (' + lineas.length + ')';
  }

  function aplicarPlantilla(p) {
    plantillaActiva = p;
    const primera = (p.lineas || [])[0];
    if (primera) {
      inpObs.value = primera.observaciones || '';
    }
    inpCant.value = '';
    const prev = document.getElementById('plantilla-preview');
    prev.textContent = p.nombre + ': ' + resumenPlantilla(p);
    prev.hidden = false;
    btnAnadir.textContent = I18N_ENTRADA.anadirPlantilla + ' (' + (p.lineas || []).length + ')';
    inpObs.focus();
  }

  function resetFormulario() {
    filasPantalla.length = 0;
    cuerpoLineas.querySelectorAll('tr.fila-guardada').forEach(tr => tr.remove());
    limpiarFilaActiva();
    document.getElementById('msg').hidden = true;
    document.getElementById('err').hidden = true;
  }

  function onCabeceraChange() {
    resetFormulario();
    actualizarCuadre();
    actualizarPreviewContrapartidas();
  }

  function cantidadNum(val) {
    if (typeof val === 'number') return val;
    const s = String(val ?? '').trim().replace(',', '.');
    return parseFloat(s) || 0;
  }

  function fmtEuro(n) {
    return n.toLocaleString(secretaryLocale(), {
      useGrouping: true,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  function calcularTotales() {
    let gastos = 0;
    let saldo = 0;
    filasPantalla.forEach(f => {
      const c = mapConcepto[f.concepto_codigo];
      if (!c) return;
      if (c.naturaleza === 'gasto') {
        gastos += f.cantidadNum;
        saldo += f.cantidadNum;
      } else if (c.naturaleza === 'ingreso') {
        saldo -= f.cantidadNum;
      }
    });
    return { gastos, saldo };
  }

  function textoCuadre(c) {
    const ini = esc(c.iniciales || selI.value);
    if (c.cuadrado) {
      return I18N_ENTRADA.cuadreOk.replace('%s', ini);
    }
    let msg = I18N_ENTRADA.cuadreMal.replace('%s', ini).replace('%s', esc(c.saldo_total_es));
    if (c.cuadrado_antes_fecha && c.solo_gastos_fecha && c.sugerencia) {
      msg += I18N_ENTRADA.cuadreAntesFecha.replace('%s', fmtFecha(c.fecha));
    }
    return msg;
  }

  function textoSugerencia(c) {
    if (!c.sugerencia) return '';
    const s = c.sugerencia;
    if (s.motivo === 'solo_gastos_fecha') {
      return I18N_ENTRADA.sugSoloGastos.replace('%s', esc(s.cantidad_es));
    }
    if (s.motivo === 'saldo_negativo_vivienda') {
      if (s.concepto_codigo === '212') {
        return I18N_ENTRADA.sugVivienda212.replace('%s', esc(s.cantidad_es));
      }
      if (s.concepto_codigo === '6') {
        return I18N_ENTRADA.sugNecesidades.replace('%s', esc(s.cantidad_es));
      }
      return I18N_ENTRADA.sugVivienda211.replace('%s', esc(s.cantidad_es));
    }
    return I18N_ENTRADA.sugEquilibrar.replace('%s', esc(s.cantidad_es));
  }

  async function actualizarCuadre() {
    const pie = document.getElementById('entrada-pie');
    const alerta = document.getElementById('cuadre-alerta');
    const esA = selOrigen.value === 'A' && CUENTA === 'P';
    pie.hidden = !esA;
    if (!esA) return;

    const { gastos, saldo } = calcularTotales();
    document.getElementById('pie-gastos').textContent = gastos > 0 ? I18N_ENTRADA.enPantalla + fmtEuro(gastos) : '';
    document.getElementById('pie-saldo').textContent = filasPantalla.length
      ? I18N_ENTRADA.saldoPantalla + fmtEuro(saldo) : '';

    const ini = selI.value;
    const fecha = document.getElementById('hdr-fecha').value;
    if (!ini || !fecha) {
      alerta.hidden = true;
      document.getElementById('btn-cuadrar').hidden = true;
      cuadreActual = null;
      return;
    }

    const r = await api('/api/apuntes/cuadre?cuenta=' + encodeURIComponent(CUENTA)
      + '&iniciales=' + encodeURIComponent(ini) + '&fecha=' + encodeURIComponent(fecha));
    if (!r.ok) {
      cuadreActual = null;
      alerta.hidden = false;
      alerta.classList.remove('cuadre-ok', 'cuadre-mal');
      document.getElementById('cuadre-msg').textContent = r.error || I18N_ENTRADA.noCuadre;
      document.getElementById('cuadre-sug').hidden = true;
      document.getElementById('btn-cuadrar').hidden = true;
      return;
    }
    cuadreActual = r.cuadre || null;
    if (!cuadreActual?.aplica) {
      alerta.hidden = true;
      document.getElementById('btn-cuadrar').hidden = true;
      return;
    }

    alerta.hidden = false;
    alerta.classList.toggle('cuadre-ok', !!cuadreActual.cuadrado);
    alerta.classList.toggle('cuadre-mal', !cuadreActual.cuadrado);
    document.getElementById('cuadre-msg').textContent = textoCuadre(cuadreActual);
    const sug = textoSugerencia(cuadreActual);
    const elSug = document.getElementById('cuadre-sug');
    elSug.textContent = sug;
    elSug.hidden = !sug;

    const btn = document.getElementById('btn-cuadrar');
    btn.hidden = !cuadreActual.sugerencia;
    btn.disabled = !cuadreActual.sugerencia;
    if (cuadreActual.sugerencia) {
      btn.textContent = I18N_ENTRADA.cuadrar
        .replace('%s', cuadreActual.sugerencia.concepto_codigo)
        .replace('%s', cuadreActual.sugerencia.cantidad_es);
    }
  }

  function appendFilaGuardada(datos, apunte) {
    const tr = document.createElement('tr');
    tr.className = 'fila-guardada';
    const fimp = datos.fecha_imputacion || '';
    const fHdr = datos.fecha || '';
    const tdFimp = fimp && fimp !== fHdr ? fmtFecha(fimp) : '';
    tr.innerHTML =
      '<td>' + esc(datos.observaciones) + '</td>' +
      '<td>' + esc(etiquetaConcepto(datos.concepto_codigo, datos.cuenta)) + '</td>' +
      '<td>' + esc(tdFimp) + '</td>' +
      '<td class="num">' + esc(apunte?.cantidad_es || datos.cantidad) + '</td>' +
      '<td></td>';
    cuerpoLineas.insertBefore(tr, filaNueva);
    filasPantalla.push({
      concepto_codigo: datos.concepto_codigo,
      cantidadNum: cantidadNum(apunte?.cantidad ?? datos.cantidad),
      observaciones: datos.observaciones,
    });
  }

  function payloadLinea(linea, idx, cantidad) {
    const body = {
      cuenta: linea.cuenta || CUENTA,
      iniciales: selI.value,
      origen: linea.origen,
      fecha: document.getElementById('hdr-fecha').value,
      observaciones: idx === 0 ? inpObs.value : (linea.observaciones || ''),
      concepto_codigo: linea.concepto_codigo,
      fecha_imputacion: idx === 0 ? inpFimp.value : '',
      cantidad: cantidad,
    };
    if (!selFisica.disabled && selFisica.value && linea.origen !== 'A') {
      body.cuenta_fisica_id = selFisica.value;
    }
    return body;
  }

  async function anadirApunte() {
    document.getElementById('err').hidden = true;
    document.getElementById('msg').hidden = true;

    if (plantillaActiva) {
      const cantidad = inpCant.value.trim();
      if (!cantidad) {
        document.getElementById('err').hidden = false;
        document.getElementById('err').textContent = I18N_ENTRADA.indiqueCantidad;
        inpCant.focus();
        return;
      }
      for (let i = 0; i < plantillaActiva.lineas.length; i++) {
        const datos = payloadLinea(plantillaActiva.lineas[i], i, cantidad);
        const s = await api('/api/apuntes', { method: 'POST', body: datos });
        if (!s.ok) {
          document.getElementById('err').hidden = false;
          document.getElementById('err').textContent = s.error;
          return;
        }
        appendFilaGuardada(datos, (s.apuntes || [])[0]);
      }
      document.getElementById('msg').hidden = false;
      limpiarFilaActiva();
      inpObs.focus();
      await actualizarCuadre();
      actualizarPreviewContrapartidas();
      return;
    }

    const datos = payloadFila();
    const extras = lineasContrapartidas();
    if (extras) datos.contrapartidas = true;
    const s = await api('/api/apuntes', { method: 'POST', body: datos });
    if (!s.ok) {
      document.getElementById('err').hidden = false;
      document.getElementById('err').textContent = s.error;
      return;
    }
    document.getElementById('msg').hidden = false;
    const creados = s.apuntes || [];
    document.getElementById('msg').textContent = creados.length > 1
      ? I18N_ENTRADA.apuntesGuardados.replace('%s', creados.length) : I18N_ENTRADA.apunteGuardado;
    creados.forEach(apunte => {
      appendFilaGuardada({
        observaciones: apunte.observaciones || datos.observaciones,
        concepto_codigo: apunte.concepto_codigo,
        fecha_imputacion: apunte.fecha_imputacion || '',
        fecha: apunte.fecha || datos.fecha,
        cantidad: apunte.cantidad,
        cuenta: apunte.cuenta || datos.cuenta,
      }, apunte);
    });
    if (creados.length === 0) {
      appendFilaGuardada(datos, null);
    }
    limpiarFilaActiva();
    inpObs.focus();
    await actualizarCuadre();
    actualizarPreviewContrapartidas();
  }

  async function cuadrar() {
    document.getElementById('err').hidden = true;
    document.getElementById('msg').hidden = true;
    const sug = cuadreActual?.sugerencia;
    if (!sug) {
      document.getElementById('err').hidden = false;
      document.getElementById('err').textContent = I18N_ENTRADA.noSugerencia;
      return;
    }
    const datos = {
      cuenta: CUENTA,
      iniciales: selI.value,
      origen: 'A',
      fecha: document.getElementById('hdr-fecha').value,
      observaciones: 'Cuadre',
      concepto_codigo: sug.concepto_codigo,
      cantidad: sug.cantidad,
    };
    const s = await api('/api/apuntes', { method: 'POST', body: datos });
    if (!s.ok) {
      document.getElementById('err').hidden = false;
      document.getElementById('err').textContent = s.error;
      return;
    }
    document.getElementById('msg').hidden = false;
    const apunte = (s.apuntes || [])[0];
    appendFilaGuardada(datos, apunte);
    await actualizarCuadre();
  }

  selC.addEventListener('change', () => {
    const v = selC.value;
    if (v.startsWith('@plantilla:')) {
      const p = plantillasById[v.slice(11)];
      if (p) aplicarPlantilla(p);
      return;
    }
    limpiarPlantillaActiva();
    actualizarPreviewContrapartidas();
  });
  selC.addEventListener('keydown', (ev) => {
    if (ev.key === 'Tab' && !ev.shiftKey) {
      ev.preventDefault();
      inpCant.focus();
    }
  });

  btnAnadir.addEventListener('click', anadirApunte);
  document.getElementById('btn-cuadrar').addEventListener('click', cuadrar);
  inpCant.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      anadirApunte();
    }
  });

  selI.addEventListener('change', onCabeceraChange);
  document.getElementById('hdr-fecha').addEventListener('change', onCabeceraChange);
  selOrigen.addEventListener('change', () => {
    syncFisica();
    onCabeceraChange();
  });
  selFisica.addEventListener('change', onCabeceraChange);

  actualizarCuadre();
  actualizarPreviewContrapartidas();

  if (CUENTA === 'G') {
    await pintarImportBancoCentro();
  }
});

async function pintarImportBancoCentro() {
  const selBanco = document.getElementById('entrada-banco-sel');
  const form = document.getElementById('entrada-banco-form');
  if (!selBanco || !form) return;

  const errBanco = document.getElementById('entrada-banco-err');
  const msgBanco = document.getElementById('entrada-banco-msg');

  function errApi(s) {
    return s.error || s.mensaje || s.message || 'Error';
  }

  function limpiarErrBanco() {
    document.querySelectorAll('.entrada-banco-ini-error').forEach(el => {
      el.classList.remove('entrada-banco-ini-error');
    });
    if (errBanco) errBanco.hidden = true;
  }

  function mostrarErrBanco(msg, li) {
    if (msgBanco) msgBanco.hidden = true;
    document.querySelectorAll('.entrada-banco-ini-error').forEach(el => {
      el.classList.remove('entrada-banco-ini-error');
    });
    if (errBanco) {
      errBanco.hidden = false;
      errBanco.textContent = msg;
      errBanco.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    if (li) {
      const ini = li.querySelector('.entrada-banco-ini');
      const field = ini ? ini.closest('.entrada-banco-field') : null;
      if (field) field.classList.add('entrada-banco-ini-error');
      if (ini) ini.focus();
    }
  }

  const b = await api('/api/banco-centro/bancos');
  if (!b.ok) return mostrarErrBanco(b.error || 'Error');
  (b.bancos || []).forEach(x => {
    const o = document.createElement('option');
    o.value = x.id;
    o.textContent = x.nombre;
    selBanco.appendChild(o);
  });
  if (b.banco && (b.bancos || []).some(x => x.id === b.banco)) selBanco.value = b.banco;

  const tes = await api('/api/tesoreria');
  const fisicas = (tes.cuentas_fisicas || []).filter(f => f.activo && f.tipo === 'banco');
  const wrapFisica = document.getElementById('wrap-banco-fisica');
  const selFisica = document.getElementById('entrada-banco-fisica');
  function syncFisicaImport() {
    if (!wrapFisica || !selFisica) return;
    if (fisicas.length <= 1) {
      wrapFisica.hidden = true;
      selFisica.disabled = true;
      return;
    }
    selFisica.innerHTML = fisicas.map(f =>
      `<option value="${f.id}">${esc(f.nombre)}</option>`).join('');
    selFisica.disabled = false;
    wrapFisica.hidden = false;
  }
  syncFisicaImport();

  const inputFichero = document.getElementById('entrada-banco-fichero');
  function actualizarAcceptBanco() {
    if (!inputFichero) return;
    inputFichero.accept = selBanco.value === 'caixabank'
      ? '.csv,.xls,.xlsx,text/csv' : '.csv,text/csv';
  }
  selBanco.onchange = () => {
    actualizarAcceptBanco();
    api('/api/banco-centro/preferencia', { method: 'POST', body: { banco: selBanco.value } });
  };
  actualizarAcceptBanco();

  let conceptosG = [];
  let conceptosP = [];
  const consG = await api('/api/conceptos?cuenta=G');
  conceptosG = (consG.conceptos || []).filter(c => c.naturaleza === 'ingreso' || c.naturaleza === 'gasto');
  const consP = await api('/api/conceptos?cuenta=P');
  conceptosP = (consP.conceptos || []).filter(c => c.naturaleza === 'ingreso' || c.naturaleza === 'gasto');

  function opcionesConceptoG(sentido, sugerida, categoriaCodigo) {
    const opts = conceptosG.filter(c => c.naturaleza === sentido);
    const esOtra = categoriaCodigo === 'OTRA.gasto' || categoriaCodigo === 'OTRA.ingreso';
    let html = `<option value="">${esc(I18N_ENTRADA.conceptoG)}…</option>`;
    opts.forEach(c => {
      const sel = !esOtra && c.codigo === sugerida ? ' selected' : '';
      html += `<option value="${esc(c.codigo)}"${sel}>${esc(c.etiqueta)}</option>`;
    });
    html += '<option disabled>────────</option>';
    html += `<option value="@otra"${esOtra ? ' selected' : ''}>${esc(I18N_ENTRADA.otraContabilidad)}</option>`;
    html += `<option value="@traspaso">${esc(I18N_ENTRADA.traspasoCaja)}</option>`;
    return html;
  }

  function opcionesConceptoP(sentido) {
    const opts = conceptosP.filter(c => c.naturaleza === sentido);
    let html = `<option value="">${esc(I18N_ENTRADA.conceptoP)}…</option>`;
    opts.forEach(c => {
      html += `<option value="${esc(c.codigo)}">${esc(c.etiqueta)}</option>`;
    });
    return html;
  }

  const optsIni = () => {
    const hdr = document.getElementById('hdr-iniciales');
    const iniHdr = hdr ? hdr.value : '';
    return '<option value=""></option>' + Array.from(hdr.options)
      .filter(o => o.value).map(o => {
        const sel = o.value === iniHdr ? ' selected' : '';
        return `<option value="${esc(o.value)}"${sel}>${esc(o.textContent)}</option>`;
      }).join('');
  };

  function inicialesDeFila(ini) {
    const v = ini ? String(ini.value || '').trim() : '';
    if (v) return v;
    const hdr = document.getElementById('hdr-iniciales');
    return hdr ? String(hdr.value || '').trim() : '';
  }

  function pintarSelectConcepto(sel, sentido, modo, sugerida, categoriaCodigo) {
    sel.innerHTML = modo === 'P'
      ? opcionesConceptoP(sentido)
      : opcionesConceptoG(sentido, sugerida, categoriaCodigo);
  }

  function syncModoConcepto(li, p, modo) {
    li.dataset.modo = modo;
    const label = li.querySelector('.entrada-banco-concepto-label');
    const sel = li.querySelector('.entrada-banco-concepto');
    const btnP = li.querySelector('.entrada-banco-a-p');
    if (label) label.textContent = modo === 'P' ? I18N_ENTRADA.conceptoP : I18N_ENTRADA.conceptoG;
    if (sel) pintarSelectConcepto(sel, p.sentido, modo, p.sugerida_codigo || '', p.categoria_codigo || '');
    if (btnP) btnP.textContent = modo === 'P' ? I18N_ENTRADA.cambiarAG : I18N_ENTRADA.cambiarAP;
  }

  function filaBancoCentro(p, conIniciales) {
    const imp = String(p.importe || '').replace('.', ',');
    const texto = String(p.nota || p.concepto || '');
    const iniHtml = conIniciales
      ? `<label class="entrada-banco-field muted">${esc(I18N_ENTRADA.iniciales)}<select class="entrada-banco-ini">${optsIni()}</select></label>`
      : '';
    const btnPHtml = conIniciales
      ? `<button type="button" class="entrada-banco-a-p">${esc(I18N_ENTRADA.cambiarAP)}</button>`
      : '';
    return `<span><strong>${esc(fmtFecha(p.fecha))} · ${esc(imp)}</strong></span>` +
      `<div class="yo-banco-pend-acc"><div class="yo-banco-pend-row entrada-banco-pend-row">` +
      iniHtml +
      `<label class="entrada-banco-field muted"><span class="entrada-banco-concepto-label">${esc(I18N_ENTRADA.conceptoG)}</span>` +
      `<div class="yo-banco-cat-wrap"><select class="entrada-banco-concepto">${opcionesConceptoG(p.sentido, p.sugerida_codigo || '', p.categoria_codigo || '')}</select></div></label>` +
      `<div class="entrada-banco-acciones">` +
      `<button type="button" class="yo-banco-asignar">${esc(I18N_ENTRADA.asignar)}</button>` +
      btnPHtml +
      `</div></div>` +
      `<div class="yo-banco-obs-wrap">` +
      `<input type="text" class="yo-banco-obs" placeholder="${esc(I18N_ENTRADA.observaciones)}" maxlength="250" autocomplete="off" value="${esc(texto)}">` +
      `</div></div>`;
  }

  function enlazarFilaBancoCentro(li, p, conIniciales, refrescar) {
    li.dataset.modo = 'G';
    const btn = li.querySelector('.yo-banco-asignar');
    const btnP = li.querySelector('.entrada-banco-a-p');
    const choose = li.querySelector('.entrada-banco-concepto');
    const ini = conIniciales ? li.querySelector('.entrada-banco-ini') : null;
    const obs = li.querySelector('.yo-banco-obs');
    if (btnP) {
      btnP.onclick = () => syncModoConcepto(li, p, li.dataset.modo === 'P' ? 'G' : 'P');
    }
    if (!btn || !choose) return;
    btn.onclick = async () => {
      limpiarErrBanco();
      const v = choose.value;
      if (!v) return mostrarErrBanco(I18N_ENTRADA.elijaConcepto, li);
      const modoP = li.dataset.modo === 'P';
      const body = {
        fila_id: p.fila_id,
        observaciones: obs ? obs.value : '',
      };
      if (modoP) {
        const iniVal = inicialesDeFila(ini);
        if (!iniVal) return mostrarErrBanco(I18N_ENTRADA.elijaIniciales, li);
        body.cambiar_a_p = true;
        body.iniciales = iniVal;
        body.concepto_codigo = v;
      } else {
        if (conIniciales && ini) {
          const iniVal = inicialesDeFila(ini);
          if (iniVal) body.iniciales = iniVal;
        }
        if (v === '@traspaso') body.traspaso_caja = true;
        else if (v === '@otra') body.otra_contabilidad = true;
        else body.concepto_codigo = v;
      }
      const s = await api('/api/banco-centro/categorizar', { method: 'POST', body });
      if (!s.ok) return mostrarErrBanco(errApi(s), li);
      limpiarErrBanco();
      if (!modoP && v === '@otra') {
        const bloque = document.getElementById('entrada-banco-otra-bloque');
        if (bloque) bloque.open = true;
      }
      await refrescar();
    };
  }

  function pintarFilasBancoCentro(ul, vacio, rows, conIniciales, refrescar) {
    if (!ul) return;
    if (vacio) vacio.hidden = rows.length > 0;
    ul.innerHTML = '';
    rows.forEach(p => {
      const li = document.createElement('li');
      li.innerHTML = filaBancoCentro(p, conIniciales);
      enlazarFilaBancoCentro(li, p, conIniciales, refrescar);
      ul.appendChild(li);
    });
  }

  async function pintarPendientesBanco() {
    const r = await api('/api/banco-centro/pendientes');
    if (!r.ok) return mostrarErrBanco(r.error || 'Error');
    limpiarErrBanco();
    pintarFilasBancoCentro(
      document.getElementById('entrada-banco-pend'),
      document.getElementById('entrada-banco-vacio'),
      r.pendientes || [],
      true,
      pintarPendientesBanco,
    );
    const otras = r.otras || [];
    pintarFilasBancoCentro(
      document.getElementById('entrada-banco-otra'),
      document.getElementById('entrada-banco-otra-vacio'),
      otras,
      false,
      pintarPendientesBanco,
    );
    const cntOtra = document.getElementById('entrada-banco-otra-cnt');
    if (cntOtra) {
      if (otras.length) {
        cntOtra.hidden = false;
        cntOtra.textContent = '(' + otras.length + ')';
      } else {
        cntOtra.hidden = true;
        cntOtra.textContent = '';
      }
    }
  }

  await pintarPendientesBanco();

  form.onsubmit = async (ev) => {
    ev.preventDefault();
    const btn = document.getElementById('entrada-banco-enviar');
    if (btn) btn.disabled = true;
    const fd = new FormData(form);
    const r = await api('/api/banco-centro/csv', { method: 'POST', body: fd });
    if (btn) btn.disabled = false;
    const err = document.getElementById('entrada-banco-err');
    const msg = document.getElementById('entrada-banco-msg');
    if (msg) msg.hidden = true;
    if (!r.ok) {
      mostrarErrBanco(r.error || 'Error');
      return;
    }
    limpiarErrBanco();
    if (msg) {
      msg.hidden = false;
      let t = I18N_ENTRADA.importNuevos.replace('%s', r.nuevos).replace('%s', r.repetidos);
      if (r.omitidos) t += I18N_ENTRADA.importOmitidos.replace('%s', r.omitidos);
      msg.textContent = t;
    }
    const bancoId = selBanco.value;
    form.reset();
    selBanco.value = bancoId;
    syncFisicaImport();
    actualizarAcceptBanco();
    await pintarPendientesBanco();
  };
}
</script>
