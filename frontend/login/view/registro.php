<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= _("Registrarse — Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<?php
$tipoCuenta = (string) ($tipoCuenta ?? 'persona');
$esCentro = $tipoCuenta === 'centro';
$esClub = $tipoCuenta === 'club';
$esCentroSg = $tipoCuenta === 'centro-sg';
$esFundacion = $tipoCuenta === 'fundacion';
$esOrganizacion = $esCentro || $esClub || $esCentroSg || $esFundacion;
?>
<form method="post" action="/registro" class="login-box" id="form-registro">
    <h1><?= _("Registrarse") ?></h1>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">

    <fieldset class="registro-tipo">
        <legend><?= _("Tipo de cuenta") ?></legend>
        <label class="inline">
            <input type="radio" name="tipo_cuenta" value="persona"<?= $esOrganizacion ? '' : ' checked' ?>>
            <?= _("Personal (libro propio)") ?>
        </label>
        <label class="inline">
            <input type="radio" name="tipo_cuenta" value="centro"<?= $esCentro ? ' checked' : '' ?>>
            <?= _("Centro n") ?>
        </label>
        <label class="inline">
            <input type="radio" name="tipo_cuenta" value="centro-sg"<?= $esCentroSg ? ' checked' : '' ?>>
            <?= _("Centro sg") ?>
        </label>
        <label class="inline">
            <input type="radio" name="tipo_cuenta" value="club"<?= $esClub ? ' checked' : '' ?>>
            <?= _("Asociación") ?>
        </label>
        <label class="inline">
            <input type="radio" name="tipo_cuenta" value="fundacion"<?= $esFundacion ? ' checked' : '' ?>>
            <?= _("Fundación") ?>
        </label>
    </fieldset>

    <div id="bloque-centro"<?= $esOrganizacion ? '' : ' hidden' ?>>
        <p class="muted" id="ayuda-centro"<?= $esCentro ? '' : ' hidden' ?>><?= _("Crea un centro n y la cuenta de su secretario. Las personas podrán solicitar vincularse. Tras confirmar el correo deberá activar el segundo factor.") ?></p>
        <p class="muted" id="ayuda-centro-sg"<?= $esCentroSg ? '' : ' hidden' ?>><?= _("Crea un centro sg (un solo libro, como el Excel Secretario sg) y la cuenta de quien lo lleva. Las personas no se vinculan. Tras confirmar el correo deberá activar el segundo factor.") ?></p>
        <p class="muted" id="ayuda-club"<?= $esClub ? '' : ' hidden' ?>><?= _("Crea una asociación y la cuenta de quien la lleva. Las personas no se vinculan. Tras confirmar el correo deberá activar el segundo factor.") ?></p>
        <p class="muted" id="ayuda-fundacion"<?= $esFundacion ? '' : ' hidden' ?>><?= _("Crea una fundación y la cuenta de quien la lleva. Las personas no se vinculan. Tras confirmar el correo deberá activar el segundo factor.") ?></p>
        <label><?= _("Sigla") ?> <input name="codigo_centro" autocomplete="off" value="<?= htmlspecialchars((string) ($codigoCentro ?? ''), ENT_QUOTES) ?>"></label>
        <label><?= _("Nombre") ?> <input name="nombre_centro" autocomplete="organization" value="<?= htmlspecialchars((string) ($nombreCentro ?? ''), ENT_QUOTES) ?>"></label>
    </div>

    <div id="bloque-persona"<?= $esOrganizacion ? ' hidden' : '' ?>>
        <p class="muted"><?= _("Cuenta personal sin centro. Tras confirmar el correo podrá solicitar acceso a un centro n.") ?></p>
    </div>

    <label><?= _("Alias") ?> <input name="usuario" required autofocus autocomplete="username" pattern="[a-zA-Z][a-zA-Z0-9._-]{1,31}" title="<?= htmlspecialchars(_("Letra inicial y 2-32 caracteres"), ENT_QUOTES) ?>" value="<?= htmlspecialchars((string) ($usuario ?? ''), ENT_QUOTES) ?>"></label>
    <label><?= _("Correo") ?> <input type="email" name="email" required autocomplete="email" value="<?= htmlspecialchars((string) ($email ?? ''), ENT_QUOTES) ?>"></label>
    <label><?= _("Nombre") ?> <input name="nombre" autocomplete="name" value="<?= htmlspecialchars((string) ($nombre ?? ''), ENT_QUOTES) ?>"></label>
    <label><?= _("Contraseña") ?> <input type="password" name="password" required minlength="6" autocomplete="new-password"></label>
    <label><?= _("Repetir contraseña") ?> <input type="password" name="password_confirm" required minlength="6" autocomplete="new-password"></label>
    <label class="inline casilla-legal">
        <input type="checkbox" name="acepto_condiciones" value="1" required>
        <span>
            <?= htmlspecialchars((string) ($textoAceptacion ?? _('He leído y acepto las Condiciones de uso y he sido informado de la Política de privacidad. El servicio es gratuito.')), ENT_QUOTES) ?>
            <a href="/condiciones" target="_blank" rel="noopener"><?= _("Condiciones") ?></a>
            (<?= htmlspecialchars((string) ($versionCondiciones ?? 'v1'), ENT_QUOTES) ?>)
            ·
            <a href="/privacidad" target="_blank" rel="noopener"><?= _("Privacidad") ?></a>
            (<?= htmlspecialchars((string) ($versionPrivacidad ?? 'v1'), ENT_QUOTES) ?>)
        </span>
    </label>
    <button type="submit"><?= _("Crear cuenta") ?></button>
    <p class="login-alt"><a href="/login"><?= _("Volver a entrar") ?></a></p>
    <?php include dirname(__DIR__, 2) . '/shared/view/_pie_legal.php'; ?>
</form>
<script>
document.querySelectorAll('[name=tipo_cuenta]').forEach((r) => {
  r.addEventListener('change', () => {
    const tipo = document.querySelector('[name=tipo_cuenta]:checked').value;
    const esOrganizacion = tipo === 'centro' || tipo === 'club' || tipo === 'centro-sg' || tipo === 'fundacion';
    document.getElementById('bloque-centro').hidden = !esOrganizacion;
    document.getElementById('bloque-persona').hidden = esOrganizacion;
    document.getElementById('ayuda-centro').hidden = tipo !== 'centro';
    document.getElementById('ayuda-centro-sg').hidden = tipo !== 'centro-sg';
    document.getElementById('ayuda-club').hidden = tipo !== 'club';
    document.getElementById('ayuda-fundacion').hidden = tipo !== 'fundacion';
    document.querySelector('[name=codigo_centro]').required = esOrganizacion;
    document.querySelector('[name=nombre_centro]').required = esOrganizacion;
  });
});
document.querySelector('[name=tipo_cuenta]:checked')?.dispatchEvent(new Event('change'));
</script>
</body>
</html>
