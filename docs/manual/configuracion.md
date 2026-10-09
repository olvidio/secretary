# Configuración

- Ruta: `/configuracion`
- Ámbito: centro-n, centro-sg, asociacion, fundacion
- Quién: secretario del centro. Quien solo consulta ve la pantalla y no puede guardar.

## Común

Guarda la **sigla** del centro (identificador corto, p. ej. sgMontagut o Montagut). **Guardar** muestra «Guardado».

El **ejercicio contable** (altas, fechas de inicio y fin, cierre y reapertura) se gestiona en **Plan y ejercicio → Ejercicios**. La **fecha de corte** del mes (hasta dónde hay datos metidos) se cambia en **Cierre y arqueo → Fecha cierre**; no es el fin del ejercicio.

- **No aparece «Guardado»**: algún campo no se ha aceptado; el aviso emergente indica cuál.

## Centro n

También se eligen el **tipo** (centro n o centro sg), el **tipo de cierre** (vivienda o necesidades) y el **plan contable**. El tipo de cierre decide el reparto mensual de gastos generales: vivienda → 21 / 11; necesidades → 6 / 14. Solo un centro n admite que las cuentas personales soliciten acceso. El plan fija la estructura de cuentas; las partidas del capítulo VII se personalizan en Centros. Conviene dejarlo elegido al empezar.

En «Tramos de desgravación» se editan los tramos (p. ej. 250 € al 80 % y el resto al 40 %) y el **máximo** (% de la base liquidable; por defecto 10 %). **Añadir tramo** agrega una fila; **Quitar** la elimina. **Guardar tramos** muestra «Tramos guardados». Sirven al proponer destinos 7. El primero se llena entre varias personas antes de subir el importe de una sola. El máximo recorta lo que cada uno puede meter en partidas 7 que desgravan. La base liquidable se escribe en Nombres.

- **«El máximo debe estar entre 1 y 100 %…»**: el tope de donativos es un porcentaje de la base liquidable (10 % en el IRPF general).

## Centro sg

No hay tipo de centro, tipo de cierre, plan ni tramos de desgravación. El menú es Centro → Configuración (sigla y usuarios). Ahí también se ven los usuarios del centro y se puede **invitar** a otro secretario por **correo** (mandato D15: misma persona, mismo login, varios centros posibles): **Puede modificar** o **Solo consulta**. El de solo consulta ve los datos y no los guarda ni los borra. Tiene que quedar al menos un usuario que pueda modificar.

**Importar Excel** carga el libro Secretario sg (`.xlsm` o `.xlsx`) cuando el centro empieza de cero. Hay que marcar que el centro es responsable de los nombres y pulsar **Importar Excel**. Entran los nombres, el talonario y los destinos. El aviso indica cuántos nombres y apuntes han entrado y la fecha de cierre. Volver a importar sustituye los apuntes que vinieron de un Excel anterior; lo anotado a mano se queda.

- **«Falta el fichero Excel»** o **«El fichero debe ser .xlsm o .xlsx»**: hay que elegir el libro Secretario sg.
- **«Marque que el centro es responsable…»**: la importación da de alta nombres; hace falta marcar la casilla.

## Asociación y fundación

No hay menú Centros, ni tipo de centro, ni tipo de vivienda, ni tramos de desgravación. En esta pantalla se editan la sigla y los usuarios de esta asociación o fundación; el ejercicio contable, en **Ejercicios**. Al vincular un usuario se elige **Puede modificar** o **Solo consulta**. El de solo consulta ve los datos y no los guarda ni los borra. En la tabla se puede cambiar el rol; tiene que quedar al menos un usuario que pueda modificar.

**Importar Grisbi** carga un `.gsb`. Las categorías nuevas se crean como cuentas y los movimientos como asientos. Volver a importar actualiza o añade según el fichero, sin duplicar al azar.
