document.addEventListener('DOMContentLoaded', async () => {
  const t = window.I18N_PERSONAL || {};
  const r = await api('/api/preferencias');
  if (!r.ok) return alert(r.error || t.error || 'Error');

  const formMail = document.getElementById('form-mail');
  const aviso = document.getElementById('aviso-pendiente');
  fillForm(formMail, r);
  if (r.email_pendiente && aviso) {
    aviso.hidden = false;
    aviso.textContent = String(t.pendiente || '').replace('%s', r.email_pendiente);
  }
  formMail.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const msg = document.getElementById('msg-mail');
    msg.hidden = true;
    const s = await api('/api/preferencias/mail', { method: 'POST', body: formObj(ev.target) });
    if (!s.ok) return alert(s.error);
    msg.hidden = false;
    msg.textContent = s.mensaje || '';
    if (s.pendiente_confirmacion) {
      aviso.hidden = false;
      aviso.textContent = String(t.pendiente || '').replace('%s', formObj(ev.target).email);
      fillForm(formMail, { email: s.email });
    } else {
      aviso.hidden = true;
      fillForm(formMail, { email: s.email });
    }
  });

  document.getElementById('form-password').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/preferencias/password', { method: 'POST', body: formObj(ev.target) });
    document.getElementById('msg-password').hidden = !s.ok;
    if (!s.ok) return alert(s.error);
    ev.target.reset();
  });

  if (r.totp_activo) {
    document.getElementById('totp-estado').hidden = false;
    document.getElementById('totp-pendiente').hidden = true;
  } else {
    document.getElementById('btn-totp-preparar').addEventListener('click', async () => {
      const p = await api('/api/preferencias/totp/preparar', { method: 'POST', body: {} });
      if (!p.ok) return alert(p.error);
      document.getElementById('totp-inactivo').hidden = true;
      document.getElementById('btn-totp-preparar').hidden = true;
      document.getElementById('totp-setup').hidden = false;
      document.getElementById('totp-secreto').textContent = p.secreto || '';
      const cont = document.getElementById('totp-qr');
      cont.innerHTML = '';
      if (p.uri && typeof QRCode !== 'undefined') {
        new QRCode(cont, {
          text: p.uri,
          width: 200,
          height: 200,
          colorDark: '#1a202c',
          colorLight: '#ffffff',
          correctLevel: QRCode.CorrectLevel.M,
        });
      }
    });
    document.getElementById('form-totp').addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const s = await api('/api/preferencias/totp/confirmar', { method: 'POST', body: formObj(ev.target) });
      if (!s.ok) return alert(s.error);
      document.getElementById('totp-pendiente').hidden = true;
      document.getElementById('totp-estado').hidden = false;
      const ul = document.getElementById('totp-codigos-lista');
      ul.innerHTML = '';
      (s.codigos || []).forEach((c) => {
        const li = document.createElement('li');
        const code = document.createElement('code');
        code.textContent = c;
        li.appendChild(code);
        ul.appendChild(li);
      });
      document.getElementById('totp-codigos').hidden = false;
    });
  }

  const formLayout = document.getElementById('form-layout');
  const radio = formLayout.querySelector('[name="layout"][value="' + r.layout + '"]');
  if (radio) radio.checked = true;
  formLayout.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/preferencias/layout', { method: 'POST', body: formObj(ev.target) });
    if (!s.ok) return alert(s.error);
    location.reload();
  });

  const formIdioma = document.getElementById('form-idioma');
  fillForm(formIdioma, r);
  formIdioma.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/preferencias/idioma', { method: 'POST', body: formObj(ev.target) });
    if (!s.ok) return alert(s.error);
    location.reload();
  });

  if (document.getElementById('btn-backup-personal')) {
    enlazarCopias(t);
  }
  if (document.getElementById('estado-vinculado')) {
    enlazarCentros(t);
  }
});

