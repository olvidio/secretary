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
5. En **Listados** hay dos pestañas. **Existentes** muestra los que ya hay. **Ver** abre el informe en otra ventana, con el periodo entre paréntesis en el título, para imprimirlo; desde ahí se vuelve a la lista o se edita. **Nuevo** tiene secciones: nombre, fechas (ejercicio actual, el anterior, u otras fechas), cuentas, transferencias, categorías, terceros, textos, importes, organización y columnas. En organización se agrupa por categoría o por cuenta (el orden se cambia con Subir y Bajar), se pueden separar ingresos y gastos o por día, semana, mes o año, y se elige el orden de los movimientos. En columnas se marca qué sale en cada línea: fecha, cuenta, categoría, glosa, debe y haber. Actual y anterior se recalculan cada año. Guardar deja el listado en Existentes.

## Reglas

- Solo un centro con plan Club. En un centro H16n la API responde que no está disponible.
- El movimiento tiene que caer dentro de un ejercicio del centro. Si la fecha no entra en ninguno, se omite.
- Un listado sin cuentas incluye todas. Un listado sin terceros no filtra por glosa.

## Problemas frecuentes

- «La importación Grisbi solo está disponible en un centro con plan Club»: el centro activo no tiene ese plan.
- Movimientos omitidos: la fecha no cae en ningún ejercicio, el importe es cero, o ese número de Grisbi ya se importó.
