<h1><?= _("Inicio") ?></h1>
<p><?= _("Contabilidad personal (P) y general (G) del centro. Equivale al programa Secretario del Excel.") ?></p>
<ul class="cards">
    <li><a href="/entrada-p"><?= _("Entrada de apuntes P") ?></a></li>
    <li><a href="/entrada-g"><?= _("Entrada de apuntes G") ?></a></li>
    <li><a href="/apuntes"><?= _("Listado de apuntes") ?></a></li>
    <li><a href="/613-p"><?= _("Resumen mensual 613 P") ?></a></li>
    <li><a href="/613-g"><?= _("Resumen mensual 613 G") ?></a></li>
    <li><a href="/cierre"><?= _("Apuntes de cierre de mes") ?></a></li>
    <li><a href="/comprobaciones"><?= _("Comprobaciones P / G") ?></a></li>
    <li><a href="/remesas"><?= _("Remesas personales") ?></a></li>
</ul>
<p id="home-meta" class="muted"></p>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/centros');
  if (r.ok && r.centro) {
    const c = r.centro;
    document.getElementById('home-meta').textContent = c.nombre || c.codigo || '';
  }
});
</script>
