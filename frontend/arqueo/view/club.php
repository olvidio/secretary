<?php if (empty($esClub)): ?>
<?php return; ?>
<?php endif; ?>
<h1><?= _("Arqueo") ?></h1>
<p class="muted"><?= _("Recuente caja y banco hasta una fecha. Si cuadra, márquela: un cambio posterior de apuntes que mueva ese saldo avisará del descuadre.") ?></p>
<form id="form-arqueo" class="filters">
    <label><?= _("Fecha") ?> <input type="date" name="fecha" required></label>
</form>
<table id="tabla-arqueo">
    <thead>
    <tr>
        <th><?= _("Tesorería") ?></th>
        <th class="num"><?= _("Saldo contable") ?></th>
        <th class="num"><?= _("Contado") ?></th>
        <th class="num"><?= _("Diferencia") ?></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<p><button type="button" id="btn-cuadrar" disabled><?= _("Dar por cuadrado") ?></button></p>
<h2><?= _("Periodos cuadrados") ?></h2>
<ul id="lista-cuadrados"></ul>
<script>
const I18N_ARQ = {
  cuadra: <?= json_encode(_("cuadra"), JSON_UNESCAPED_UNICODE) ?>,
  descuadre: <?= json_encode(_("descuadre"), JSON_UNESCAPED_UNICODE) ?>,
  ninguno: <?= json_encode(_("Todavía no hay ningún periodo cuadrado."), JSON_UNESCAPED_UNICODE) ?>,
  guardado: <?= json_encode(_("Periodo cuadrado."), JSON_UNESCAPED_UNICODE) ?>,
};
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('form-arqueo');
  const tb = document.querySelector('#tabla-arqueo tbody');
  const btn = document.getElementById('btn-cuadrar');
  form.fecha.value = new Date().toISOString().slice(0, 10);
  let tesoreria = [];
  function centsDe(texto) {
    const s = String(texto || '').trim();
    if (!s) return null;
    const n = s.includes(',') ? Number(s.replace(/\./g, '').replace(',', '.')) : Number(s);
    return Number.isFinite(n) ? Math.round(n * 100) : null;
  }
  function pintar(cuadrados) {
    const previos = {};
    tb.querySelectorAll('input').forEach(input => { previos[input.dataset.id] = input.value; });
    tb.replaceChildren();
    let cuadra = tesoreria.length > 0;
    tesoreria.forEach(t => {
      const escrito = previos[t.id] || '';
      const contado = centsDe(escrito);
      const dif = contado === null ? null : contado - t.saldo_cents;
      if (dif !== 0) cuadra = false;
      const tr = document.createElement('tr');
      const contadoTd = document.createElement('td');
      contadoTd.className = 'num';
      const input = document.createElement('input');
      input.id = 'contado-' + t.id;
      input.dataset.id = t.id;
      input.value = escrito;
      input.addEventListener('input', () => pintar(cuadrados));
      contadoTd.appendChild(input);
      tr.innerHTML = '<td>' + esc(t.nombre) + '</td><td class="num">' + esc(fmtImporteEs(t.saldo_cents / 100))
        + '</td><td class="num"></td><td class="num">' + (dif === null ? '' : esc(fmtImporteEs(dif / 100))) + '</td>';
      tr.children[2].replaceWith(contadoTd);
      tb.appendChild(tr);
    });
    btn.disabled = !cuadra;
    const ul = document.getElementById('lista-cuadrados');
    ul.replaceChildren();
    if (!cuadrados.length) {
      const li = document.createElement('li');
      li.textContent = I18N_ARQ.ninguno;
      ul.appendChild(li);
      return;
    }
    cuadrados.forEach(c => {
      const li = document.createElement('li');
      const fecha = c.fecha.slice(8, 10) + '/' + c.fecha.slice(5, 7) + '/' + c.fecha.slice(0, 4);
      li.textContent = fecha + ' — ' + (c.cuadra ? I18N_ARQ.cuadra : (c.avisos || []).join(' '));
      if (!c.cuadra) li.className = 'error';
      ul.appendChild(li);
    });
  }
  async function cargar() {
    const r = await api('/api/arqueo-club?fecha=' + encodeURIComponent(form.fecha.value));
    if (!r.ok) { alert(r.error || 'Error'); return; }
    tesoreria = r.tesoreria || [];
    pintar(r.cuadrados || []);
  }
  form.fecha.addEventListener('change', cargar);
  btn.addEventListener('click', async () => {
    const contados = {};
    tesoreria.forEach(t => { contados[t.id] = centsDe(document.getElementById('contado-' + t.id).value); });
    const r = await api('/api/arqueo-club/cuadrar', { method: 'POST', body: { fecha: form.fecha.value, contados } });
    if (!r.ok) { alert(r.error || 'Error'); return; }
    alert(I18N_ARQ.guardado);
    cargar();
  });
  cargar();
});
</script>
