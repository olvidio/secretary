# Banco: importar el extracto

- Ruta: `/yo/banco`
- Menú: Mis cuentas → Banco
- Quién: cualquier persona

## Para qué sirve

Permite subir el extracto del banco y convertirlo en movimientos del libro propio, sin teclearlos uno a uno. Cada línea entra en el banco propio y queda en «Por categorizar» hasta que se le asigna una categoría.

## Cómo se usa

1. En «Banco», elegir el banco del que viene el fichero. La elección se recuerda para la próxima vez.
2. En «Fichero», elegir el extracto descargado: de N26, el CSV; de CaixaBank, BBVA o Banco Sabadell, el Excel (`.xls` o `.xlsx`) o un CSV.
3. Pulsar **Importar**. Un aviso indica cuántos movimientos son nuevos, cuántos ya estaban y cuántos se omiten.
4. En «Por categorizar», elegir la categoría en el desplegable de cada línea.
5. Revisar el texto de «Observaciones», que viene con el concepto del banco, y corregirlo si se quiere.
6. Elegir categoría en el desplegable y pulsar **Asignar**: la línea sale de «Por categorizar» y ya cuenta como ingreso o gasto. Al final del desplegable hay también **Otra contabilidad** y **Traspaso a caja** / **Traspaso desde caja** (según cobro o pago): se eligen igual y se confirman con **Asignar**.
7. En observaciones, **×** borra el texto guardado de ese comercio para que no vuelva a preseleccionarse.

En N26 el fichero se descarga desde la web: cuenta → Descargas → actividad de la cuenta → CSV. En CaixaBank, desde CaixaBankNow → Cuentas → la cuenta, cargando todo el periodo con «Ver más movimientos» antes de extraerlo. En BBVA, Cuentas → la cuenta → Movimientos → Descargar en Excel o CSV. En Banco Sabadell, Operativa diaria → Cuentas → Saldos y movimientos → Descargar → Excel o CSV.

## Reglas que conviene saber

- Volver a subir el mismo extracto no duplica nada: el programa reconoce las líneas que ya tenía.
- Si antes se clasificó algo del mismo comercio, la categoría aparece preseleccionada.
- La categoría elegida debe ser del mismo signo: un cobro no admite una categoría de gasto.
- «Otra contabilidad» aparca un movimiento que no es propio: sigue moviendo el banco, para que el saldo cuadre con el extracto, pero no suma en ingresos ni gastos ni viaja al centro. Después se le puede poner una categoría normal.
- «Traspaso a caja» (o «Traspaso desde caja» en un ingreso) convierte la línea en un traspaso entre banco y caja: no es ingreso ni gasto del plan, solo mueve efectivo entre tus dos tesorerías.
- Debajo aparecen las **plantillas del centro** (p. ej. «Club»): en tu libro personal queda una sola salida; al aceptar la remesa, el centro ejecuta la plantilla completa (P y G).
- El fichero no puede pasar de 2 MB.

## Problemas frecuentes

- **«El fichero CSV está vacío» o «No hay movimientos en el extracto»**: se descargó sin cargar antes todo el periodo, o no es el extracto del banco elegido.
- **«Este banco solo admite CSV»**: se ha subido un Excel a un banco que no lo acepta.
- **«Formato Excel no soportado: use .xls o .xlsx»**: guardar el fichero en uno de esos formatos.
- **Movimientos «omitidos»**: caen en fechas que el centro no tiene abiertas y no se han podido anotar.
- **«Elija una categoría del plan»**: se ha dejado «Por categorizar» en el desplegable.
