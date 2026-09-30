<?php $cuenta = $cuentaEntrada ?? 'P'; $esClub = !empty($esClub); ?>
<?php if ($esClub && $cuenta === 'G'): ?>
<h1><?= _("Cuentas") ?></h1>
<p><?= !empty($esFundacion)
    ? _("Las cuentas de esta fundación. La plantilla de base se puede cambiar; las cuentas que ya existen se conservan.")
    : _("Las cuentas de esta associació. La plantilla de base se puede cambiar; las cuentas que ya existen se conservan.") ?></p>
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
        <button type="submit"><?= _("Añadir") ?></button>
    </form>
</section>
<section>
    <h2><?= _("Plantilla") ?></h2>
    <form id="form-aplicar">
        <label><?= _("Plan contable base") ?>
            <select name="codigo" id="sel-plantilla"></select>
        </label>
        <button type="submit"><?= _("Aplicar") ?></button>
    </form>
    <form id="form-plantilla">
        <label><?= _("Nombre de la plantilla") ?> <input name="nombre" required></label>
        <button type="submit"><?= _("Guardar como plantilla") ?></button>
    </form>
</section>
<table id="tabla-cuentas">
    <thead><tr><th><?= _("Código") ?></th><th><?= _("Nombre") ?></th><th><?= _("Naturaleza") ?></th></tr></thead>
    <tbody></tbody>
</table>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const tb = document.querySelector('#tabla-cuentas tbody');
  const sel = document.getElementById('sel-plantilla');
  function pintar(cuentas) {
    tb.replaceChildren();
    (cuentas || []).forEach(c => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${esc(c.codigo)}</td><td>${esc(c.nombre)}</td><td>${esc(c.naturaleza_plan)}</td>`;
      tb.appendChild(tr);
    });
  }
  function plantillas(lista, actual) {
    sel.replaceChildren();
    (lista || []).forEach(p => {
      const o = document.createElement('option');
      o.value = p.codigo;
      o.textContent = p.nombre;
      if (p.codigo === actual) o.selected = true;
      sel.appendChild(o);
    });
  }
  api('/api/club/cuentas').then(r => {
    if (!r.ok) { alert(r.error || 'Error'); return; }
    pintar(r.cuentas);
    plantillas(r.plantillas, r.plan);
  });
  document.getElementById('form-cuenta').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const f = new FormData(ev.target);
    const r = await api('/api/club/cuentas', { method: 'POST', body: {
      codigo: f.get('codigo'), nombre: f.get('nombre'), naturaleza: f.get('naturaleza'),
    }});
    if (!r.ok) { alert(r.error || 'Error'); return; }
    pintar(r.cuentas);
    ev.target.reset();
  });
  document.getElementById('form-aplicar').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const r = await api('/api/club/plantillas/aplicar', { method: 'POST', body: {
      codigo: sel.value,
    }});
    if (!r.ok) { alert(r.error || 'Error'); return; }
    pintar(r.cuentas);
    plantillas(r.plantillas, r.plan);
  });
  document.getElementById('form-plantilla').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const r = await api('/api/club/plantillas', { method: 'POST', body: {
      nombre: new FormData(ev.target).get('nombre'),
    }});
    if (!r.ok) { alert(r.error || 'Error'); return; }
    plantillas(r.plantillas, null);
    ev.target.reset();
  });
});
</script>
<?php else: ?>
<h1><?= sprintf(_("Conceptos %s"), htmlspecialchars($cuenta, ENT_QUOTES)) ?></h1>
<table id="tabla-conceptos">
    <thead><tr><th><?= _("Código") ?></th><th><?= _("Nombre") ?></th><th><?= _("Descripción") ?></th><th><?= _("Naturaleza") ?></th></tr></thead>
    <tbody></tbody>
</table>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/conceptos?cuenta=' + <?= json_encode($cuenta) ?>);
  const tb = document.querySelector('#tabla-conceptos tbody');
  (r.conceptos || []).forEach(c => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${esc(c.codigo)}</td><td>${esc(c.nombre)}</td><td>${esc(c.descripcion)}</td><td>${esc(c.naturaleza)}</td>`;
    tb.appendChild(tr);
  });
});
</script>
<?php endif; ?>
