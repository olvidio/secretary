<h1><?= _("Remesas") ?></h1>
<p class="muted"><?= _("Envíos mensuales de las personas. Aceptar genera un asiento en P contra la cuenta personal; rechazar no deja rastro. Reenviar el mismo mes sustituye la versión pendiente.") ?></p>
<form id="filtros-remesas" class="filters">
    <label><?= _("Estado") ?>
        <select name="estado">
            <option value=""><?= _("Todas") ?></option>
            <option value="enviada" selected><?= _("Recibidas") ?></option>
            <option value="aceptada"><?= _("Aceptadas") ?></option>
            <option value="rechazada"><?= _("Rechazadas") ?></option>
            <option value="sustituida"><?= _("Sustituidas") ?></option>
        </select>
    </label>
    <button type="submit"><?= _("Filtrar") ?></button>
</form>
<p id="remesas-err" class="error" hidden></p>
<table id="tabla-remesas">
    <thead>
    <tr>
        <th><?= _("Mes") ?></th>
        <th><?= _("Persona") ?></th>
        <th>V.</th>
        <th><?= _("Estado") ?></th>
        <th class="num"><?= _("Disponible") ?></th>
        <th><?= _("Enviada") ?></th>
        <th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<section id="remesa-detalle" hidden>
    <h2 id="remesa-detalle-titulo"><?= _("Detalle") ?></h2>
    <p id="remesa-detalle-meta" class="muted"></p>
    <p id="remesa-tesoreria" class="muted" hidden></p>
    <label id="remesa-sustituir-wrap" class="inline" hidden>
        <input type="checkbox" id="remesa-sustituir">
        <span><?= _("Sustituir el disponible por este importe") ?></span>
    </label>
    <table id="tabla-remesa-lineas">
        <thead>
        <tr><th><?= _("Concepto") ?></th><th class="num"><?= _("Importe") ?></th><th><?= _("Detalle") ?></th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <div id="remesa-linea-detalle" class="remesa-linea-detalle" hidden>
        <h3 id="remesa-linea-detalle-titulo"><?= _("Desglose autorizado") ?></h3>
        <p class="muted"><?= _("Subcuentas del libro personal que suman la línea. Solo lectura; no crea cuentas en el centro.") ?></p>
        <table id="tabla-remesa-linea-detalle">
            <thead>
            <tr><th><?= _("Subcuenta") ?></th><th><?= _("Nombre") ?></th><th class="num"><?= _("Importe") ?></th><th><?= _("Notas") ?></th><th><?= _("Generales / plantillas") ?></th></tr>
            </thead>
            <tbody></tbody>
        </table>
        <p id="remesa-linea-detalle-vacio" class="muted" hidden><?= _("Sin subcuentas en el desglose.") ?></p>
    </div>
    <div id="remesa-diff" hidden>
        <h3><?= _("Diferencia respecto a la versión anterior") ?></h3>
        <ul id="remesa-diff-list"></ul>
    </div>
    <p id="remesa-acciones">
        <button type="button" id="remesa-aceptar"><?= _("Aceptar") ?></button>
        <button type="button" id="remesa-rechazar"><?= _("Rechazar") ?></button>
    </p>
    <label><?= _("Nota al rechazar") ?> <input id="remesa-nota" maxlength="200"></label>
</section>
<script src="/js/remesas.js"></script>

