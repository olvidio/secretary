<h1>Ayuda</h1>
<p class="muted ayuda-intro">
    Pregunte con sus palabras. Intro envía; Mayúsculas+Intro baja de línea. La
    respuesta sale <strong>únicamente</strong> del manual del programa: si algo no
    está explicado, se le dirá en lugar de improvisar.
</p>
<form id="form-ayuda" class="ayuda-preguntar">
    <label class="ayuda-campo">
        <textarea name="pregunta" rows="2" maxlength="400" required
                  placeholder="Por ejemplo: ¿cómo anoto un traspaso de banco a caja?"></textarea>
    </label>
    <button type="submit" id="btn-preguntar">Preguntar</button>
</form>
<div id="ayuda-hilo" class="ayuda-hilo"></div>
<details class="ayuda-indice">
    <summary>Apartados del manual</summary>
    <ul id="ayuda-temas" class="ayuda-temas"></ul>
</details>
<script>
function ayudaFuentes(fuentes) {
  if (!fuentes || !fuentes.length) return '';
  const chips = fuentes
    .map((f) => `<span class="ayuda-fuente">${esc(f.titulo)}</span>`)
    .join('');
  return `<p class="ayuda-fuentes">Según el manual: ${chips}</p>`;
}

function ayudaNota(origen) {
  if (origen === 'cache') return '<p class="muted ayuda-nota">Ya se había preguntado lo mismo.</p>';
  if (origen === 'busqueda') return '<p class="ayuda-nota error">Respuesta sin IA.</p>';
  return '';
}

function ayudaTurno(pregunta, datos) {
  const art = document.createElement('article');
  art.className = 'ayuda-turno' + (datos.resuelta ? '' : ' ayuda-sin-respuesta');
  const texto = esc(datos.respuesta).replace(/\n/g, '<br>');
  art.innerHTML = `<p class="ayuda-pregunta">${esc(pregunta)}</p>
    <div class="ayuda-respuesta"><p>${texto}</p>${ayudaFuentes(datos.fuentes)}${ayudaNota(datos.origen)}</div>`;
  return art;
}

document.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('form-ayuda');
  const campo = form.querySelector('[name=pregunta]');
  const boton = document.getElementById('btn-preguntar');
  const hilo = document.getElementById('ayuda-hilo');

  const temas = await api('/api/ayuda/temas');
  if (temas.ok) {
    const lista = document.getElementById('ayuda-temas');
    (temas.temas || []).forEach((t) => {
      const li = document.createElement('li');
      li.innerHTML = `<button type="button" class="ayuda-tema" data-titulo="${esc(t.titulo)}">${esc(t.titulo)}</button>`;
      lista.appendChild(li);
    });
    lista.onclick = (ev) => {
      const tema = ev.target.closest('.ayuda-tema');
      if (!tema) return;
      campo.value = '¿Para qué sirve ' + tema.dataset.titulo + '?';
      campo.focus();
    };
  }

  form.onsubmit = async (ev) => {
    ev.preventDefault();
    const pregunta = campo.value.trim();
    if (!pregunta) return;
    boton.disabled = true;
    boton.textContent = 'Consultando…';
    const r = await api('/api/ayuda/preguntar', { method: 'POST', body: { pregunta } });
    boton.disabled = false;
    boton.textContent = 'Preguntar';
    if (!r.ok) return alert(r.error || 'No se ha podido consultar la ayuda');
    hilo.appendChild(ayudaTurno(pregunta, r));
    campo.value = '';
    hilo.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };
  campo.addEventListener('keydown', (ev) => {
    if (ev.key !== 'Enter' || ev.shiftKey) return;
    ev.preventDefault();
    form.requestSubmit();
  });
  campo.focus();
});
</script>
