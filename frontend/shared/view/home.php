<h1>Inicio</h1>
<p>Contabilidad personal (P) y general (G) del centro. Equivale al programa Secretario del Excel.</p>
<ul class="cards">
    <li><a href="/entrada-p">Entrada de apuntes P</a></li>
    <li><a href="/entrada-g">Entrada de apuntes G</a></li>
    <li><a href="/apuntes">Listado de apuntes</a></li>
    <li><a href="/613-p">Resumen mensual 613 P</a></li>
    <li><a href="/613-g">Resumen mensual 613 G</a></li>
    <li><a href="/cierre">Apuntes de cierre de mes</a></li>
    <li><a href="/comprobaciones">Comprobaciones P / G</a></li>
    <li><a href="/remesas">Remesas personales</a></li>
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
