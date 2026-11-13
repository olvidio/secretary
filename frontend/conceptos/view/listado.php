<?php
$cuenta = $cuentaEntrada ?? 'P';
$esClub = !empty($esClub);
$esCentroSg = !empty($esCentroSg);
?>
<?php if ($esClub && $cuenta === 'G'): ?>
<h1><?= _("Cuentas") ?></h1>
<p><?= !empty($esFundacion)
    ? _("Las cuentas de esta fundación. Se pueden cambiar el código, el nombre, la naturaleza y el código oficial de cada una: escriba en la fila y pulse Guardar. Al cambiar un código, sus movimientos pasan al código nuevo.")
    : _("Las cuentas de esta associació. Se pueden cambiar el código, el nombre, la naturaleza y el código oficial de cada una: escriba en la fila y pulse Guardar. Al cambiar un código, sus movimientos pasan al código nuevo.") ?></p>
<section>
    <h2><?= _("Nueva cuenta") ?></h2>
    <form id="form-cuenta">
        <label><?= _("Código") ?> <input name="codigo" required maxlength="16"></label>
        <label><?= _("Nombre") ?> <input name="nombre" required></label>
        <label><?= _("Naturaleza") ?>
            <select name="naturaleza">
                <option value="gasto"><?= _("Gasto") ?></option>
                <option value="ingreso"><?= _("Ingreso") ?></option>
            </select>
        </label>
        <label><?= _("Código oficial") ?> <input name="codigo_oficial" maxlength="4" inputmode="numeric" placeholder="640"></label>
        <label class="cuenta-fila"><input type="checkbox" name="grupo"> <?= _("Es un grupo (título sin movimientos, como 61)") ?></label>
        <button type="submit"><?= _("Añadir") ?></button>
    </form>
</section>
<section>
    <h2><?= _("Modelo de plan de cuentas") ?></h2>
    <p class="muted"><?= _("Un modelo es una lista de cuentas para empezar. Solo se usa para copiar cuentas: una vez copiadas son de este centro y el modelo no influye en nada más, así que se puede eliminar.") ?></p>
    <form id="form-aplicar">
        <label><?= _("Modelo") ?>
            <select name="codigo" id="sel-modelo"></select>
        </label>
        <button type="submit"><?= _("Añadir las cuentas que falten") ?></button>
        <button type="button" id="btn-borrar-modelo" disabled><?= _("Eliminar modelo") ?></button>
    </form>
    <form id="form-modelo">
        <label><?= _("Nombre del modelo") ?> <input name="nombre" required></label>
        <button type="submit"><?= _("Guardar mis cuentas como modelo") ?></button>
    </form>
    <p id="msg-modelo" class="ok" hidden aria-live="polite"></p>
</section>
<p class="muted"><?= _("El código oficial es la cuenta del Pla de comptabilitat de les associacions de Catalunya (por ejemplo 640 sueldos, 642 Seguridad Social, 628 suministros) a la que corresponde cada cuenta propia. Los grupos no llevan. Las cuentas que usa el programa por su nombre (saldo inicial, por categorizar, otra contabilidad) no cambian de código. Al cambiar el código de un grupo, sus hijas cambian con él (61 → 65 lleva 61.1 a 65.1).") ?></p>
<table id="tabla-cuentas">
    <thead><tr><th><?= _("Código") ?></th><th><?= _("Nombre") ?></th><th><?= _("Naturaleza") ?></th><th><?= _("Código oficial") ?></th><th></th></tr></thead>
    <tbody></tbody>
