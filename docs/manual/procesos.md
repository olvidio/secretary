# Procesos contables habituales — cierro el mes del centro, remesa, disponible, banco, alta en Nombres

Guía paso a paso de los flujos que cruzan varias pantallas. Para el detalle de cada botón, ver la ficha de la pantalla correspondiente.

Palabras clave: **cierro el mes**, **cerrar el mes del centro**, **remesa**, **disponible**, **partidas 7**, **importar extracto del banco**, **dar de alta persona nueva**, **primera remesa**, **vincular cuenta**.

## Para qué sirve

Resume en orden los procesos que el secretario y las personas repiten cada mes: **cómo cierro el mes** del centro (entrada, cierre de vivienda, 613, remesas), qué hacer con el **disponible** tras **aceptar una remesa**, **importar el banco** y clasificar movimientos, y **dar de alta a una persona nueva** con su libro personal hasta la **primera remesa**.

## Cómo se usa

### 1. Cómo cierro el mes del centro (entrada → cierre → 613 → remesas)

Flujo del secretario cuando **cierra el mes** contable del centro: no confundir con el cierre del libro personal de cada persona (su remesa).

1. **Comprobar la fecha de cierre** en Configuración (o Fecha cierre si hay que moverla). Las pantallas de entrada y los informes usan ese mes.
2. **Anotar lo que falte** en Entrada P y Entrada G: gastos de casa en G, movimientos personales en P, traspasos caja/banco si procede.
3. **Comprobaciones** (opcional pero recomendable): ejecutar y revisar avisos de meses sin cierre, vivienda sin pareja P/G, etc.
4. **Cierre de mes**: revisar la tabla de reparto y pulsar **Generar apuntes**. Si falta vivienda de meses anteriores, **Regularizar meses anteriores** antes.
5. **613 P** y **613 G**: abrir cada resumen, revisar previsto/realizado, rellenar celdas azules si hace falta, **Imprimir** o **Descargar PDF** para archivar o enviar.
6. **Remesas**: cuando las personas envíen su mes (ver proceso 2), **Filtrar** por recibidas, **Ver** cada una, **Aceptar** o **Rechazar**. Aceptar no sustituye el cierre de vivienda del centro: son cosas distintas (cierre reparte gastos G; remesa trae el resumen personal de cada uno).

Orden práctico: primero el cierre de vivienda del centro y los 613; las remesas personales pueden llegar en paralelo y aceptarse cuando estén listas.

### 2. Remesa de la persona → bandeja del centro → disponible → partidas 7

Flujo mensual entre el libro personal y el centro.

**Persona (Mis cuentas):**

1. Anotar ingresos, gastos y traspasos del mes en Resumen, Lista o Banco.
2. Ir a **Remesa**, elegir el mes con ‹ ›, revisar las líneas y el saldo de tesorería.
3. **Cerrar y enviar mes** (pide confirmación). Si el centro ya propuso destinos 7, el texto «Deberías ingresar…» aparece encima del botón.

**Secretario (centro):**

4. **Remesas** → **Filtrar** por recibidas → **Ver** el detalle.
5. Revisar conceptos e importes. Si la persona mandó saldo de caja/banco, valorar **Sustituir el disponible por esa tesorería**.
6. **Aceptar** (o **Rechazar** con nota). Aceptar anota P contra la cuenta personal y suma al **disponible** (salvo sustitución por tesorería).
7. **Disponible** → revisar saldos → **Proponer destinos 7** → revisar el reparto → **Confirmar y apuntar**. Eso anota las 7 en P y baja el disponible.
8. La persona ve el mismo texto de destinos 7 en su pantalla Remesa.

Las 7 que la persona ya hizo en su libro viajan en la remesa; las de la propuesta del centro se apuntan al confirmar en Disponible.

### 3. Importación del banco → categorizar → remesa

Mismo criterio en el libro personal (`/yo/banco`) y en el centro (**Entrada G** → bloque Importar extracto).

