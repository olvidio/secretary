# Changelog

Cambios visibles para quien usa Secretario. El formato sigue
[Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

La **versión** que aparece en el login se toma, en este orden, de `var/version.json`
(generado en el deploy), del fichero `VERSION` del repositorio o del tag git
del checkout. El changelog se **actualiza a mano** al preparar un tag; no hace
falta en cada push.

## [Unreleased]

## [0.1.9] — 2026-09-23

### Añadido
- En **Previsión personal**, selector de **año** (etiqueta del ejercicio: `2027`, `2026-27`, etc.). Por defecto es el período siguiente al abierto, con la misma duración (enero–diciembre, septiembre–agosto u otra), aunque ese ejercicio aún no esté dado de alta.
- Al guardar esa previsión se crea el ejercicio siguiente en estado **planificado** (el abierto no cambia). **Calc.** muestra el acumulado del ejercicio abierto y, entre paréntesis, la previsión ya guardada de ese ejercicio. **Usar previsión del ejercicio actual** copia esas cifras a la columna Previsión.
- Importar extractos de **BBVA** y **Banco Sabadell** (Excel o CSV) en el libro personal y en Entrada G, junto a N26 y CaixaBank.

### Cambiado
- En **Previsión**, la hoja consolidada lee los importes del ejercicio siguiente (el que se está presupuestando).
- En Entrada G, el bloque **Importar extracto del banco** queda cerrado al entrar.
- Impresión de la previsión personal: la columna de concepto ocupa más ancho y las cifras van en columnas fijas.

## [0.1.8] — 2026-09-22

### Corregido
- En producción, un despliegue dejaba modificado el fichero `VERSION` del clone y
  el siguiente `./deploy.sh` se detenía por «cambios locales». Ahora solo se
  escribe `var/version.json`; el script descarta restos de despliegues antiguos.

## [0.1.7] — 2026-09-22

### Añadido
- En **Previsión personal**, opción **Todos (imprimir)**: carga todas las hojas
  del centro, con la columna Importe en blanco para rellenar a mano, e imprime
  dos hojas tamaño A5 por página en A4 horizontal.
- En **Previsión** y **Previsión personal**, la cabecera de impresión incluye
  el **año del ejercicio siguiente** (el presupuesto que se está preparando),
  después del nombre del centro.
- **Dar de baja la cuenta personal** (menú del nombre → Dar de baja): resumen
  de lo que se borra, confirmación en pantalla y enlace por correo (48 h) antes
  del borrado definitivo.
- En **administración → Usuarios**, borrado de cuentas con resumen previo:
  cuentas **personales** de inmediato (con aviso por correo) y cuentas de
  **secretario** en espera de 60 días (se puede **Reactivar** mientras dure;
  después se purgan las credenciales sin tocar la contabilidad del centro).
- En **Nombres**, al resolver una solicitud de acceso se puede **vincular** la
  cuenta a un nombre que ya existía en el centro, además de dar de alta uno nuevo.

### Cambiado
- El mismo correo puede usarse en el libro personal y en el nombre de una persona
  del centro (sigue sin repetirse entre dos nombres del mismo centro).
- Al aprobar una solicitud de acceso, si el correo ya tenía libro propio, se
  copia al nombre del centro al vincular.

## [0.1.6] — 2026-09-22

### Añadido
- Si el mismo correo tiene varias cuentas y la contraseña vale para más de una,
  al entrar se elige con cuál (libro personal o centro).
- Cambiar el correo pide confirmación en el buzón nuevo. Hasta abrir el enlace
  se sigue entrando con el anterior.

### Cambiado
- El mismo correo puede usarse en la cuenta personal y en cuentas de centro.
  No puede repetirse entre dos cuentas personales.
- «Persona activa» y «Tipo» solo aparecen en el menú cuando hay algo que elegir.
- Al pedir acceso a un centro, el nombre del listado no se confunde con el del
  secretario.
- Ayuda breve en Centro y en la fecha de cierre del libro personal.

## [0.1.5] — 2026-09-21

### Añadido
- Al registrarse o entrar como persona se crea el libro personal propio, si aún
  no existe, para poder anotar sin estar vinculado a un centro.

## [0.1.4] — 2026-09-21

### Añadido
- Importar el extracto del banco en Entrada G y categorizar los movimientos
  (concepto del general o del personal, otra contabilidad, traspaso a caja).
- Licencia GPL v3 y pantalla `/licencia`.
- Versión en el pie, con enlace a este changelog.
- En administración, expediente legal: buscar por correo o usuario y descargar
  PDF o HTML de las aceptaciones.
- Guía de procesos habituales en la ayuda (cerrar el mes, remesa, banco, alta).

### Cambiado
- En el cuadre de apuntes, atajos para anotar el descuadre (vivienda o ingreso)
  y botón para volver al listado.
- Las plantillas de apuntes se pueden editar.
- Al cuadrar en la entrada, el botón indica el concepto y el importe.
- En el banco personal: «Otra contabilidad», traspaso a caja y borrar el texto
  recordado de un comercio.
- Las solicitudes de acceso personal se resuelven con alta y vínculo, vínculo a
  un nombre existente, o rechazo.

## [0.1.3] — 2026-09-21

Versión desplegada antes de este changelog automatizado.