<section id="saldos-cc-manual">
    <h2><?= _("Cuenta corriente (banco) sin remesa") ?></h2>
    <p class="muted"><?= _("Para quien no envía remesa: anote el saldo de caja y banco de la persona a fin de mes (concepto 90). Solo informa, no crea apuntes. En el 613 P cuenta igual que el de una remesa aceptada; si el mismo mes hay las dos cosas, manda lo anotado aquí.") ?></p>
    <form id="form-saldo-cc" class="filters">
        <label><?= _("Persona") ?>
            <select name="persona_id" required><option value=""></option></select>
        </label>
        <label><?= _("Mes") ?>
            <select name="mes" required>
                <option value="1"><?= _("enero") ?></option>
                <option value="2"><?= _("febrero") ?></option>
                <option value="3"><?= _("marzo") ?></option>
                <option value="4"><?= _("abril") ?></option>
                <option value="5"><?= _("mayo") ?></option>
                <option value="6"><?= _("junio") ?></option>
                <option value="7"><?= _("julio") ?></option>
                <option value="8"><?= _("agosto") ?></option>
                <option value="9"><?= _("septiembre") ?></option>
                <option value="10"><?= _("octubre") ?></option>
                <option value="11"><?= _("noviembre") ?></option>
                <option value="12"><?= _("diciembre") ?></option>
            </select>
        </label>
        <label><?= _("Año") ?> <input name="anio" type="number" min="1900" max="2999" step="1" required></label>
        <label><?= _("Saldo") ?> <input name="saldo" inputmode="decimal" required></label>
        <label><?= _("Nota") ?> <input name="nota" maxlength="200"></label>
        <button type="submit"><?= _("Guardar") ?></button>
    </form>
    <p id="saldo-cc-msg" class="ok" hidden></p>
    <table id="tabla-saldos-cc">
        <thead>
        <tr>
            <th><?= _("Mes") ?></th>
            <th><?= _("Persona") ?></th>
            <th class="num"><?= _("Saldo") ?></th>
            <th><?= _("Nota") ?></th>
            <th></th>
        </tr>
        </thead>
        <tbody></tbody>
    </table>
    <p id="saldos-cc-vacio" class="muted" hidden><?= _("No hay saldos anotados a mano en este ejercicio.") ?></p>
</section>
<script>
(() => {
  const I18N_SALDO_CC = {
    guardado: <?= json_encode(_("Saldo guardado"), JSON_UNESCAPED_UNICODE) ?>,
    borrar: <?= json_encode(_("Borrar"), JSON_UNESCAPED_UNICODE) ?>,
    borrarConfirm: <?= json_encode(_("¿Borrar este saldo anotado a mano?"), JSON_UNESCAPED_UNICODE) ?>,
  };
  const form = document.getElementById('form-saldo-cc');
  const tb = document.querySelector('#tabla-saldos-cc tbody');
  const vacio = document.getElementById('saldos-cc-vacio');
  const msg = document.getElementById('saldo-cc-msg');
  const nombresMes = Array.from(form.mes.options).map((o) => o.textContent);

  async function cargarPersonas() {
    const r = await api('/api/personas');
    if (!r.ok) return;
    (r.personas || []).filter((p) => p.activo !== false).forEach((p) => {
      const o = document.createElement('option');
      o.value = p.id;
      o.textContent = (p.iniciales || '') + ' · ' + [p.nombre, p.apellidos].filter(Boolean).join(' ');
      form.persona_id.appendChild(o);
    });
  }

  async function cargarSaldos() {
    const r = await api('/api/remesas/saldos-cc');
    tb.innerHTML = '';
    if (!r.ok) {
      alert(r.error || t('no_se_pudo_cargar'));
      return;
    }
    const filas = r.saldos || [];
    vacio.hidden = filas.length > 0;
    filas.forEach((s) => {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td>' + esc(nombresMes[s.mes - 1] + ' ' + s.anio) + '</td>'
        + '<td>' + esc((s.iniciales || '') + ' · ' + (s.persona || '')) + '</td>'
        + '<td class="num">' + esc(s.saldo_es) + '</td>'
        + '<td>' + esc(s.nota || '') + '</td>'
        + '<td><button type="button" class="peligro">' + esc(I18N_SALDO_CC.borrar) + '</button></td>';
      tr.querySelector('button').onclick = async () => {
        if (!confirm(I18N_SALDO_CC.borrarConfirm)) return;
        const b = await api('/api/remesas/saldos-cc/' + s.id + '/borrar', { method: 'POST', body: {} });
        if (!b.ok) {
          alert(b.error || t('no_se_pudo_borrar'));
          return;
        }
        cargarSaldos();
      };
      tb.appendChild(tr);
    });
  }

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    msg.hidden = true;
    const r = await api('/api/remesas/saldos-cc', { method: 'POST', body: formObj(form) });
    if (!r.ok) {
      alert(r.error || t('no_se_pudo_guardar'));
      return;
    }
    msg.textContent = I18N_SALDO_CC.guardado;
    msg.hidden = false;
    form.saldo.value = '';
    form.nota.value = '';
    cargarSaldos();
  });

  const hoy = new Date();
  form.mes.value = String(hoy.getMonth() + 1);
  form.anio.value = String(hoy.getFullYear());
  document.addEventListener('DOMContentLoaded', () => {
    cargarPersonas();
    cargarSaldos();
  });
})();
</script>
