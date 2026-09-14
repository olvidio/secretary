<h1>Centros</h1>
<p class="muted">Solicite unirse a un centro para el año del ejercicio. El secretario debe aprobar la petición; si ya existe su nombre en Nombres, puede vincularlo a su cuenta.</p>

<section>
    <h2>Mis vínculos</h2>
    <table id="tabla-vinculos">
        <thead>
        <tr><th>Centro</th><th>Iniciales</th><th>Nombre</th><th>Año</th><th></th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <p id="vinculos-vacio" class="muted" hidden>Sin vínculos todavía.</p>
</section>

<section>
    <h2>Solicitudes</h2>
    <table id="tabla-solicitudes">
        <thead>
        <tr><th>Centro</th><th>Año</th><th>Estado</th><th>Mensaje</th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <p id="solicitudes-vacio" class="muted" hidden>Sin solicitudes.</p>
</section>

<section>
    <h2>Solicitar acceso</h2>
    <form id="form-solicitud" class="grid-form">
        <label>Centro
            <select name="centro_id" required></select>
        </label>
        <label>Año del ejercicio <input name="anio" type="number" min="2000" max="2100" required></label>
        <label>Mensaje <input name="mensaje" placeholder="Opcional"></label>
        <button type="submit">Enviar solicitud</button>
    </form>
    <p id="err-sol" class="error" hidden></p>
    <p id="ok-sol" class="ok" hidden></p>
</section>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  const anio = new Date().getFullYear();
  document.querySelector('#form-solicitud [name=anio]').value = String(anio);

  async function usarPersona(personaId) {
    const s = await api('/api/preferencias/persona', { method: 'POST', body: { persona_id: personaId } });
    if (!s.ok) return alert(s.error || 'No se pudo cambiar la persona activa');
    if (s.siguiente) location.href = s.siguiente;
  }

  async function cargar() {
    const [vinc, centros, pref] = await Promise.all([
      api('/api/yo/vinculos-centro'),
      api('/api/yo/vinculos-centro/centros'),
      api('/api/preferencias'),
    ]);
    if (!vinc.ok) return alert(vinc.error || 'No se pudieron cargar los vínculos');
    const personaActiva = pref.ok ? pref.persona_id : null;

    const tbV = document.querySelector('#tabla-vinculos tbody');
    tbV.innerHTML = '';
    const vinculos = vinc.vinculos || [];
    document.getElementById('vinculos-vacio').hidden = vinculos.length > 0;
    vinculos.forEach((v) => {
      const tr = document.createElement('tr');
      const activa = personaActiva && v.id === personaActiva;
      const btn = activa
        ? '<span class="muted">Activa</span>'
        : `<button type="button" class="btn-link" data-persona="${v.id}">Usar</button>`;
      tr.innerHTML = `<td>${esc(v.centro_nombre || '')}</td><td>${esc(v.iniciales)}</td>
        <td>${esc(v.nombre_completo || v.nombre)}</td><td>${esc(v.anio || '')}</td><td>${btn}</td>`;
      tbV.appendChild(tr);
      const b = tr.querySelector('[data-persona]');
      if (b) b.onclick = () => usarPersona(Number(b.dataset.persona));
    });

    const tbS = document.querySelector('#tabla-solicitudes tbody');
    tbS.innerHTML = '';
    const solicitudes = vinc.solicitudes || [];
    document.getElementById('solicitudes-vacio').hidden = solicitudes.length > 0;
    solicitudes.forEach((s) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${esc(s.centro_nombre || '')}</td><td>${esc(s.anio)}</td>
        <td>${esc(s.estado)}</td><td>${esc(s.mensaje || '')}</td>`;
      tbS.appendChild(tr);
    });

    const sel = document.querySelector('#form-solicitud [name=centro_id]');
    sel.innerHTML = '<option value="">Elegir…</option>';
    if (centros.ok) {
      (centros.centros || []).forEach((c) => {
        const o = document.createElement('option');
        o.value = c.id;
        o.textContent = c.nombre || c.codigo;
        sel.appendChild(o);
      });
    }
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
    document.getElementById('ok-sol').textContent = 'Solicitud enviada. Espere la aprobación del centro.';
    ev.target.reset();
    document.querySelector('#form-solicitud [name=anio]').value = String(anio);
    await cargar();
  };

  await cargar();
});
</script>
