<?php $esLibroPersonal = (($_SESSION['nivel'] ?? '') === 'persona'); ?>
<h1><?= _("Personal") ?></h1>
<nav class="cuenta-tabs" id="cuenta-tabs">
    <a href="#mail" data-tab="mail"><?= _("Mail") ?></a>
    <a href="#password" data-tab="password"><?= _("Contraseña") ?></a>
    <a href="#totp" data-tab="totp"><?= _("2FA") ?></a>
    <a href="#layout" data-tab="layout"><?= _("Layout") ?></a>
    <a href="#idioma" data-tab="idioma"><?= _("Idioma") ?></a>
    <?php if ($esLibroPersonal): ?>
    <a href="#centros" data-tab="centros"><?= _("Centros") ?></a>
    <a href="#copias" data-tab="copias"><?= _("Copia") ?></a>
    <?php endif; ?>
</nav>

<section id="mail" class="cuenta-seccion">
    <h2><?= _("Mail") ?></h2>
    <p class="muted"><?= _("Correo de esta cuenta. Sirve para entrar y, si está vinculada a un nombre, también es el del libro personal.") ?></p>
    <p class="muted" id="aviso-pendiente" hidden></p>
    <form id="form-mail" class="grid-form">
        <label><?= _("Correo") ?> <input name="email" type="email" required autocomplete="email"></label>
        <button type="submit"><?= _("Guardar") ?></button>
        <p class="ok" id="msg-mail" hidden></p>
    </form>
</section>

<section id="password" class="cuenta-seccion">
    <h2><?= _("Contraseña") ?></h2>
    <p class="muted"><?= _("Cambie la contraseña de esta cuenta. Hace falta la actual para confirmar.") ?></p>
    <form id="form-password" class="grid-form">
        <label><?= _("Actual") ?> <input name="password_actual" type="password" required autocomplete="current-password"></label>
        <label><?= _("Nueva") ?> <input name="password" type="password" required autocomplete="new-password" minlength="6"></label>
        <label><?= _("Repetir nueva") ?> <input name="password_confirm" type="password" required autocomplete="new-password" minlength="6"></label>
        <button type="submit"><?= _("Guardar") ?></button>
        <p class="ok" id="msg-password" hidden><?= _("Contraseña actualizada") ?></p>
    </form>
</section>

<section id="totp" class="cuenta-seccion">
    <h2><?= _("Segundo factor") ?></h2>
    <p class="muted"><?= _("Autenticación TOTP (Google Authenticator, Aegis, etc.). Obligatorio para secretarios de centro; opcional en el libro personal.") ?></p>
    <p id="totp-estado" class="ok" hidden><?= _("El segundo factor está activo.") ?></p>
    <div id="totp-pendiente">
        <p id="totp-inactivo" class="muted"><?= _("Todavía no tiene segundo factor en esta cuenta.") ?></p>
        <button type="button" id="btn-totp-preparar"><?= _("Activar segundo factor") ?></button>
        <div id="totp-setup" hidden>
            <p><?= _("Escanea el código QR con tu aplicación de autenticación y confirma con un código de 6 dígitos.") ?></p>
            <div id="totp-qr" class="totp-qr"></div>
            <details class="totp-manual">
                <summary><?= _("Introducir clave manualmente") ?></summary>
                <p class="muted totp-secret"><strong id="totp-secreto"></strong></p>
            </details>
            <form id="form-totp" class="grid-form">
                <label><?= _("Código") ?> <input name="codigo" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></label>
                <button type="submit"><?= _("Confirmar") ?></button>
            </form>
        </div>
    </div>
    <div id="totp-codigos" hidden>
        <p><strong><?= _("Guarde estos códigos de recuperación.") ?></strong> <?= _("Cada uno sirve una sola vez si pierde el autenticador. No se volverán a mostrar.") ?></p>
        <ul id="totp-codigos-lista"></ul>
    </div>
</section>

<section id="layout" class="cuenta-seccion">
    <h2><?= _("Layout") ?></h2>
    <p class="muted"><?= _("Disposición de los menús del centro (tipo excel o burger). El libro personal no cambia.") ?></p>
    <form id="form-layout" class="grid-form">
        <label class="cuenta-tipo-op"><input type="radio" name="layout" value="excel" required> <?= _("Tipo excel") ?></label>
        <label class="cuenta-tipo-op"><input type="radio" name="layout" value="burger"> <?= _("Burger") ?></label>
        <button type="submit"><?= _("Guardar") ?></button>
    </form>
</section>

