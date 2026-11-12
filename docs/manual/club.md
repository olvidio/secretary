# Club: importar Grisbi y listados

- Ruta: `/listados`
- Ámbito: asociacion, fundacion
- Menú: Movimientos → Listados. La importación Grisbi está en Parámetros → Configuración.

## Para qué sirve

Llevar la contabilidad de un club en un centro con plan **Club**: un solo libro (el general), caja y banco, y las cuentas del club. Un fichero Grisbi (`.gsb`) nuevo incorpora sus categorías, movimientos e informes. Los listados se pueden crear aquí sin volver a Grisbi.

## Cómo se usa

1. El centro ya está en el plan **Club**.
2. En **Parámetros → Configuración**, bloque **Importar Grisbi**, subir el `.gsb`. Las categorías que no existan se crean como cuentas del libro G. Cada movimiento pasa a un asiento (entrada de dinero al debe de caja o banco; salida al haber). Un traspaso entre caja y banco es un solo asiento. El saldo inicial va contra la cuenta de apertura.
3. Volver a importar el mismo fichero no duplica: cada movimiento de Grisbi queda ligado a su asiento.
4. Los informes del fichero aparecen en **Listados**.
5. En **Cuentas** (plan del libro G) cada cuenta propia lleva al lado su **código oficial**: la cuenta del Pla de comptabilitat de les associacions de Catalunya (Decret 259/2008) a la que corresponde. La numeración del club es propia, por finalidad (60 actividad, 61 generales, 62 Neteja con 62.1 Sous, 62.2 Seguretat Social y 62.3 Gestoria…); el código oficial sirve para trasladarla al plan oficial sin renumerar: 640 sueldos, 642 Seguridad Social, 623 profesionales (gestoría), 628 suministros, 622 mantenimiento, 625 seguros, 626 gastos bancarios, 631 tasas, 721 cuotas de socios, 728 donativos, y 120 Romanent para el saldo inicial (APERTURA). Las cuentas de la plantilla vienen con uno propuesto; se cambia escribiendo en la casilla (de dos a cuatro cifras, o vacío para quitarlo). Los grupos (60, 61, 62…) no llevan.
6. En **Listados** hay dos pestañas. **Existentes** muestra los que ya hay. **Ver** abre el informe en otra ventana, con el periodo entre paréntesis en el título, para imprimirlo; desde ahí se vuelve a la lista o se edita. **Nuevo** tiene secciones: nombre, fechas (ejercicio actual, el anterior, u otras fechas), cuentas, transferencias, categorías, terceros, textos, importes, organización y columnas. En organización se agrupa por categoría o por cuenta (el orden se cambia con Subir y Bajar); al agrupar sale una línea por grupo con su total, en vez de la lista de movimientos (si la categoría es el último grupo, cada categoría madre, como 61 Despeses generals, abre con una fila con su título y el total de sus hijas), y para ver el detalle hay que desmarcar los grupos; se pueden separar ingresos y gastos (según la naturaleza de la categoría, no el signo del movimiento: una devolución de un gasto sigue en Gastos; una categoría hija sigue a su madre, así 90.1 Ingressos sale en Gastos porque 90 Aiguafreda es de gasto) o por día, semana, mes o año, y se elige el orden de los movimientos. En columnas se marca qué sale en cada línea: fecha, cuenta, categoría, glosa, debe, haber e importe (haber menos debe en una sola columna: los ingresos salen en positivo y los gastos en negativo). Con **Saldo inicial y final** marcado, el listado abre con el saldo de caja y banco al empezar el periodo (la apertura) y cierra con el resultado y el saldo final, que es el dinero que hay realmente; si se marcan cuentas de caja o banco, el saldo es solo el de esas; no sale si el listado filtra por categorías, terceros o texto, ni con fechas que abarquen más de un ejercicio. Actual y anterior se recalculan cada año. Guardar deja el listado en Existentes.

## Reglas

- Solo un centro con plan Club. En un centro H16n la API responde que no está disponible.
- El movimiento tiene que caer dentro de un ejercicio del centro. Si la fecha no entra en ninguno, se omite.
- Un listado sin cuentas incluye todas. Un listado sin terceros no filtra por glosa.

## Problemas frecuentes

- «La importación Grisbi solo está disponible en un centro con plan Club»: el centro activo no tiene ese plan.
- Movimientos omitidos: la fecha no cae en ningún ejercicio, el importe es cero, o ese número de Grisbi ya se importó.
