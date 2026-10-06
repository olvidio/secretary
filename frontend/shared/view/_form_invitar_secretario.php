<?php
/** Formulario D15: mandato por correo (incluir donde el secretario invita a otro). */
?>
<p class="muted"><?= _("Correo de la persona. Si ya tiene cuenta en el programa, solo se le da acceso a este centro (mandato); entrará con su alias y verá todos sus centros en la misma sesión.") ?></p>
<form id="form-usuario" class="grid-form">
    <label><?= _("Correo") ?> <input name="email" type="email" required autocomplete="email"></label>
    <label><?= _("Alias") ?>
        <input name="usuario" autocomplete="username" placeholder="<?= htmlspecialchars(_("Solo si la cuenta es nueva"), ENT_QUOTES) ?>">
    </label>
    <label><?= _("Contraseña") ?>
        <input name="password" type="password" minlength="6" autocomplete="new-password" placeholder="<?= htmlspecialchars(_("Obligatoria solo en cuenta nueva; opcional para fijar otra"), ENT_QUOTES) ?>">
    </label>
    <label><?= _("Nombre") ?> <input name="nombre" autocomplete="name"></label>
    <label><?= _("Rol") ?>
        <select name="rol">
            <option value="admin"><?= _("Puede modificar") ?></option>
            <option value="consulta"><?= _("Solo consulta") ?></option>
        </select>
    </label>
    <button type="submit"><?= _("Invitar") ?></button>
</form>
