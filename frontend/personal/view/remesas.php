<div class="yo-month">
    <button type="button" id="yo-mes-ant" aria-label="Mes anterior">‹</button>
    <h1 id="yo-mes-titulo">Mes</h1>
    <button type="button" id="yo-mes-sig" aria-label="Mes siguiente">›</button>
</div>
<p class="muted">Envío mensual al centro. La caja y el banco propios no viajan.</p>
<p id="yo-remesa-msg" class="ok" hidden></p>
<p id="yo-remesa-err" class="error" hidden></p>
<section class="yo-remesa-resumen">
    <p id="yo-remesa-estado" class="muted">Cargando…</p>
    <ul id="yo-remesa-lineas" class="yo-lista"></ul>
    <p id="yo-remesa-vacia" class="muted" hidden>No hay ingresos ni gastos en este mes (sí se puede enviar para anular una remesa ya aceptada).</p>
</section>
<p>
    <label>Nota para el centro <input id="yo-remesa-nota" maxlength="200"></label>
</p>
<p>
    <button type="button" id="yo-remesa-enviar">Cerrar y enviar mes</button>
</p>
<section>
    <h2>Historial de este mes</h2>
    <ul id="yo-remesa-hist" class="yo-lista"></ul>
</section>
<section>
    <h2>Solicitudes de detalle</h2>
    <ul id="yo-remesa-sols" class="yo-lista"></ul>
    <p id="yo-remesa-sols-vacia" class="muted">No hay solicitudes pendientes.</p>
</section>
