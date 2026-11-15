<h1><?= _("Ayuda") ?></h1>
<p class="muted ayuda-intro">
    <?= _("Pregunte con sus palabras. Intro envía; Mayúsculas+Intro baja de línea. La respuesta sale únicamente del manual del programa: si algo no está explicado, se le dirá en lugar de improvisar.") ?>
</p>
<form id="form-ayuda" class="ayuda-preguntar">
    <label class="ayuda-campo">
        <textarea name="pregunta" rows="2" maxlength="400" required
                  placeholder="<?= htmlspecialchars(_("Por ejemplo: ¿cómo anoto un traspaso de banco a caja?"), ENT_QUOTES) ?>"></textarea>
    </label>
    <button type="submit" id="btn-preguntar"><?= _("Preguntar") ?></button>
</form>
<div id="ayuda-hilo" class="ayuda-hilo"></div>
<details class="ayuda-indice">
    <summary><?= _("Apartados del manual") ?></summary>
    <ul id="ayuda-temas" class="ayuda-temas"></ul>
</details>
<script>
const I18N_AYUDA = {
  segunManual: <?= json_encode(_("Según el manual: "), JSON_UNESCAPED_UNICODE) ?>,
  yaPreguntado: <?= json_encode(_("Ya se había preguntado lo mismo."), JSON_UNESCAPED_UNICODE) ?>,
  sinIa: <?= json_encode(_("Respuesta sin IA."), JSON_UNESCAPED_UNICODE) ?>,
  consultando: <?= json_encode(_("Consultando…"), JSON_UNESCAPED_UNICODE) ?>,
  preguntar: <?= json_encode(_("Preguntar"), JSON_UNESCAPED_UNICODE) ?>,
  errorConsulta: <?= json_encode(_("No se ha podido consultar la ayuda"), JSON_UNESCAPED_UNICODE) ?>,
  paraQueSirve: <?= json_encode(_("¿Para qué sirve %s?"), JSON_UNESCAPED_UNICODE) ?>,
  respondaAclaracion: <?= json_encode(_("Responda aquí a lo que se le pregunta…"), JSON_UNESCAPED_UNICODE) ?>,
  leHaServido: <?= json_encode(_("¿Le ha servido?"), JSON_UNESCAPED_UNICODE) ?>,
  si: <?= json_encode(_("Sí"), JSON_UNESCAPED_UNICODE) ?>,
  no: <?= json_encode(_("No"), JSON_UNESCAPED_UNICODE) ?>,
  queEsperaba: <?= json_encode(_("¿Qué esperaba encontrar? (opcional)"), JSON_UNESCAPED_UNICODE) ?>,
  enviar: <?= json_encode(_("Enviar"), JSON_UNESCAPED_UNICODE) ?>,
  gracias: <?= json_encode(_("Gracias."), JSON_UNESCAPED_UNICODE) ?>,
  graciasNo: <?= json_encode(_("Gracias. Lo apuntamos para mejorar el manual."), JSON_UNESCAPED_UNICODE) ?>,
};

function ayudaFuentes(fuentes) {
  if (!fuentes || !fuentes.length) return '';
  const chips = fuentes
    .map((f) => `<span class="ayuda-fuente">${esc(f.titulo)}</span>`)
    .join('');
  return `<p class="ayuda-fuentes">${esc(I18N_AYUDA.segunManual)}${chips}</p>`;
}

function ayudaNota(origen) {
  if (origen === 'cache') return '<p class="muted ayuda-nota">' + esc(I18N_AYUDA.yaPreguntado) + '</p>';
  if (origen === 'busqueda') return '<p class="ayuda-nota error">' + esc(I18N_AYUDA.sinIa) + '</p>';
  return '';
}