function enlazarCentros(t) {
  const anio = new Date().getFullYear();
  const campoAnio = document.querySelector('#form-solicitud [name=anio]');
  if (campoAnio) campoAnio.value = String(anio);

  function pintarDl(el, filas) {
    el.innerHTML = filas.map(([k, v]) => '<dt>' + esc(k) + '</dt><dd>' + esc(v) + '</dd>').join('');
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
    if (!vinc.ok) return alert(vinc.error || t.noVinculos);

    const vinculos = vinc.vinculos || [];
    const solicitudes = (vinc.solicitudes || []).filter((s) => s.estado === 'pendiente');

    if (vinculos.length > 0) {
      const v = vinculos[0];
      pintarDl(document.getElementById('datos-vinculo'), [
        [t.centro, v.centro_nombre || ''],
        [t.iniciales, v.iniciales || ''],
        [t.nombre, v.nombre_completo || v.nombre || ''],
        [t.anio, v.anio || ''],
      ]);
      document.getElementById('btn-desvincular').onclick = async () => {
        if (!confirm(t.confirmDesvincular)) return;
        const s = await api('/api/yo/vinculos-centro/' + v.id + '/desvincular', { method: 'POST', body: {} });
        if (!s.ok) return alert(s.error || t.noDesvincular);
        await cargar();
      };
      mostrar('estado-vinculado');
      return;
    }

    if (solicitudes.length > 0) {
      const s = solicitudes[0];
      pintarDl(document.getElementById('datos-solicitud'), [
        [t.centro, s.centro_nombre || ''],
        [t.anio, s.anio || ''],
        [t.mensaje, s.mensaje || ''],
      ]);
      mostrar('estado-pendiente');
      return;
    }

    const sel = document.querySelector('#form-solicitud [name=centro_id]');
    sel.innerHTML = '<option value="">' + esc(t.elegir) + '</option>';
    if (!sel.dataset.boundPropio) {
      sel.dataset.boundPropio = '1';
      sel.addEventListener('change', () => ajustarVinculoPropio(sel));
    }
    const lista = centros.ok ? (centros.centros || []) : [];
    if (lista.length === 0) {
      const o = document.createElement('option');
      o.value = '';
      o.textContent = t.sinCentros;
      o.disabled = true;
      sel.appendChild(o);
      document.querySelector('#form-solicitud button[type=submit]').disabled = true;
    } else {
      document.querySelector('#form-solicitud button[type=submit]').disabled = false;
      lista.forEach((c) => {
        const o = document.createElement('option');
        o.value = c.id;
        o.textContent = c.nombre_listado || c.nombre || c.codigo;
        if (c.es_secretario) o.dataset.secretario = '1';
        sel.appendChild(o);
      });
    }
    mostrar('estado-solicitar');
    ajustarVinculoPropio(sel);
  }

  async function ajustarVinculoPropio(sel) {
    const opt = sel.options[sel.selectedIndex];
    const propio = document.getElementById('bloque-vincular-propio');
    const enviar = document.getElementById('btn-enviar-solicitud');
    const mensaje = document.querySelector('#form-solicitud [name=mensaje]');
    const esSecretario = !!(opt && opt.dataset.secretario === '1');
    if (propio) propio.hidden = !esSecretario;
    if (enviar) enviar.hidden = esSecretario;
    if (mensaje) mensaje.closest('label').hidden = esSecretario;
    const nombres = document.getElementById('sel-nombre-propio');
    if (!nombres) return;
    nombres.innerHTML = '';
    if (!esSecretario) return;
    const r = await api('/api/yo/vinculos-centro/centros/' + encodeURIComponent(sel.value) + '/nombres');
    if (!r.ok) {
      alert(r.error || t.error);
      return;
    }
    const libres = (r.nombres || []).filter((n) => !n.ocupado);
    if (libres.length === 0) {
      const o = document.createElement('option');
      o.value = '';
      o.textContent = t.sinNombres || '';
      nombres.appendChild(o);
      return;
    }
    libres.forEach((n) => {
      const o = document.createElement('option');
      o.value = n.id;
      o.textContent = (n.iniciales ? n.iniciales + ' · ' : '') + (n.nombre || '');
      nombres.appendChild(o);
    });
  }

  const btnPropio = document.getElementById('btn-vincular-propio');
  if (btnPropio) {
    btnPropio.onclick = async () => {
      const form = document.getElementById('form-solicitud');
      const cuerpo = formObj(form);
      const s = await api('/api/yo/vinculos-centro/propio', { method: 'POST', body: cuerpo });
      if (!s.ok) return alert(s.error || t.error);
      location.reload();
    };
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
    document.getElementById('ok-sol').textContent = t.solicitudEnviada;
    await cargar();
  };

  cargar();
}