</table>
<script>
const I18N_CUENTAS = {
  guardar: <?= json_encode(_("Guardar"), JSON_UNESCAPED_UNICODE) ?>,
  gasto: <?= json_encode(_("Gasto"), JSON_UNESCAPED_UNICODE) ?>,
  ingreso: <?= json_encode(_("Ingreso"), JSON_UNESCAPED_UNICODE) ?>,
  anadidas: <?= json_encode(_("Cuentas añadidas: %s. Las que ya existían no se han tocado."), JSON_UNESCAPED_UNICODE) ?>,
  modeloGuardado: <?= json_encode(_("Modelo guardado."), JSON_UNESCAPED_UNICODE) ?>,
  modeloBorrado: <?= json_encode(_("Modelo eliminado."), JSON_UNESCAPED_UNICODE) ?>,
  confirmarBorrar: <?= json_encode(_("¿Eliminar el modelo «%s»? Las cuentas de los centros no cambian."), JSON_UNESCAPED_UNICODE) ?>,
};
document.addEventListener('DOMContentLoaded', () => {
  const tb = document.querySelector('#tabla-cuentas tbody');
  const sel = document.getElementById('sel-modelo');
  const btnBorrarModelo = document.getElementById('btn-borrar-modelo');
  const msgModelo = document.getElementById('msg-modelo');
  let listaModelos = [];
  function avisarModelo(texto) { msgModelo.textContent = texto; msgModelo.hidden = false; }
  function pintar(cuentas) {
    tb.replaceChildren();
    (cuentas || []).forEach(c => {
      const tr = document.createElement('tr');
      tr.dataset.codigo = c.codigo;
      if (!c.imputable) tr.className = 'grupo';
      const fija = c.fija ? ' disabled' : '';
      const natFija = c.fija || c.tipo === 'patrimonio';
      const opcion = (v, texto) => `<option value="${v}"${c.tipo === v ? ' selected' : ''}>${esc(texto)}</option>`;
      tr.innerHTML = `<td><input name="codigo" value="${esc(c.codigo)}" maxlength="16" size="9"${fija}></td>`
        + `<td><input name="nombre" value="${esc(c.nombre)}"></td>`
        + (natFija
          ? `<td>${esc(c.naturaleza_plan)}</td>`
          : `<td><select name="naturaleza">${opcion('gasto', I18N_CUENTAS.gasto)}${opcion('ingreso', I18N_CUENTAS.ingreso)}</select></td>`)
        + (c.imputable
          ? `<td><input name="codigo_oficial" value="${esc(c.codigo_oficial || '')}" maxlength="4" inputmode="numeric" size="5"></td>`
          : '<td></td>')
        + `<td class="acc"><button type="button" data-act="guardar" disabled>${esc(I18N_CUENTAS.guardar)}</button></td>`;
      tb.appendChild(tr);
    });
  }
  // El botón de la fila se activa en cuanto se toca algo de ella.
  tb.addEventListener('input', (ev) => {
    const tr = ev.target.closest('tr');
    if (tr) tr.querySelector('button[data-act=guardar]').disabled = false;
  });
  tb.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('button[data-act=guardar]');
    if (!btn) return;
    const tr = btn.closest('tr');
    const valor = (n) => { const el = tr.querySelector('[name=' + n + ']'); return el ? el.value.trim() : null; };
    const body = {
      codigo: tr.dataset.codigo,
      nuevo_codigo: valor('codigo'),
      nombre: valor('nombre'),
      naturaleza: valor('naturaleza'),
      codigo_oficial: valor('codigo_oficial'),
    };
    let r = await api('/api/club/cuentas/modificar', { method: 'POST', body });
    if (r.ok && r.pendiente) {
      // Cambio de código: se avisa de a qué afecta antes de hacerlo.
      if (!confirm(r.aviso)) return;
      r = await api('/api/club/cuentas/modificar', { method: 'POST', body: Object.assign({ confirmado: true }, body) });
    }
    if (!r.ok) { alert(r.error || 'Error'); return; }
    pintar(r.cuentas);
  });
  function modelos(lista) {
    listaModelos = lista || [];
    const elegido = sel.value;
    sel.replaceChildren();
    listaModelos.forEach(p => {
      const o = document.createElement('option');
      o.value = p.codigo;
      o.textContent = p.nombre;
      sel.appendChild(o);
    });
    if (listaModelos.some(p => p.codigo === elegido)) sel.value = elegido;
    sincronizarBorrar();
  }
  // El modelo de base (el plan del centro) no se elimina; los guardados sí.
  function sincronizarBorrar() {
    const m = listaModelos.find(p => p.codigo === sel.value);
    btnBorrarModelo.disabled = !(m && m.borrable);
  }
  sel.addEventListener('change', sincronizarBorrar);
  btnBorrarModelo.addEventListener('click', async () => {
    const m = listaModelos.find(p => p.codigo === sel.value);
    if (!m || !confirm(I18N_CUENTAS.confirmarBorrar.replace('%s', m.nombre))) return;
    const r = await api('/api/club/modelos/borrar', { method: 'POST', body: { codigo: m.codigo } });
    if (!r.ok) { alert(r.error || 'Error'); return; }
    modelos(r.modelos);
    avisarModelo(I18N_CUENTAS.modeloBorrado);
  });
  api('/api/club/cuentas').then(r => {
    if (!r.ok) { alert(r.error || 'Error'); return; }
    pintar(r.cuentas);
    modelos(r.modelos);
  });
  document.getElementById('form-cuenta').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const f = new FormData(ev.target);
    const r = await api('/api/club/cuentas', { method: 'POST', body: {
      codigo: f.get('codigo'), nombre: f.get('nombre'), naturaleza: f.get('naturaleza'),
      codigo_oficial: f.get('codigo_oficial'), grupo: f.get('grupo') === 'on',
    }});
    if (!r.ok) { alert(r.error || 'Error'); return; }
    pintar(r.cuentas);
    ev.target.reset();
  });
  document.getElementById('form-aplicar').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const r = await api('/api/club/modelos/aplicar', { method: 'POST', body: { codigo: sel.value } });
    if (!r.ok) { alert(r.error || 'Error'); return; }
    pintar(r.cuentas);
    modelos(r.modelos);
    avisarModelo(I18N_CUENTAS.anadidas.replace('%s', r.anadidas));
  });
  document.getElementById('form-modelo').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const r = await api('/api/club/modelos', { method: 'POST', body: {
      nombre: new FormData(ev.target).get('nombre'),
    }});
    if (!r.ok) { alert(r.error || 'Error'); return; }
    modelos(r.modelos);
    ev.target.reset();
    avisarModelo(I18N_CUENTAS.modeloGuardado);
  });
});
</script>
<?php else: ?>
<h1><?= sprintf(_("Conceptos %s"), htmlspecialchars($cuenta, ENT_QUOTES)) ?></h1>
<?php if ($esCentroSg && $cuenta === 'G'): ?>
<p class="muted"><?= _("Lista de consulta del plan. Los destinos 42–54 se nombran abajo; el 41 es fijo.") ?></p>
<?php endif; ?>
<table id="tabla-conceptos">
    <thead><tr><th><?= _("Código") ?></th><th><?= _("Nombre") ?></th><th><?= _("Descripción") ?></th><th><?= _("Naturaleza") ?></th></tr></thead>
    <tbody></tbody>
</table>
<?php if ($esCentroSg && $cuenta === 'G'): ?>
<?php include __DIR__ . '/_destinos_centro_sg.php'; ?>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/conceptos?cuenta=' + <?= json_encode($cuenta) ?>);
  if (!r.ok) { alert(r.error || 'Error'); return; }
  const tb = document.querySelector('#tabla-conceptos tbody');
  (r.conceptos || []).forEach(c => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${esc(c.codigo)}</td><td>${esc(c.nombre)}</td><td>${esc(c.descripcion)}</td><td>${esc(c.naturaleza)}</td>`;
    tb.appendChild(tr);
  });
});
</script>
<?php endif; ?>
