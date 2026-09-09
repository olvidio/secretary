<?php $cuenta = $cuentaEntrada ?? 'P'; ?>
<h1>Entrada apuntes <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></h1>
<p class="muted">Un espacio al inicio de observaciones copia el nombre del concepto. Un _ escribe Decepal. 41 y 42 generan un solo asiento caja/banco. La fecha de imputación solo si hay que contarlo en otro día (p. ej. operación el 8/01 y gasto el 31/12): entonces se crean dos asientos enlazados, sin que haya que pensar en debe y haber.</p>
<form id="form-apunte" class="grid-form">
    <input type="hidden" name="cuenta" value="<?= htmlspecialchars($cuenta, ENT_QUOTES) ?>">
    <label>Fecha <input name="fecha" type="date" required></label>
    <label>Fecha de imputación <input name="fecha_imputacion" type="date">
        <span class="muted">Vacío = la misma. Solo caja/banco.</span>
    </label>
    <label>A/B/C
        <select name="origen" required>
            <option>A</option><option>B</option><option>C</option>
        </select>
    </label>
    <label id="wrap-fisica" hidden>Cuenta de tesorería
        <select name="cuenta_fisica_id" id="sel-fisica" disabled></select>
    </label>
    <label>Iniciales
        <select name="iniciales"><option value=""></option></select>
    </label>
    <label>Concepto
        <select name="concepto_codigo" required></select>
    </label>
    <label>Observaciones <input name="observaciones"></label>
    <label>Cantidad <input name="cantidad" required></label>
    <button type="submit">Añadir</button>
</form>
<p id="msg" class="ok" hidden>Apunte guardado</p>
<p id="err" class="error" hidden></p>
<script>
const CUENTA = <?= json_encode($cuenta) ?>;
document.addEventListener('DOMContentLoaded', async () => {
  const cfg = await api('/api/configuracion');
  const hoy = new Date().toISOString().slice(0,10);
  const cierre = cfg.config.fecha_cierre;
  document.querySelector('[name=fecha]').value = hoy.slice(0,7) === cierre.slice(0,7) ? hoy : cierre;
  const pers = await api('/api/personas');
  const selI = document.querySelector('[name=iniciales]');
  (pers.personas || []).forEach(p => {
    const o = document.createElement('option');
    o.value = p.iniciales;
    o.textContent = p.iniciales + ' — ' + p.nombre_completo;
    selI.appendChild(o);
  });
  const cons = await api('/api/conceptos?cuenta=' + CUENTA);
  const selC = document.querySelector('[name=concepto_codigo]');
  (cons.conceptos || []).forEach(c => {
    const o = document.createElement('option');
    o.value = c.codigo;
    o.textContent = c.etiqueta;
    selC.appendChild(o);
  });
  const tes = await api('/api/tesoreria');
  const fisicas = (tes.cuentas_fisicas || []).filter(f => f.activo);
  const wrapFisica = document.getElementById('wrap-fisica');
  const selFisica = document.getElementById('sel-fisica');
  const selOrigen = document.querySelector('[name=origen]');
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
  selOrigen.addEventListener('change', syncFisica);
  syncFisica();
  document.getElementById('form-apunte').onsubmit = async (ev) => {
    ev.preventDefault();
    document.getElementById('err').hidden = true;
    document.getElementById('msg').hidden = true;
    const s = await api('/api/apuntes', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) {
      document.getElementById('err').hidden = false;
      document.getElementById('err').textContent = s.error;
      return;
    }
    document.getElementById('msg').hidden = false;
    ev.target.observaciones.value = '';
    ev.target.cantidad.value = '';
  };
});
</script>
