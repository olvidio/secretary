<section id="sec-destinos">
    <h2><?= _("Destinos del centro") ?></h2>
    <p class="muted"><?= _("Cada centro añade las suyas, del 42 al 54, con su nombre. El 41, Necesidades generales, es fijo y sale en la lista de arriba.") ?></p>
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
<script>
(function () {
  const I18N_DESTINOS = {
    quitar: <?= json_encode(_("Quitar"), JSON_UNESCAPED_UNICODE) ?>,
    guardados: <?= json_encode(_("Destinos guardados."), JSON_UNESCAPED_UNICODE) ?>,
    errorCarga: <?= json_encode(_("No se pudieron cargar los destinos"), JSON_UNESCAPED_UNICODE) ?>,
  };

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
    if (!r.ok) return alert(r.error || I18N_DESTINOS.errorCarga);
    const tb = document.querySelector('#tabla-destinos tbody');
    tb.innerHTML = '';
    (r.partidas || []).forEach((p) => tb.appendChild(filaDestino(p)));
    refrescarBotonDestino();
  }

  async function recargarConceptos() {
    const tb = document.querySelector('#tabla-conceptos tbody');
    if (!tb) return;
    const r = await api('/api/conceptos?cuenta=G');
    if (!r.ok) return;
    tb.innerHTML = '';
    (r.conceptos || []).forEach((c) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${esc(c.codigo)}</td><td>${esc(c.nombre)}</td><td>${esc(c.descripcion)}</td><td>${esc(c.naturaleza)}</td>`;
      tb.appendChild(tr);
    });
  }

  document.addEventListener('DOMContentLoaded', async () => {
    if (!document.getElementById('sec-destinos')) return;
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
      await recargarConceptos();
    });
  });
})();
</script>
