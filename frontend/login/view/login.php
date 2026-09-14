<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Entrar — Secretario</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<form method="post" action="/login" class="login-box">
    <h1>Secretario</h1>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <label>Usuario o email <input name="usuario" required autofocus autocomplete="username" value="<?= htmlspecialchars((string) ($usuario ?? ''), ENT_QUOTES) ?>"></label>
    <label>Contraseña <input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit">Entrar</button>
    <p class="login-alt"><a href="/registro" id="ir-registro">Registrarse</a></p>
</form>
<script>
document.getElementById('ir-registro').addEventListener('click', (ev) => {
  const u = document.querySelector('input[name="usuario"]')?.value?.trim();
  if (!u) return;
  ev.preventDefault();
  location.href = '/registro?usuario=' + encodeURIComponent(u);
});
</script>
</body>
</html>