function ayudaTurno(pregunta, datos, preguntaAnterior = '') {
  const art = document.createElement('article');
  art.className = 'ayuda-turno' + (datos.resuelta ? '' : ' ayuda-sin-respuesta')
    + (datos.aclaracion ? ' ayuda-aclaracion' : '');
  const texto = esc(datos.respuesta).replace(/\n/g, '<br>');
  art.innerHTML = `<p class="ayuda-pregunta">${esc(pregunta)}</p>
    <div class="ayuda-respuesta"><p>${texto}</p>${ayudaFuentes(datos.fuentes)}${ayudaNota(datos.origen)}</div>`;
  // Solo se valora lo que contestó la IA: es lo que queda guardado para repasar el manual.
  // Una petición de aclaración todavía no es una respuesta: no se valora.
  if (datos.resuelta && !datos.aclaracion && (datos.origen === 'ia' || datos.origen === 'cache')) {
    art.appendChild(ayudaValoracion(pregunta, preguntaAnterior));
  }
  return art;
}

function ayudaValoracion(pregunta, preguntaAnterior) {
  const caja = document.createElement('div');
  caja.className = 'ayuda-valorar';
  caja.innerHTML = `<span class="muted">${esc(I18N_AYUDA.leHaServido)}</span>
    <button type="button" data-util="1">${esc(I18N_AYUDA.si)}</button>
    <button type="button" data-util="0">${esc(I18N_AYUDA.no)}</button>`;
  const enviar = async (util, comentario) => {
    const r = await api('/api/ayuda/valorar', { method: 'POST', body: { pregunta, util, comentario, pregunta_anterior: preguntaAnterior } });
    if (!r.ok) return alert(r.error || I18N_AYUDA.errorConsulta);
    caja.innerHTML = `<span class="muted">${esc(util ? I18N_AYUDA.gracias : I18N_AYUDA.graciasNo)}</span>`;
  };
  caja.addEventListener('click', (ev) => {
    const btn = ev.target.closest('button');
    if (!btn) return;
    if (btn.dataset.util === '1') return enviar(true, '');
    if (btn.dataset.util === '0') {
      caja.innerHTML = `<label class="ayuda-valorar-comentario">${esc(I18N_AYUDA.queEsperaba)}
          <textarea rows="2" maxlength="500"></textarea></label>
        <button type="button" data-enviar="1">${esc(I18N_AYUDA.enviar)}</button>`;
      caja.querySelector('textarea').focus();
      return;
    }
    if (btn.dataset.enviar) enviar(false, caja.querySelector('textarea').value.trim());
  });
  return caja;
}

document.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('form-ayuda');
  const campo = form.querySelector('[name=pregunta]');
  const boton = document.getElementById('btn-preguntar');
  const hilo = document.getElementById('ayuda-hilo');
  const placeholder = campo.placeholder;
  // Si la ayuda ha pedido que se concrete, lo siguiente que se escriba es la respuesta a eso.
  let pendiente = null;

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
      campo.value = I18N_AYUDA.paraQueSirve.replace('%s', tema.dataset.titulo);
      campo.focus();
    };
  }

  form.onsubmit = async (ev) => {
    ev.preventDefault();
    const pregunta = campo.value.trim();
    if (!pregunta) return;
    boton.disabled = true;
    boton.textContent = I18N_AYUDA.consultando;
    const anterior = pendiente;
    const r = await api('/api/ayuda/preguntar', { method: 'POST', body: {
      pregunta,
      pregunta_anterior: anterior ? anterior.pregunta : '',
      aclaracion_pedida: anterior ? anterior.aclaracion : '',
    } });
    boton.disabled = false;
    boton.textContent = I18N_AYUDA.preguntar;
    if (!r.ok) return alert(r.error || I18N_AYUDA.errorConsulta);
    hilo.appendChild(ayudaTurno(pregunta, r, anterior ? anterior.pregunta : ''));
    pendiente = r.aclaracion ? { pregunta, aclaracion: r.respuesta } : null;
    campo.placeholder = pendiente ? I18N_AYUDA.respondaAclaracion : placeholder;
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
