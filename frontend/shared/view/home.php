<?php $esClub = !empty($esClub); $esFundacion = !empty($esFundacion); $esCentroSg = !empty($esCentroSg); ?>
<h1><?= _("Inicio") ?></h1>
<?php if ($esCentroSg): ?>
<p><?= _("Contabilidad de un centro sg: un solo libro, nombres, presupuesto y resumen 613, como el Excel Secretario sg.") ?></p>
<ul class="cards">
    <li><a href="/nombres"><?= _("Nombres") ?></a></li>
    <li><a href="/entrada-g"><?= _("Entrada") ?></a></li>
    <li><a href="/plantillas-g"><?= _("Plantillas") ?></a></li>
    <li><a href="/apuntes"><?= _("Listado de apuntes") ?></a></li>
    <li><a href="/presupuesto-g"><?= _("Presupuesto") ?></a></li>
    <li><a href="/613-g"><?= _("613") ?></a></li>
    <li><a href="/aportaciones"><?= _("Listado de aportaciones") ?></a></li>
    <li><a href="/arqueo-g"><?= _("Arqueo") ?></a></li>
</ul>
<?php elseif ($esClub): ?>
<p><?= $esFundacion
    ? _("Contabilidad de la fundación: un solo libro, caja y banco, importación Grisbi y listados.")
    : _("Contabilidad de la associació: un solo libro, caja y banco, importación Grisbi y listados.") ?></p>
<ul class="cards">
    <li><a href="/entrada-g"><?= _("Entrada") ?></a></li>
    <li><a href="/apuntes"><?= _("Apuntes") ?></a></li>
    <li><a href="/listados"><?= _("Listados") ?></a></li>
    <li><a href="/tesoreria"><?= _("Tesorería") ?></a></li>
    <li><a href="/saldos"><?= _("Saldos") ?></a></li>
</ul>
<?php else: ?>
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
<?php endif; ?>
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
