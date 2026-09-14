# Apuntes

- Ruta: `/apuntes`
- Menú: Personales y generales → Apuntes
- Quién: secretario del centro

## Para qué sirve

Es el listado de todo lo anotado en el centro, con filtros. Sirve para buscar un apunte concreto, corregirlo o borrarlo, y para localizar descuadres en los apuntes de una persona.

## Cómo se usa

1. Ajustar los filtros: libro (P, G o los dos), procedencia (A, B o C), iniciales, concepto y fechas.
2. Pulsar «Filtrar». El listado sale ordenado por fecha.
3. Para corregir una fila, pulsar «Editar»: se abre una ventana con todos los campos y el cursor puesto en observaciones. Guardar aplica el cambio; Escape o «Cancelar» lo descarta.
4. Para quitar una fila, «Borrar» y confirmar.

## Reglas que conviene saber

- El orden y los filtros de fecha van por la fecha en que el movimiento se cuenta. Si la operación se hizo otro día, ese otro día aparece entre paréntesis.
- La ventana de edición está pensada para retocar observaciones. Si se cambia además otro campo, al guardar pide confirmación.
- Los apuntes que llegan de una remesa no se editan ni se borran aquí: para corregirlos, la persona vuelve a enviar su remesa.
- Filtrando por libro P, procedencia A y unas iniciales, la pantalla entra en modo de revisión: agrupa por fecha, calcula el saldo de cada día (los gastos suman y los ingresos restan), marca las fechas que no cuadran y muestra el saldo total de esa persona.
- En ese modo, si encuentra en el libro general un movimiento del mismo importe que explicaría el descuadre, lo dice y ofrece dos atajos: «Ver en G», que lleva al listado del general en esa fecha, y un botón para anotar lo que falta. Ambos piden confirmación.

## Problemas frecuentes

- **«Apunte no encontrado»**: la fila ya se había borrado; volver a filtrar para refrescar el listado.
- **Una fila no tiene botones de editar ni borrar**: viene de una remesa.
- **El saldo de un día no cuadra**: casi siempre falta el ingreso de contrapartida (el 111) o el gasto de vivienda que hace pareja. El aviso de esa fecha indica qué movimiento del libro general parece ser el que falta.
- **Al guardar una edición sale un aviso de ejercicio cerrado**: el apunte cae en un ejercicio que ya se cerró; hay que reabrirlo antes de corregirlo.