function enlazarCopias(t) {
  function fmtBytes(n) {
    if (n < 1024) return n + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
    return (n / (1024 * 1024)).toFixed(1) + ' MB';
  }

  async function loadCopiasPersonal() {
    const r = await api('/api/yo/copias');
    if (!r.ok) return alert(r.error || t.noListado);
    const tb = document.querySelector('#tabla-copias-personal tbody');
    const vacio = document.getElementById('sin-copias-personal');
    tb.innerHTML = '';
    const copias = r.copias || [];
    vacio.hidden = copias.length > 0;
    copias.forEach((c) => {
      const tr = document.createElement('tr');
      tr.innerHTML =
        '<td><code class="copia-nombre" title="' + esc(c.filename) + '">' + esc(c.filename) + '</code></td>' +
        '<td class="copia-fecha" title="' + esc(c.fecha) + '">' + esc(c.fecha) + '</td>' +
        '<td class="num">' + esc(fmtBytes(c.bytes || 0)) + '</td>' +
        '<td class="acciones">' +
          '<a href="/api/yo/copias/descargar?fichero=' + encodeURIComponent(c.filename) + '">' + esc(t.descargar) + '</a>' +
          '<button type="button" class="btn-restore-personal peligro" data-fichero="' + esc(c.filename) + '">' + esc(t.restaurar) + '</button>' +
          '<button type="button" class="btn-borrar-personal peligro" data-fichero="' + esc(c.filename) + '">' + esc(t.borrar) + '</button>' +
        '</td>';
      tb.appendChild(tr);
    });
    tb.querySelectorAll('.btn-restore-personal').forEach((btn) => {
      btn.addEventListener('click', () => restaurarPersonal(btn.dataset.fichero));
    });
    tb.querySelectorAll('.btn-borrar-personal').forEach((btn) => {
      btn.addEventListener('click', () => borrarPersonal(btn.dataset.fichero));
    });
  }

  async function borrarPersonal(fichero) {
    if (!fichero) return;
    if (!confirm(String(t.confirmBorrar).replace('%s', fichero))) return;
    const s = await api('/api/yo/copias/borrar', { method: 'POST', body: { fichero } });
    if (!s.ok) return alert(s.error);
    await loadCopiasPersonal();
  }

  async function restaurarPersonal(fichero) {
    if (!fichero) return;
    if (!confirm(String(t.confirmRestaurar).replace('%s', fichero))) return;
    const msg = document.getElementById('msg-restore-personal');
    msg.hidden = true;
    const s = await api('/api/yo/copias/restore', { method: 'POST', body: { fichero, confirmar: true } });
    if (!s.ok) return alert(s.error);
    msg.hidden = false;
    msg.textContent = s.mensaje || t.restauracionOk;
  }

  async function crearCopiaPersonal(borrarMasAntigua) {
    const btn = document.getElementById('btn-backup-personal');
    const btnReemplazar = document.getElementById('btn-backup-reemplazar-personal');
    const aviso = document.getElementById('aviso-limite-copias-personal');
    const msg = document.getElementById('msg-backup-personal');
    btn.disabled = true;
    btnReemplazar.disabled = true;
    msg.hidden = true;
    if (!borrarMasAntigua) {
      aviso.hidden = true;
      btnReemplazar.hidden = true;
    }
    try {
      const body = borrarMasAntigua ? { borrar_mas_antigua: true } : {};
      const s = await api('/api/yo/copias/backup', { method: 'POST', body });
      if (!s.ok) {
        if (s.codigo === 'limite_copias') {
          aviso.textContent = s.error || t.limiteCopias;
          aviso.hidden = false;
          btnReemplazar.hidden = false;
          return;
        }
        return alert(s.error);
      }
      aviso.hidden = true;
      btnReemplazar.hidden = true;
      msg.hidden = false;
      msg.textContent = String(t.copiaCreada)
        .replace('%s', s.filename || '')
        .replace('%s', fmtBytes(s.bytes || 0))
        .replace('%s', s.movimientos || 0);
      await loadCopiasPersonal();
    } finally {
      btn.disabled = false;
      btnReemplazar.disabled = false;
    }
  }

  loadCopiasPersonal();
  document.getElementById('btn-backup-personal').addEventListener('click', () => crearCopiaPersonal(false));
  document.getElementById('btn-backup-reemplazar-personal').addEventListener('click', () => crearCopiaPersonal(true));
  document.getElementById('form-restore-personal').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const msg = document.getElementById('msg-restore-personal');
    msg.hidden = true;
    const input = ev.target.querySelector('[name=dump]');
    if (!input.files || !input.files[0]) return alert(t.elijaFichero);
    if (!confirm(t.confirmLocal)) return;
    const fd = new FormData(ev.target);
    fd.append('confirmar', '1');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const res = await fetch('/api/yo/copias/restore', {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-CSRF-Token': csrf },
      body: fd,
    });
    const s = await res.json().catch(() => ({ ok: false, error: t.respuestaNoJson }));
    if (!s.ok) return alert(s.error || t.errorRestaurar);
    msg.hidden = false;
    msg.textContent = s.mensaje || t.restauracionOk;
    ev.target.reset();
  });
}