<section id="idioma" class="cuenta-seccion">
    <h2><?= _("Idioma") ?></h2>
    <p class="muted"><?= _("Elige el idioma de la interfaz: español o catalán.") ?></p>
    <form id="form-idioma" class="grid-form">
        <label><?= _("Idioma") ?>
            <select name="idioma">
                <option value="es"><?= _("Español") ?></option>
                <option value="ca">Català</option>
            </select>
        </label>
        <button type="submit"><?= _("Guardar") ?></button>
    </form>
</section>

<?php if ($esLibroPersonal): ?>
<section id="centros" class="cuenta-seccion">
    <h2><?= _("Centros") ?></h2>
    <p class="muted"><?= _("Vincula tu cuenta personal con un centro n (un solo centro a la vez). El secretario debe aprobar la petición; hasta entonces sigues usando tu libro propio, pero no podrás enviar el resumen mensual al centro.") ?></p>
    <p class="muted"><?= _("Los centros sg, las asociaciones y las fundaciones no salen aquí. Si ya estás vinculado, puedes desvincularte para pedir acceso a otro centro n más adelante.") ?></p>

    <div id="estado-vinculado" hidden>
        <h3><?= _("Centro vinculado") ?></h3>
        <dl class="datos-centro" id="datos-vinculo"></dl>
        <p><button type="button" id="btn-desvincular" class="peligro"><?= _("Desvincular") ?></button></p>
    </div>

    <div id="estado-pendiente" hidden>
        <h3><?= _("Solicitud pendiente") ?></h3>
        <dl class="datos-centro" id="datos-solicitud"></dl>
        <p class="muted"><?= _("Espere la aprobación del secretario del centro.") ?></p>
    </div>

    <div id="estado-solicitar" hidden>
        <h3><?= _("Solicitar acceso") ?></h3>
        <form id="form-solicitud" class="grid-form">
            <label><?= _("Centro n") ?>
                <select name="centro_id" required></select>
            </label>
            <label><?= _("Año del ejercicio") ?> <input name="anio" type="number" min="2000" max="2100" required></label>
            <label><?= _("Mensaje") ?> <input name="mensaje" placeholder="<?= htmlspecialchars(_("Opcional"), ENT_QUOTES) ?>"></label>
            <div id="bloque-vincular-propio" hidden>
                <label><?= _("Su nombre en el centro") ?>
                    <select name="persona_id" id="sel-nombre-propio"></select>
                </label>
                <p class="muted"><?= _("Como secretario de este centro, elija el nombre de Nombres y vincúlelo con esta cuenta personal. No hace falta una solicitud.") ?></p>
                <button type="button" id="btn-vincular-propio"><?= _("Vincular este nombre") ?></button>
            </div>
            <button type="submit" id="btn-enviar-solicitud"><?= _("Enviar solicitud") ?></button>
        </form>
        <p id="err-sol" class="error" hidden></p>
        <p id="ok-sol" class="ok" hidden></p>
    </div>
</section>

<section id="copias" class="cuenta-seccion">
    <h2><?= _("Copia del libro personal") ?></h2>
    <p class="muted"><?= _("Exporta y restaura solo tus movimientos del libro X (categorías propias, extracto banco y fechas de cierre). No afecta al centro ni a las remesas ya aceptadas allí.") ?></p>
    <h3><?= _("Nueva copia") ?></h3>
    <p class="muted"><?= _("Genera un fichero JSON con todos tus movimientos personales.") ?></p>
    <button type="button" id="btn-backup-personal"><?= _("Crear copia ahora") ?></button>
    <p class="peligro" id="aviso-limite-copias-personal" hidden><?= _("Solo se permite tener 5 copias en el servidor") ?></p>
    <button type="button" id="btn-backup-reemplazar-personal" hidden><?= _("Borrar la más antigua y guardar") ?></button>
    <p class="ok" id="msg-backup-personal" hidden></p>
    <h3><?= _("Copias guardadas") ?></h3>
    <div class="tabla-scroll">
        <table id="tabla-copias-personal" class="yo-copias-table">
            <colgroup>
                <col class="col-fichero">
                <col class="col-fecha">
                <col class="col-bytes">
                <col class="col-acc">
            </colgroup>
            <thead>
            <tr><th><?= _("Fichero") ?></th><th><?= _("Fecha") ?></th><th><?= _("Tamaño") ?></th><th><?= _("Acciones") ?></th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <p class="muted" id="sin-copias-personal" hidden><?= _("Aún no hay copias guardadas.") ?></p>
    <h3><?= _("Restaurar") ?></h3>
    <p class="muted peligro"><?= _("Sustituye todos tus movimientos personales actuales por los de la copia. El centro y las remesas aceptadas no cambian.") ?></p>
    <p class="muted"><?= _("Elija una copia de la tabla o suba un fichero .json descargado antes.") ?></p>
    <form id="form-restore-personal" class="grid-form">
        <label><?= _("O fichero local (.json)") ?>
            <input name="dump" type="file" accept=".json,application/json">
        </label>
        <button type="submit" class="peligro"><?= _("Restaurar desde fichero local") ?></button>
    </form>
    <p class="ok" id="msg-restore-personal" hidden></p>
