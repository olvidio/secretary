# Banco: importar el extracto

- Ámbito: persona
- Ruta: `/yo/banco`
- Menú: Mis cuentas → Banco
- Quién: cualquier persona

## Para qué sirve

Permite subir el extracto del banco y convertirlo en movimientos del libro propio, sin teclearlos uno a uno. Cada línea entra en el banco propio y queda en «Por categorizar» hasta que se le asigna una categoría.

## Cómo se usa

1. En «Fichero», elegir el extracto descargado (CSV o Excel `.xls` / `.xlsx`). El programa reconoce solo si es N26, CaixaBank, BBVA, Banco Santander, Banco Sabadell o Arquia; de otro banco, se usa el formato propio que se explica más abajo.
2. Pulsar **Importar**. Un aviso indica cuántos movimientos son nuevos, cuántos ya estaban, cuántos se omiten y qué banco se ha detectado.
3. En «Por categorizar», elegir la categoría en el desplegable de cada línea.
4. Revisar el texto de «Observaciones», que viene con el concepto del banco, y corregirlo si se quiere.
5. Elegir categoría en el desplegable y pulsar **Asignar**: la línea sale de «Por categorizar» y ya cuenta como ingreso o gasto. Al final del desplegable hay también **Otra contabilidad** y **Traspaso a caja** / **Traspaso desde caja** (según cobro o pago): se eligen igual y se confirman con **Asignar**.
6. En observaciones, **×** borra el texto guardado de ese comercio para que no vuelva a preseleccionarse.
7. Si se borra en Lista (o Buscar) un movimiento que vino del extracto, pasa a **Descartados**, al final de esta pantalla: no vuelve a entrar al subir otra vez el extracto. Si se borró por error, **Recuperar** lo devuelve a «Por categorizar».

En N26 el fichero se descarga desde la web: cuenta → Descargas → actividad de la cuenta → CSV. En CaixaBank, desde CaixaBankNow → Cuentas → la cuenta, cargando todo el periodo con «Ver más movimientos» antes de extraerlo. En BBVA, Cuentas → la cuenta → Movimientos → Descargar en Excel o CSV. En Banco Santander, desde los movimientos de la cuenta, descargar el Excel (`.xls`). En Banco Sabadell, Operativa diaria → Cuentas → Saldos y movimientos → Descargar → Excel o CSV. En Arquia, descargar los movimientos de la cuenta en Excel (`.xlsx`).

## Reglas que conviene saber

- Dos apuntes iguales (mismo día, concepto e importe) se importan los dos. Volver a subir el mismo extracto no duplica nada: el programa reconoce las líneas que ya tenía, incluida cada repetición.
- Lo descartado al borrarlo tampoco vuelve a entrar al reimportar; solo vuelve con **Recuperar**.
- Si antes se clasificó algo del mismo comercio, la categoría aparece preseleccionada.
- La categoría elegida debe ser del mismo signo: un cobro no admite una categoría de gasto.
- «Otra contabilidad» aparca un movimiento que no es propio: sigue moviendo el banco, para que el saldo cuadre con el extracto, pero no suma en ingresos ni gastos ni viaja al centro. Después se le puede poner una categoría normal.
- «Traspaso a caja» (o «Traspaso desde caja» en un ingreso) convierte la línea en un traspaso entre banco y caja: no es ingreso ni gasto del plan, solo mueve efectivo entre tus dos tesorerías.
- Debajo aparecen las **plantillas del centro** (p. ej. «Club»): en tu libro personal queda una sola salida; al aceptar la remesa, el centro ejecuta la plantilla completa (P y G).
- El fichero no puede pasar de 2 MB.
- **Extracto de uno de los bancos admitidos: no hay que preparar nada.** Se sube tal como lo descarga el banco, sin abrirlo ni retocarlo. El programa lo reconoce por las cabeceras que trae de origen; si se cambian las columnas o se guarda con otro programa, puede dejar de reconocerlo.
- **Otro banco, o movimientos de otra procedencia: formato propio.** Se prepara un fichero CSV o Excel con una primera fila de cabecera con tres columnas, **Fecha**, **Concepto** e **Importe** (sin ninguna más), y debajo un movimiento por fila. La fecha, como 15/01/2026 (o 2026-01-15). El importe, en positivo si entra dinero y en negativo si sale, con coma o punto decimal (-23,50). En CSV las columnas se separan con punto y coma. Ejemplo:

  ```
  Fecha;Concepto;Importe
  15/01/2026;Compra de papelería;-23,50
  16/01/2026;Donativo recibido;100,00
  ```

  En la pantalla, dentro de las instrucciones de importar, **Descargar plantilla** baja un fichero de ejemplo para rellenar. Se importa igual que un extracto de banco: las líneas quedan en «Por categorizar» y volver a subir el mismo fichero no duplica nada.
- **Qué es un CSV.** Es un fichero de texto con los movimientos, una línea por movimiento y los datos separados por comas o punto y coma (termina en `.csv`). Es uno de los formatos que ofrece el banco al descargar los movimientos; también puede ofrecer Excel (`.xls` o `.xlsx`). Sirve cualquiera de los que el programa admite para ese banco.

## Problemas frecuentes

- **«El fichero CSV está vacío» o «No hay movimientos en el extracto»**: se descargó sin cargar antes todo el periodo, o no es el extracto del banco elegido.
- **«No se reconoce el formato del extracto. Use un CSV o Excel de N26, CaixaBank, BBVA, Banco Santander, Banco Sabadell o Arquia, o un fichero propio con las columnas Fecha, Concepto e Importe.»**: el fichero no es el extracto de uno de esos bancos, o se ha modificado después de descargarlo: volver a descargarlo y subirlo sin tocar. Si es de otro banco, pasarlo al formato propio (Fecha, Concepto e Importe); si ya lo es, comprobar que la cabecera está en la primera fila y que no hay más columnas que esas tres.
- **«Fila …: Fecha no reconocida»** o **«Fila …: Falta la cantidad»** (formato propio): esa fila del fichero tiene la fecha mal escrita o el importe vacío; el número es la fila contando la cabecera. No se importa nada hasta corregirla.
- **«Este banco solo admite CSV»**: se ha subido un Excel a un banco que no lo acepta.
- **«Formato Excel no soportado: use .xls o .xlsx»**: guardar el fichero en uno de esos formatos.
- **Movimientos «omitidos»**: caen en fechas que el centro no tiene abiertas y no se han podido anotar.
- **«Elija una categoría del plan»**: se ha dejado «Por categorizar» en el desplegable.
