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
$centroTipo = (string) ($centroTipo ?? 'n');
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
            <input type="radio" name="tipo_cuenta" value="persona"<?= $tipoCuenta !== 'centro' ? ' checked' : '' ?>>
            <?= _("Personal (libro propio)") ?>
        </label>
        <label class="inline">
            <input type="radio" name="tipo_cuenta" value="centro"<?= $tipoCuenta === 'centro' ? ' checked' : '' ?>>
            <?= _("Centro (secretario)") ?>
        </label>
    </fieldset>

    <div id="bloque-centro"<?= $tipoCuenta === 'centro' ? '' : ' hidden' ?>>
        <p class="muted"><?= _("Crea un centro nuevo y la cuenta de su secretario. Tras confirmar el correo deberá activar el segundo factor.") ?></p>
        <label><?= _("Código del centro") ?> <input name="codigo_centro" autocomplete="off" value="<?= htmlspecialchars((string) ($codigoCentro ?? ''), ENT_QUOTES) ?>"></label>
        <label><?= _("Nombre del centro") ?> <input name="nombre_centro" autocomplete="organization" value="<?= htmlspecialchars((string) ($nombreCentro ?? ''), ENT_QUOTES) ?>"></label>
        <label><?= _("Tipo de centro") ?>
            <select name="centro_tipo">
                <option value="n"<?= $centroTipo === 'n' ? ' selected' : '' ?>><?= _("n") ?></option>
                <option value="sg"<?= $centroTipo === 'sg' ? ' selected' : '' ?>><?= _("sg") ?></option>
            </select>
        </label>
    </div>

    <div id="bloque-persona"<?= $tipoCuenta === 'centro' ? ' hidden' : '' ?>>
        <p class="muted"><?= _("Cuenta personal sin centro. Tras confirmar el correo podrá solicitar acceso a un centro de tipo n.") ?></p>
    </div>

    <label><?= _("Usuario") ?> <input name="usuario" required autofocus autocomplete="username" pattern="[a-zA-Z][a-zA-Z0-9._-]{1,31}" title="<?= htmlspecialchars(_("Letra inicial y 2-32 caracteres"), ENT_QUOTES) ?>" value="<?= htmlspecialchars((string) ($usuario ?? ''), ENT_QUOTES) ?>"></label>
    <label><?= _("Correo") ?> <input type="email" name="email" required autocomplete="email" value="<?= htmlspecialchars((string) ($email ?? ''), ENT_QUOTES) ?>"></label>
    <label><?= _("Nombre del usuario") ?> <input name="nombre" autocomplete="name" value="<?= htmlspecialchars((string) ($nombre ?? ''), ENT_QUOTES) ?>"></label>
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
    const esCentro = document.querySelector('[name=tipo_cuenta]:checked').value === 'centro';
    document.getElementById('bloque-centro').hidden = !esCentro;
    document.getElementById('bloque-persona').hidden = esCentro;
    document.querySelector('[name=codigo_centro]').required = esCentro;
    document.querySelector('[name=nombre_centro]').required = esCentro;
  });
});
document.querySelector('[name=tipo_cuenta]:checked')?.dispatchEvent(new Event('change'));
</script>
</body>
</html>