</section>
<?php endif; ?>

<script>
window.I18N_PERSONAL = {
  error: <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>,
  pendiente: <?= json_encode(_("Pendiente de confirmación: %s. Revise ese buzón y abra el enlace."), JSON_UNESCAPED_UNICODE) ?>,
  descargar: <?= json_encode(_("Descargar"), JSON_UNESCAPED_UNICODE) ?>,
  restaurar: <?= json_encode(_("Restaurar"), JSON_UNESCAPED_UNICODE) ?>,
  borrar: <?= json_encode(_("Borrar"), JSON_UNESCAPED_UNICODE) ?>,
  noListado: <?= json_encode(_("No se pudo cargar el listado"), JSON_UNESCAPED_UNICODE) ?>,
  confirmBorrar: <?= json_encode(_("¿Borrar «%s» del servidor?"), JSON_UNESCAPED_UNICODE) ?>,
  confirmRestaurar: <?= json_encode(_("¿Restaurar «%s»? Se borrarán tus movimientos personales actuales y se sustituirán por los de la copia."), JSON_UNESCAPED_UNICODE) ?>,
  restauracionOk: <?= json_encode(_("Restauración completada."), JSON_UNESCAPED_UNICODE) ?>,
  copiaCreada: <?= json_encode(_("Copia creada: %s (%s, %s movimientos)."), JSON_UNESCAPED_UNICODE) ?>,
  elijaFichero: <?= json_encode(_("Elija un fichero .json"), JSON_UNESCAPED_UNICODE) ?>,
  confirmLocal: <?= json_encode(_("¿Restaurar desde el fichero local? Se sustituirán todos sus movimientos personales."), JSON_UNESCAPED_UNICODE) ?>,
  errorRestaurar: <?= json_encode(_("Error al restaurar"), JSON_UNESCAPED_UNICODE) ?>,
  respuestaNoJson: <?= json_encode(_("Respuesta no JSON"), JSON_UNESCAPED_UNICODE) ?>,
  limiteCopias: <?= json_encode(_("Solo se permite tener 5 copias en el servidor"), JSON_UNESCAPED_UNICODE) ?>,
  noVinculos: <?= json_encode(_("No se pudieron cargar los datos"), JSON_UNESCAPED_UNICODE) ?>,
  confirmDesvincular: <?= json_encode(_("¿Desvincularse de este centro? Podrá solicitar acceso a otro más tarde."), JSON_UNESCAPED_UNICODE) ?>,
  noDesvincular: <?= json_encode(_("No se pudo desvincular"), JSON_UNESCAPED_UNICODE) ?>,
  elegir: <?= json_encode(_("Elegir…"), JSON_UNESCAPED_UNICODE) ?>,
  solicitudEnviada: <?= json_encode(_("Solicitud enviada. Espere la aprobación del centro."), JSON_UNESCAPED_UNICODE) ?>,
  centro: <?= json_encode(_("Nombre del centro"), JSON_UNESCAPED_UNICODE) ?>,
  iniciales: <?= json_encode(_("Iniciales"), JSON_UNESCAPED_UNICODE) ?>,
  nombre: <?= json_encode(_("Nombre"), JSON_UNESCAPED_UNICODE) ?>,
  anio: <?= json_encode(_("Año"), JSON_UNESCAPED_UNICODE) ?>,
  mensaje: <?= json_encode(_("Mensaje"), JSON_UNESCAPED_UNICODE) ?>,
  sinCentros: <?= json_encode(_("No hay centros n disponibles."), JSON_UNESCAPED_UNICODE) ?>,
  sinNombres: <?= json_encode(_("No hay nombres libres en este centro. Délos de alta en Nombres."), JSON_UNESCAPED_UNICODE) ?>,
};
</script>
<script src="/js/qrcode.min.js"></script>
<script src="/js/cuenta_personal.js?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/js/cuenta_personal.js') ?: 0) ?>"></script>
