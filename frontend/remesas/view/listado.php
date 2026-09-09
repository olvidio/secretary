<h1>Remesas</h1>
<p class="muted">Envíos mensuales de las personas. Aceptar genera un asiento en P contra la cuenta personal; rechazar no deja rastro. Reenviar el mismo mes sustituye la versión pendiente.</p>
<form id="filtros-remesas" class="filters">
    <label>Estado
        <select name="estado">
            <option value="">Todas</option>
            <option value="enviada" selected>Enviadas</option>
            <option value="aceptada">Aceptadas</option>
            <option value="rechazada">Rechazadas</option>
            <option value="sustituida">Sustituidas</option>
        </select>
    </label>
    <button type="submit">Filtrar</button>
</form>
<p id="remesas-err" class="error" hidden></p>
<table id="tabla-remesas">
    <thead>
    <tr>
        <th>Mes</th>
        <th>Persona</th>
        <th>V.</th>
        <th>Estado</th>
        <th class="num">Importe</th>
        <th>Enviada</th>
        <th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<section id="remesa-detalle" hidden>
    <h2 id="remesa-detalle-titulo">Detalle</h2>
    <p id="remesa-detalle-meta" class="muted"></p>
    <table id="tabla-remesa-lineas">
        <thead>
        <tr><th>Concepto</th><th class="num">Importe</th><th>Detalle</th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <div id="remesa-diff" hidden>
        <h3>Diferencia respecto a la versión anterior</h3>
        <ul id="remesa-diff-list"></ul>
    </div>
    <p id="remesa-acciones">
        <button type="button" id="remesa-aceptar">Aceptar</button>
        <button type="button" id="remesa-rechazar">Rechazar</button>
    </p>
    <label>Nota al rechazar <input id="remesa-nota" maxlength="200"></label>
</section>
<script src="/js/remesas.js"></script>
