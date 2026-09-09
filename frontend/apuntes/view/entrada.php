<?php $cuenta = $cuentaEntrada ?? 'P'; ?>
<h1>Entrada apuntes <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></h1>
<p class="muted">Con iniciales elegidas, al escribir en observaciones aparecen las de esa persona (las más usadas primero); al elegir una se copian observaciones y concepto. 41 y 42 generan un solo asiento caja/banco. La fecha de imputación solo si hay que contarlo en otro día (p. ej. operación el 8/01 y gasto el 31/12): entonces se crean dos asientos enlazados, sin que haya que pensar en debe y haber.</p>

<div id="entrada-apuntes">
    <div class="entrada-cabecera">
        <label>Iniciales
            <select id="hdr-iniciales"><option value=""></option></select>
        </label>
        <label>A/B/C
            <select id="hdr-origen" required>
                <option value="A">Apunte</option>
                <option value="B">Banco</option>
                <option value="C">Caja</option>
            </select>
        </label>
        <label>Fecha <input id="hdr-fecha" type="date" required></label>
        <label id="wrap-fisica" hidden>Cuenta de tesorería
            <select id="hdr-fisica" disabled></select>
        </label>
    </div>

    <table class="entrada-lineas">
        <thead>
            <tr>
                <th class="col-obs">Observaciones</th>
                <th class="col-concepto">Concepto</th>
                <th class="col-fimp">F. imputación</th>
                <th class="col-cant">Cantidad</th>
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
                    <input id="inp-fimp" type="date" title="Vacío = la misma. Solo caja/banco.">
                </td>
                <td class="col-cant">
                    <input id="inp-cant" required inputmode="decimal">
                </td>
                <td class="col-acc">
                    <button type="button" id="btn-anadir">Añadir</button>
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
        <button type="button" id="btn-cuadrar" hidden>Cuadrar (111)</button>
    </div>
    <p id="plantilla-preview" class="muted plantilla-preview" hidden></p>
    <p class="muted entrada-hint">F. imputación vacía = la misma fecha de cabecera. Solo caja/banco.
        Las <a href="/plantillas-<?= strtolower($cuenta) ?>">plantillas</a> recurrentes aparecen en el desplegable de concepto.</p>
</div>

<p id="msg" class="ok" hidden>Apunte guardado</p>
<p id="err" class="error" hidden></p>

<script>
const CUENTA = <?= json_encode($cuenta) ?>;
document.addEventListener('DOMContentLoaded', async () => {
  const cfg = await api('/api/configuracion');
  const hoy = new Date().toISOString().slice(0, 10);
  const cierre = cfg.config.fecha_cierre;
  document.getElementById('hdr-fecha').value = hoy.slice(0, 7) === cierre.slice(0, 7) ? hoy : cierre;

  const selI = document.getElementById('hdr-iniciales');
  const pers = await api('/api/personas');
  (pers.personas || []).forEach(p => {
    const o = document.createElement('option');
    o.value = p.iniciales;
    o.textContent = p.iniciales + ' — ' + p.nombre_completo;
    selI.appendChild(o);
  });

  const selC = document.getElementById('sel-concepto');
  const btnAnadir = document.getElementById('btn-anadir');
  const mapConcepto = {};
  const plantillasById = {};
  let plantillaActiva = null;
  let optgroupPlantillas = null;
  const cons = await api('/api/conceptos?cuenta=' + CUENTA);
  (cons.conceptos || []).forEach(c => { mapConcepto[c.codigo] = c; });

  function pintarSelectConceptos() {
    selC.innerHTML = '';
    selC.appendChild(Object.assign(document.createElement('option'), { value: '' }));
    optgroupPlantillas = document.createElement('optgroup');
    optgroupPlantillas.label = 'Plantillas';
    const ogConceptos = document.createElement('optgroup');
    ogConceptos.label = 'Conceptos';
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

  function etiquetaConcepto(codigo) {
    const o = selC.querySelector('option[value="' + CSS.escape(codigo) + '"]');
    return o ? o.textContent : codigo;
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
    btnAnadir.textContent = 'Añadir';
  }

  function resumenPlantilla(p) {
    return (p.lineas || []).map(l =>
      (l.cuenta || 'P') + ' ' + l.origen + ' ' + l.concepto_codigo
      + (l.observaciones ? ' · ' + l.observaciones : '')
    ).join(' → ');
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
    btnAnadir.textContent = 'Añadir plantilla (' + (p.lineas || []).length + ')';
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
  }

  function cantidadNum(val) {
    if (typeof val === 'number') return val;
    const s = String(val ?? '').trim().replace(',', '.');
    return parseFloat(s) || 0;
  }

  function fmtEuro(n) {
    return n.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
      return 'Apuntes A de ' + ini + ' cuadrados (saldo 0).';
    }
    let msg = 'Los apuntes A de ' + ini + ' no cuadran: saldo ' + esc(c.saldo_total_es)
      + ' (gastos − ingresos).';
    if (c.cuadrado_antes_fecha && c.solo_gastos_fecha && c.sugerencia) {
      msg += ' Antes del ' + fmtFecha(c.fecha) + ' cuadraba; el desajuste viene probablemente'
        + ' de los apuntes de esa fecha.';
    }
    return msg;
  }

  function textoSugerencia(c) {
    if (!c.sugerencia) return '';
    const s = c.sugerencia;
    if (s.motivo === 'solo_gastos_fecha') {
      return 'Sugerencia: añadir ingreso 111 (Trabajo) por ' + esc(s.cantidad_es) + '.';
    }
    return 'Sugerencia: añadir ingreso 111 (Trabajo) por ' + esc(s.cantidad_es)
      + ' para equilibrar los apuntes A.';
  }

  async function actualizarCuadre() {
    const pie = document.getElementById('entrada-pie');
    const alerta = document.getElementById('cuadre-alerta');
    const esA = selOrigen.value === 'A' && CUENTA === 'P';
    pie.hidden = !esA;
    if (!esA) return;

    const { gastos, saldo } = calcularTotales();
    document.getElementById('pie-gastos').textContent = gastos > 0 ? 'En pantalla: ' + fmtEuro(gastos) : '';
    document.getElementById('pie-saldo').textContent = filasPantalla.length
      ? 'Saldo pantalla: ' + fmtEuro(saldo) : '';

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
      document.getElementById('cuadre-msg').textContent = r.error || 'No se pudo comprobar el cuadre';
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
      btn.textContent = 'Cuadrar (111 · ' + cuadreActual.sugerencia.cantidad_es + ')';
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
      '<td>' + esc(etiquetaConcepto(datos.concepto_codigo)) + '</td>' +
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
        document.getElementById('err').textContent = 'Indique la cantidad en la cabecera de la fila';
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
      return;
    }

    const datos = payloadFila();
    const s = await api('/api/apuntes', { method: 'POST', body: datos });
    if (!s.ok) {
      document.getElementById('err').hidden = false;
      document.getElementById('err').textContent = s.error;
      return;
    }
    document.getElementById('msg').hidden = false;
    const apunte = (s.apuntes || [])[0];
    appendFilaGuardada(datos, apunte);
    limpiarFilaActiva();
    inpObs.focus();
    await actualizarCuadre();
  }

  async function cuadrar() {
    document.getElementById('err').hidden = true;
    document.getElementById('msg').hidden = true;
    const sug = cuadreActual?.sugerencia;
    if (!sug) {
      document.getElementById('err').hidden = false;
      document.getElementById('err').textContent = 'No hay sugerencia de cuadre disponible';
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
});
</script>
