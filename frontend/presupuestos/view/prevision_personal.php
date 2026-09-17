<style>
@page { size: A5 portrait; margin: 7mm; }
</style>
<h1 class="print-hide"><?= _("Previsión personal") ?></h1>
<p class="muted print-hide"><?= _("Hoja 613 P por persona: el calculado suma lo acumulado hasta la fecha de corte y proyecta el resto del ejercicio. En conceptos puntuales (12, 24 ca/crt/cv, 4, 51 y 52) se mira el ejercicio anterior. El importe se puede dejar en blanco: al guardar se usa el calculado.") ?></p>
<p class="filters print-hide">
    <label><?= _("Persona") ?>
        <select id="sel-persona"><option value=""><?= _("Elegir…") ?></option></select>
    </label>
    <button type="button" id="btn-imprimir" hidden><?= _("Imprimir") ?></button>
</p>
<p class="prevision-print-cab" id="print-cab-personal"></p>
<form id="form-prevision-personal" hidden>
<table class="tabla-prevision tabla-prevision-personal">
    <thead>
    <tr>
        <th class="col-concepto"><?= _("Concepto") ?></th>
        <th class="num"><?= _("Calculado") ?></th>
        <th class="num"><?= _("Importe") ?></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<p class="filters print-hide">
    <button type="button" id="btn-usar-calculados"><?= _("Usar calculados") ?></button>
    <button type="submit"><?= _("Guardar") ?></button>
</p>
<p id="msg-prevision-personal" class="ok print-hide" hidden><?= _("Guardado") ?></p>
</form>
<script>
const I18N_PREV = {
  acum: <?= json_encode(_("acum."), JSON_UNESCAPED_UNICODE) ?>,
  puntual: <?= json_encode(_("año anterior"), JSON_UNESCAPED_UNICODE) ?>,
  errorCarga: <?= json_encode(_("No se pudo cargar la previsión"), JSON_UNESCAPED_UNICODE) ?>,
  cabecera: <?= json_encode(_("Previsión 613 P"), JSON_UNESCAPED_UNICODE) ?>,
};
const CENTRO_PREV = <?= json_encode((string) ($centroNombre ?? ''), JSON_UNESCAPED_UNICODE) ?>;

function modoEtiqueta(modo) {
  return modo === 'puntual' ? I18N_PREV.puntual : '';
}

function fmtPrev(valorEs) {
  if (!valorEs || valorEs === '0,00' || valorEs === '-0,00') return '';
  return valorEs.endsWith(',00') ? valorEs.slice(0, -3) : valorEs;
}

function pintarHojaPersonal(r) {
  const tb = document.querySelector('#form-prevision-personal tbody');
  tb.innerHTML = '';
  const filas = r.filas && r.filas.length ? r.filas : (r.lineas || []).map((l) => Object.assign({ tipo: 'hijo', editable: true }, l));
  filas.forEach((l) => {
    const tr = document.createElement('tr');
    tr.className = 'prevision-' + (l.tipo || 'hijo');
    const etq = l.codigo && l.tipo === 'hijo'
      ? esc(l.codigo) + ' ' + esc(l.etiqueta)
      : esc(l.etiqueta);
    const nota = modoEtiqueta(l.modo);
    const detalle = l.acumulado_es
      ? '<div class="muted detalle-acum">' + esc(I18N_PREV.acum + ' ' + l.acumulado_es + (nota ? ' · ' + nota : '')) + '</div>'
      : '';
    const tdConc = document.createElement('td');
    tdConc.className = 'col-concepto';
    tdConc.innerHTML = etq + detalle;
    const tdCalc = document.createElement('td');
    tdCalc.className = 'num';
    tdCalc.textContent = fmtPrev(l.calculado_es);
    const tdImp = document.createElement('td');
    tdImp.className = 'num';
    if (l.editable && l.codigo) {
      const inp = document.createElement('input');
      inp.name = l.codigo;
      inp.className = 'num print-hide';
      inp.value = l.previsto_es ? fmtImporteEs(l.previsto_es) : '';
      inp.dataset.calculado = l.calculado_es || '0,00';
      inp.addEventListener('blur', () => {
        if (inp.value.trim()) inp.value = fmtImporteEs(inp.value);
      });
      const impPrint = document.createElement('span');
      impPrint.className = 'prevision-print-imp';
      impPrint.textContent = fmtPrev(l.previsto_es || l.calculado_es);
      tdImp.appendChild(inp);
      tdImp.appendChild(impPrint);
    } else {
      tdImp.textContent = fmtPrev(l.previsto_es);
    }
    tr.appendChild(tdConc);
    tr.appendChild(tdCalc);
    tr.appendChild(tdImp);
    tb.appendChild(tr);
  });
  document.getElementById('form-prevision-personal').hidden = false;
  document.getElementById('btn-imprimir').hidden = false;
  const nom = (r.persona && (r.persona.nombre || r.persona.iniciales)) || '';
  const cab = [I18N_PREV.cabecera, nom, CENTRO_PREV].filter(Boolean).join(' — ');
  document.getElementById('print-cab-personal').textContent = cab;
}

async function cargarPersona(id) {
  document.getElementById('msg-prevision-personal').hidden = true;
  if (!id) {
    document.getElementById('form-prevision-personal').hidden = true;
    document.getElementById('btn-imprimir').hidden = true;
    document.getElementById('print-cab-personal').textContent = '';
    return;
  }
  const r = await api('/api/previsiones/personal/' + id);
  if (!r.ok) return alert(r.error || I18N_PREV.errorCarga);
  pintarHojaPersonal(r);
}

document.addEventListener('DOMContentLoaded', async () => {
  document.body.classList.add('prevision-personal-hoja');
  const pers = await api('/api/personas');
  if (!pers.ok) return alert(pers.error || I18N_PREV.errorCarga);
  const sel = document.getElementById('sel-persona');
  (pers.personas || []).forEach((p) => {
    const o = document.createElement('option');
    o.value = p.id;
    o.textContent = (p.nombre_completo || p.nombre) + ' (' + p.iniciales + ')';
    sel.appendChild(o);
  });
  const params = new URLSearchParams(location.search);
  if (params.get('persona')) sel.value = params.get('persona');
  sel.addEventListener('change', () => cargarPersona(sel.value));
  if (sel.value) await cargarPersona(sel.value);

  document.getElementById('btn-imprimir').addEventListener('click', () => {
    document.querySelectorAll('#form-prevision-personal input[name]').forEach((i) => {
      const span = i.parentElement && i.parentElement.querySelector('.prevision-print-imp');
      if (!span) return;
      const v = i.value.trim() ? fmtImporteEs(i.value) : (i.dataset.calculado || '');
      span.textContent = fmtPrev(v);
    });
    window.print();
  });

  document.getElementById('btn-usar-calculados').addEventListener('click', () => {
    document.querySelectorAll('#form-prevision-personal input[name]').forEach((i) => {
      i.value = fmtImporteEs(i.dataset.calculado || '0,00');
    });
  });

  document.getElementById('form-prevision-personal').onsubmit = async (ev) => {
    ev.preventDefault();
    const id = sel.value;
    if (!id) return;
    const lineas = {};
    ev.target.querySelectorAll('input[name]').forEach((i) => {
      lineas[i.name] = i.value.trim() ? fmtImporteEs(i.value) : '';
    });
    const s = await api('/api/previsiones/personal/' + id, { method: 'POST', body: { lineas } });
    document.getElementById('msg-prevision-personal').hidden = !s.ok;
    if (!s.ok) return alert(s.error);
    pintarHojaPersonal(s);
  };
});
</script>
