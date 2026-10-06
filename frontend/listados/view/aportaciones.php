<style>
@page { size: A4 landscape; margin: 8mm; }
</style>
<h1 class="print-hide"><?= _("Listado de aportaciones") ?></h1>
<p class="muted print-hide"><?= _("Como la hoja E 32 del Excel: cada s, por grupo, con la aportación ordinaria (11) y la extraordinaria (12) de cada mes. Debajo, las ayudas de los cp (13). Solo entran los nombres con grupo y clase s o cp.") ?></p>
<p class="filters print-hide">
    <button type="button" id="btn-imprimir-aportaciones" hidden><?= _("Imprimir") ?></button>
</p>
<div id="listado-aportaciones"></div>
<p class="muted print-hide" id="vacio" hidden><?= _("No hay nombres clasificados. En Nombres indique el grupo y si es s o cp.") ?></p>
<script>
const MESES = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
function celdas(linea) {
  return (linea.meses || []).map((v) => '<td class="num">' + esc(v) + '</td>').join('')
    + '<td class="num">' + esc(linea.total || '') + '</td>';
}
function tablaGrupo(g) {
  let html = '<section class="aportaciones-grupo">';
  html += '<h2>' + esc(<?= json_encode(_("Grupo"), JSON_UNESCAPED_UNICODE) ?>) + ' ' + esc(String(g.grupo)) + '</h2>';
  html += '<table class="listado-sg"><thead><tr><th></th><th></th>';
  MESES.forEach((m) => { html += '<th>' + m + '</th>'; });
  html += '<th>total</th></tr></thead><tbody>';
  (g.personas || []).forEach((p) => {
    html += '<tr><td>' + esc(p.nombre) + '</td><td>ordinaria</td>' + celdas(p.ordinaria) + '</tr>';
    html += '<tr><td></td><td>extraordinaria</td>' + celdas(p.extraordinaria) + '</tr>';
  });
  html += '<tr><td><strong>TOTAL s del grupo</strong></td><td>ordinaria</td>' + celdas(g.total_ordinaria) + '</tr>';
  html += '<tr><td></td><td>extraordinaria</td>' + celdas(g.total_extraordinaria) + '</tr>';
  html += '</tbody></table></section>';
  return html;
}
document.addEventListener('DOMContentLoaded', async () => {
  const btnPrint = document.getElementById('btn-imprimir-aportaciones');
  btnPrint.addEventListener('click', () => {
    document.body.classList.add('aportaciones-hoja');
    window.print();
  });
  window.addEventListener('afterprint', () => document.body.classList.remove('aportaciones-hoja'));

  const r = await api('/api/aportaciones-sg');
  const box = document.getElementById('listado-aportaciones');
  if (!r.ok) {
    box.textContent = r.error || 'Error';
    return;
  }
  const grupos = r.grupos || [];
  const cp = r.cp || [];
  if (grupos.length === 0 && cp.length === 0) {
    document.getElementById('vacio').hidden = false;
    return;
  }
  let html = '';
  grupos.forEach((g) => { html += tablaGrupo(g); });
  if (cp.length) {
    html += '<section class="aportaciones-grupo aportaciones-grupo-cp">';
    html += '<h2>Ayudas (cp)</h2><table class="listado-sg"><thead><tr><th></th><th>grupo</th>';
    MESES.forEach((m) => { html += '<th>' + m + '</th>'; });
    html += '<th>total</th></tr></thead><tbody>';
    cp.forEach((p) => {
      html += '<tr><td>' + esc(p.nombre) + '</td><td>' + esc(String(p.grupo)) + '</td>' + celdas(p.meses) + '</tr>';
    });
    html += '<tr><td><strong>TOTAL cp</strong></td><td></td>' + celdas(r.total_cp) + '</tr>';
    html += '</tbody></table></section>';
  }
  box.innerHTML = html;
  btnPrint.hidden = false;
});
</script>
