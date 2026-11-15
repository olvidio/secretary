// Ojo de los campos de contraseña (.campo-clave): muestra u oculta lo escrito.
document.querySelectorAll('.campo-clave .ver-clave').forEach((btn) => {
  const campo = btn.closest('.campo-clave').querySelector('input');
  btn.addEventListener('click', () => {
    const visible = campo.type === 'password';
    campo.type = visible ? 'text' : 'password';
    const texto = visible ? btn.dataset.ocultar : btn.dataset.mostrar;
    btn.setAttribute('aria-pressed', visible ? 'true' : 'false');
    btn.setAttribute('aria-label', texto);
    btn.title = texto;
    campo.focus();
  });
  // Nunca se envía con la contraseña a la vista.
  campo.form?.addEventListener('submit', () => {
    campo.type = 'password';
    btn.setAttribute('aria-pressed', 'false');
  });
});
