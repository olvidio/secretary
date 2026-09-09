<h1>Categorías</h1>
<p class="muted">Las subcuentas consolidan en el plan del centro por su código maestro. No se puede inventar un código que no exista ahí.</p>
<form id="yo-cat-form" class="yo-cat-form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <label>Plan maestro
        <select name="codigo_maestro" required></select>
    </label>
    <label>Etiqueta <input name="codigo" required maxlength="20" placeholder="gas, luz…"></label>
    <label>Nombre <input name="nombre" required placeholder="Gas de casa"></label>
    <button type="submit">Añadir subcuenta</button>
</form>
<p id="yo-cat-msg" class="ok" hidden></p>
<p id="yo-cat-err" class="error" hidden></p>
<ul id="yo-cat-list" class="yo-cat-list"></ul>
