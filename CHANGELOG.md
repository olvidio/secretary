# Changelog

Cambios visibles para quien usa Secretario. El formato sigue
[Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

La **versión** que aparece en el login se toma, en este orden, de `var/version.json`
(generado en el deploy), del fichero `VERSION` del repositorio o del tag git
del checkout. El changelog se **actualiza a mano** al preparar un tag; no hace
falta en cada push.

## [Unreleased]

## [0.1.17] — 2026-10-05

### Añadido
- En un **centro sg**, el **nº de s** del centro se edita en **Centro → Nombres** (antes estaba en Presupuesto). El resumen **613** usa ese valor como previsto de aportaciones.

### Cambiado
- En un **centro sg**, el menú lateral se agrupa en **Centro**, **Talonario** (entrada, periódicas, listado de apuntes y plantillas), **Presupuesto e informes**, **Cierre y arqueo**, **Plan y ejercicio** y **Ayuda**.
- En **Nombres** de un centro sg ya no aparece la bandeja de solicitudes de acceso personal (solo aplica a centros n).

### Corregido
- En un **centro sg**, los conceptos de **gasto** con código 41 o 42 (p. ej. necesidades generales) ya no se tratan como traspaso caja/banco: el listado de apuntes muestra el código y la fecha de imputación distinta a la de operación funciona cuando corresponde.
- Al contabilizar en un centro sg ya no falla por falta de la cuenta puente **PUENTE.PERIODIFICACION** en el libro G (centros ya creados se actualizan al migrar la base de datos).

## [0.1.16] — 2026-10-04

### Añadido
- En **Entrar**, **Olvidé la contraseña**. El enlace llega al correo confirmado, caduca a las dos horas y solo sirve una vez. Al usarlo se quita el bloqueo por intentos fallidos. Después hay que entrar otra vez; si la cuenta es de secretario, pide el código de seis dígitos.
- Usuarios de **solo consulta** en un centro n (Centros) y en un centro sg, una asociación o una fundación (Configuración). Ven las pantallas y los informes y no pueden guardar ni borrar. Tiene que quedar al menos un usuario que pueda modificar.
- En un **centro sg**, **Apuntes → Entradas periódicas** y **Ejecutar periódicas**: apuntes mensuales, trimestrales o anuales que se contabilizan cuando tocan.
- En un **centro sg**, **Cuentas → Plantillas** para repetir un apunte del talonario. El concepto y las observaciones quedan guardados; la contrapartida es la caja.

## [0.1.15] — 2026-10-02

### Añadido
- En un **centro sg**, **Configuración** tiene **Importar Excel** para cargar el libro Secretario sg al empezar de cero: nombres, talonario y destinos.

### Cambiado
- En un **centro sg**, **Ayuda** está en el menú lateral, junto a Apuntes, Cuentas y Caja. Ya no sale dentro de Caja.
- La **Ayuda** responde solo con el manual del tipo de cuenta con el que se ha entrado (centro n, centro sg, asociación, fundación o libro personal). Los apartados de otro tipo no se ofrecen.

## [0.1.14] — 2026-10-01

### Añadido
- En **Mis cuentas**, pestaña **g.o.** (en catalán, **d.o.**) entre Remesa y Cierre. Abre un listado para imprimir: cabecera con el nombre y el periodo, y debajo los movimientos de la cuenta 22 Ordinarios y sus subcuentas.
- En **Remesa**, el **neto del mes** entre el remanente y el disponible. Las fechas de apertura y cierre van entre corchetes.

### Cambiado
- En **Nombres**, los cuatro meses exentos caben en una fila y son más cortos. Vivienda, desgravación, base liquidable y correo ocupan menos ancho.
- El saldo de la cuenta en la remesa usa separador de miles, como el resto de importes, y el campo cabe en 8 cifras.
- Lo que se envía y lo que muestra el historial y la bandeja del centro es el **disponible** (saldo de caja y banco menos el remanente). El neto del mes queda aparte.
- La remesa del libro personal llega al **nombre vinculado del centro**, para que salga en la bandeja de Remesas.

### Corregido
- Vincular la cuenta personal con un nombre ya existente del centro ya no abre un segundo libro personal dentro de ese nombre. La sesión sigue en el libro propio.

## [0.1.13] — 2026-09-30

### Añadido
- En **Mis cuentas**, al editar un movimiento de banco se puede pasar a **Traspaso a caja** (un pago) o **Traspaso desde caja** (un cobro). El botón queda junto a Caja y Banco.
- Al **desdoblar** un gasto se puede usar una plantilla del centro (p. ej. Club), igual que al anotar el gasto.
- Tipos de **entidad**: centro n, centro sg, asociación y fundación. Club Montagut pasa a asociación. Una persona solo puede vincularse a un **centro n**.
- En **administración → Usuarios**, **Borrar** también en cuentas que no tienen libro personal ni entidad.

### Cambiado
- **Remanente** pasa al menú del nombre (junto a Mail y Contraseña). **Remesa** sigue en la barra inferior cuando la persona está vinculada a un centro que no es el libro personal.
- El mes del libro personal empieza el **día siguiente al cierre del mes anterior**. Si ese cierre cae en fin de mes, el siguiente sigue empezando el día 1.
- En la administración, la lista de centros se llama **Entidades**. La sigla de la entidad ya no se llama código. Para entrar se pide **alias o correo**; el nombre de la persona y la sigla de la entidad van en campos distintos.
- Alta de asociación y de fundación en el registro. Dentro de una fundación los textos dicen fundación.

### Corregido
- Al importar un extracto, dos líneas iguales (misma fecha, importe y concepto) ya no se funden en una. Volver a importar el mismo fichero no las duplica.
- Desdoblar con la categoría Club ya no responde «Categoría no válida».

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
