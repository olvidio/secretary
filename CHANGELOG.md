# Changelog

Cambios visibles para quien usa Secretario. El formato sigue
[Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

La **versión** que aparece en el login se toma, en este orden, de `var/version.json`
(generado en el deploy), del fichero `VERSION` del repositorio o del tag git
del checkout. El changelog se **actualiza a mano** al preparar un tag; no hace
falta en cada push.

## [Unreleased]

## [0.1.12] — 2026-09-28

### Corregido
- **Mis cuentas → Banco** dejaba de cargar la pestaña (error al preparar la lista de bancos para importar el extracto: N26, CaixaBank, BBVA, Sabadell). **Entrada G** ya funcionaba por la API; personal y centro comparten el mismo catálogo de formatos.

## [0.1.11] — 2026-09-28

### Añadido
- **Centro sg** (plan contable H16s): alta como «Centro sg» en el registro, menús y pantallas adaptados al Excel Secretario sg (un solo libro general). Importación de datos desde `.xlsm`, personas con **grupo** y clase **s** / **cp**, **Listado de aportaciones** (E 32), **destinos** del 42 al 54 con nombre por centro, presupuesto anual propio del centro con **nº de s**, resumen **613 G-D** (ingresos, gastos, disponible, destinos, estadísticas de aportaciones y arqueo fin de mes).
- **Associació (club)** (plan Club): alta en el registro, importación de ficheros **Grisbi** (`.gsb`) desde Configuración, pantalla **Listados** para crear y consultar informes, arqueo del club.
- En **Mis cuentas → Remanente**, fijar la cantidad que no se envía al centro en la remesa; el **disponible** del mes es el saldo de caja y banco menos ese remanente.
- **Copias de seguridad** del centro (`/copias`): crear, descargar, restaurar y borrar copias (hasta cinco en el servidor).

### Cambiado
- En **Remesas** y **Disponible**, el importe enviable usa el **disponible** (saldo menos remanente), no el saldo bruto de caja y banco.
- En **Configuración**, el campo **Centro** muestra la sigla del centro activo (no el nombre genérico del singleton).
- En **Apuntes** y **Nombres**, filtros y columnas adaptados al centro sg; en **Presupuesto G** del sg, editor de destinos y campo compacto de nº de s.
- El pie del **613 G-D** identifica el informe como **613 G-D**; totales de ingresos, gastos y destinos en negrita; nota de aportaciones y bloque VºBº / arqueo al estilo del Excel.

### Corregido
- El resumen 613 de un centro sg ya no reutiliza la hoja del centro de casa ni el presupuesto global de Montagut: previsto, destinos visibles y pie del informe salen del centro H16s.

## [0.1.10] — 2026-09-23 (pulir previsiones)

### Cambiado
- En **Previsión personal**, la columna de referencia pasa a llamarse **acumulado(anterior)**: muestra el acumulado del ejercicio abierto y, entre paréntesis, la previsión ya guardada de ese ejercicio (no la proyección).
- **Guardar** solo persiste los importes escritos en **Previsión**; las casillas vacías dejan el concepto sin cifra guardada (ya no se rellena con la proyección calculada).
- Los importes de **Previsión** se muestran y guardan en **euros enteros**, sin céntimos.
- Se quitan los botones **Usar calculados** y **Usar previsión del ejercicio actual**.

### Corregido
- Al escribir **5000** en Previsión ya no se guardaba **5** por confundir el separador de miles español (`5.000`) con decimales.

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