1. Descargar el extracto del banco (N26: CSV; CaixaBank, BBVA y Banco Sabadell: Excel o CSV).
2. Elegir banco (y cuenta de tesorería en Entrada G si hay varias) → fichero → **Importar**.
3. En **Por categorizar**, por cada línea:
   - Elegir categoría/concepto en el desplegable.
   - Revisar observaciones.
   - **Asignar** (confirma el movimiento).
   - Opciones al final del desplegable: **Otra contabilidad** (cuadra banco sin ingreso/gasto del plan) o **Traspaso a caja** / **Traspaso desde caja**.
   - En Entrada G: **Cambiar a P** si el movimiento es personal (iniciales obligatorias al **Asignar**).
4. Seguir anotando a mano lo que no venga del banco (caja, apuntes sueltos).
5. Al cerrar el mes, incluir esos movimientos en la **Remesa** (persona) o en los apuntes del centro (secretario).

Reimportar el mismo fichero no duplica líneas ya importadas.

### 4. Dar de alta a una persona nueva → vincular cuenta → primera remesa

Desde cero hasta la **primera remesa** aceptada. Pantallas clave: **Nombres**, **Centros** (persona), **Remesas**.

**Opción A — el secretario da de alta el nombre en Nombres:**

1. Pantalla **Nombres** (menú Inicio → Nombres, no la de Centros): rellenar datos, correo si llevará libro personal, marcar responsable de datos → **Guardar**. Anotar la contraseña inicial que se muestra una sola vez.
2. La persona entra con ese correo, cambia contraseña si quiere y usa Mis cuentas con normalidad.

**Opción B — la persona pide acceso:**

1. **Mis cuentas → Centros**: elegir centro **n**, año, mensaje → **Enviar solicitud**.
2. **Nombres → Solicitudes de acceso personal**: **Dar de alta y vincular** (nombre nuevo) o **Vincular existente…** → **Vincular** (casilla de responsable de datos marcada).
3. La persona queda vinculada; ya puede enviar remesas.

**Primera remesa:**

4. Persona: anotar el mes (Resumen, Banco…) → **Remesa** → **Cerrar y enviar mes**.
5. Secretario: **Remesas** → **Aceptar**.
6. Si hay disponible: **Disponible** → **Proponer destinos 7** → **Confirmar y apuntar** (puede no hacer falta el primer mes si el disponible es cero).

Comprobar en **Nombres** que «vivienda aporta a generales», exenciones de meses e iniciales son correctas antes de la primera remesa.

## Reglas que conviene saber

- La **fecha de cierre** del centro marca el mes en curso para entrada, cierre, 613 y remesas aceptadas.
- El **cierre de vivienda** (secretario) y la **remesa** (persona) son independientes: una reparte gastos G entre residentes; la otra trae el resumen P de quien envía.
- Sin vínculo aprobado en Centros (persona) no hay pestaña Remesa ni envío al centro.
- Los apuntes que vienen de remesa no se editan en Apuntes: hay que pedir reenvío.
- Tras aceptar remesa, el disponible y las 7 se gestionan en Disponible, no en la bandeja de remesas.

## Problemas frecuentes

- **Cierre generado antes de meter todos los gastos G del mes**: volver a **Generar apuntes** en Cierre de mes (sustituye el cierre de ese mes).
- **Remesa aceptada pero las 7 no aparecen**: las del centro se apuntan en **Disponible** con **Confirmar y apuntar**; las que la persona hizo en su libro ya viajan en la remesa.
- **Importación con movimientos omitidos**: fechas fuera del ejercicio abierto; revisar Ejercicios o la fecha del movimiento.
- **Solicitud de acceso sin respuesta**: el secretario debe resolverla en Nombres; hasta entonces la persona no puede enviar remesa.
- **613 con IX negativo tras el cierre**: ver ficha de Resumen 613 P y Cierre de mes; no confundir con el disponible de G.
