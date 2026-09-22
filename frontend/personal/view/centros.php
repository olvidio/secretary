<div class="yo-con-ayuda">
    <h1><?= _("Centro") ?></h1>
    <details class="yo-ayuda">
        <summary aria-label="<?= htmlspecialchars(_("Ayuda"), ENT_QUOTES) ?>">i</summary>
        <div class="yo-ayuda-cuerpo">
            <p class="muted"><?= _("Vincula tu cuenta personal con un centro de tipo n (un solo centro a la vez). El secretario debe aprobar la petición; hasta entonces sigues usando tu libro propio, pero no podrás enviar el resumen mensual al centro.") ?></p>
            <p class="muted"><?= _("Los centros sg no admiten solicitudes desde aquí. Si ya estás vinculado, puedes desvincularte para pedir acceso a otro centro más adelante.") ?></p>
        </div>
    </details>
</div>

<section id="estado-vinculado" hidden>
    <h2><?= _("Centro vinculado") ?></h2>
    <dl class="datos-centro" id="datos-vinculo"></dl>
    <p><button type="button" id="btn-desvincular" class="peligro"><?= _("Desvincular") ?></button></p>
</section>

<section id="estado-pendiente" hidden>
    <h2><?= _("Solicitud pendiente") ?></h2>
    <dl class="datos-centro" id="datos-solicitud"></dl>
    <p class="muted"><?= _("Espere la aprobación del secretario del centro.") ?></p>
</section>

<section id="estado-solicitar" hidden>
    <h2><?= _("Solicitar acceso") ?></h2>
    <form id="form-solicitud" class="grid-form">
        <label><?= _("Centro (tipo n)") ?>
            <select name="centro_id" required></select>
        </label>
        <label><?= _("Año del ejercicio") ?> <input name="anio" type="number" min="2000" max="2100" required></label>
        <label><?= _("Mensaje") ?> <input name="mensaje" placeholder="<?= htmlspecialchars(_("Opcional"), ENT_QUOTES) ?>"></label>
        <button type="submit"><?= _("Enviar solicitud") ?></button>
    </form>
    <p id="err-sol" class="error" hidden></p>
    <p id="ok-sol" class="ok" hidden></p>
</section>

<script>
const I18N_YO_CENTROS = {
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
  sinCentros: <?= json_encode(_("No hay centros de tipo n disponibles."), JSON_UNESCAPED_UNICODE) ?>,
};
document.addEventListener('DOMContentLoaded', async () => {
  const anio = new Date().getFullYear();
  document.querySelector('#form-solicitud [name=anio]').value = String(anio);

  function pintarDl(el, filas) {
    el.innerHTML = filas.map(([k, v]) => `<dt>${esc(k)}</dt><dd>${esc(v)}</dd>`).join('');
  }

  function mostrar(estado) {
    ['estado-vinculado', 'estado-pendiente', 'estado-solicitar'].forEach((id) => {
      document.getElementById(id).hidden = id !== estado;
    });
  }

  async function cargar() {
    const [vinc, centros] = await Promise.all([
      api('/api/yo/vinculos-centro'),
      api('/api/yo/vinculos-centro/centros'),
    ]);
    if (!vinc.ok) return alert(vinc.error || I18N_YO_CENTROS.noVinculos);

    const vinculos = vinc.vinculos || [];
    const solicitudes = (vinc.solicitudes || []).filter((s) => s.estado === 'pendiente');

    if (vinculos.length > 0) {
      const v = vinculos[0];
      pintarDl(document.getElementById('datos-vinculo'), [
        [I18N_YO_CENTROS.centro, v.centro_nombre || ''],
        [I18N_YO_CENTROS.iniciales, v.iniciales || ''],
        [I18N_YO_CENTROS.nombre, v.nombre_completo || v.nombre || ''],
        [I18N_YO_CENTROS.anio, v.anio || ''],
      ]);
      document.getElementById('btn-desvincular').onclick = async () => {
        if (!confirm(I18N_YO_CENTROS.confirmDesvincular)) return;
        const s = await api('/api/yo/vinculos-centro/' + v.id + '/desvincular', { method: 'POST', body: {} });
        if (!s.ok) return alert(s.error || I18N_YO_CENTROS.noDesvincular);
        await cargar();
      };
      mostrar('estado-vinculado');
      return;
    }

    if (solicitudes.length > 0) {
      const s = solicitudes[0];
      pintarDl(document.getElementById('datos-solicitud'), [
        [I18N_YO_CENTROS.centro, s.centro_nombre || ''],
        [I18N_YO_CENTROS.anio, s.anio || ''],
        [I18N_YO_CENTROS.mensaje, s.mensaje || ''],
      ]);
      mostrar('estado-pendiente');
      return;
    }

    const sel = document.querySelector('#form-solicitud [name=centro_id]');
    sel.innerHTML = '<option value="">' + esc(I18N_YO_CENTROS.elegir) + '</option>';
    const lista = centros.ok ? (centros.centros || []) : [];
    if (lista.length === 0) {
      const o = document.createElement('option');
      o.value = '';
      o.textContent = I18N_YO_CENTROS.sinCentros;
      o.disabled = true;
      sel.appendChild(o);
      document.querySelector('#form-solicitud button[type=submit]').disabled = true;
    } else {
      document.querySelector('#form-solicitud button[type=submit]').disabled = false;
      lista.forEach((c) => {
        const o = document.createElement('option');
        o.value = c.id;
        o.textContent = c.nombre_listado || c.nombre || c.codigo;
        sel.appendChild(o);
      });
    }
    mostrar('estado-solicitar');
  }

  document.getElementById('form-solicitud').onsubmit = async (ev) => {
    ev.preventDefault();
    document.getElementById('err-sol').hidden = true;
    document.getElementById('ok-sol').hidden = true;
    const body = formObj(ev.target);
    const s = await api('/api/yo/vinculos-centro', { method: 'POST', body });
    if (!s.ok) {
      document.getElementById('err-sol').hidden = false;
      document.getElementById('err-sol').textContent = s.error;
      return;
    }
    document.getElementById('ok-sol').hidden = false;
    document.getElementById('ok-sol').textContent = I18N_YO_CENTROS.solicitudEnviada;
    await cargar();
  };

  await cargar();
});
</script>
