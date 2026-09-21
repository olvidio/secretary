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
- En ese modo aparece **← Volver** arriba para salir del cuadre y volver al listado normal con los mismos filtros.
- Si encuentra en el libro general un movimiento del mismo importe que explicaría el descuadre, lo indica y ofrece **Ver en G** (abre el listado del general en esa fecha) y, según el caso, **Aceptar (P/211 y G/11)** o **Aceptar (ingreso P/111)** para anotar lo que falta sin teclearlo. Todos piden confirmación.

### Modo cuadre: qué botón pulsar

El programa busca en G un movimiento del **mismo importe** que el descuadre de ese día en P/A. Según el tipo de movimiento en G y el signo del saldo del día, ofrece uno de estos atajos (nunca los dos a la vez):

| Situación habitual | Saldo del día en P/A | Movimiento en G | Botón |
| --- | --- | --- | --- |
| Pagaste un recibo de casa en G (luz, agua…) y falta la vivienda personal | Negativo (sobran gastos) | Gasto G (p. ej. concepto 11) | **Aceptar (P/211 y G/11)** |
| Mismo caso, pero esa persona **no aporta a generales** | Negativo | Gasto G | **Aceptar (P/211 y G/11)** → anota P/212 sin G/11 |
| En G hay un **ingreso** del mismo importe (p. ej. ingreso 11) | Negativo | Ingreso G | **Aceptar (P/211 y G/11)** |
| En G hay una **devolución** (gasto con importe negativo) y en P sobran gastos por esa cantidad | Positivo (falta ingreso) | Gasto negativo (abono) | **Aceptar (ingreso P/111)** |

Ejemplo de vivienda: el día 15 hay gastos P/A por 500 € y ningún ingreso; en G figura un gasto 11 de 500 € por el recibo de luz. El saldo del día es −500 €. El aviso propone **Aceptar (P/211 y G/11)**: crea el gasto P/211 y, si la persona aporta a generales, el ingreso G/11 emparejado (el G/11 del recibo ya existía).

Ejemplo de devolución: en G hay un abono de −120 € y en P/A ese día hay gastos que no cuadran con un saldo positivo. **Aceptar (ingreso P/111)** anota el ingreso 111 en P como contrapartida; el apunte de G no se toca.

Si el aviso no encaja o no hay candidato en G, corregir a mano en Entrada P/G o usar **Ver en G** para revisar antes de decidir.

## Problemas frecuentes

- **«Apunte no encontrado»**: la fila ya se había borrado; volver a filtrar para refrescar el listado.
- **Una fila no tiene botones de editar ni borrar**: viene de una remesa.
- **El saldo de un día no cuadra**: casi siempre falta el ingreso de contrapartida (el 111) o el gasto de vivienda que hace pareja. El aviso de esa fecha indica qué movimiento del libro general parece ser el que falta.
- **Al guardar una edición sale un aviso de ejercicio cerrado**: el apunte cae en un ejercicio que ya se cerró; hay que reabrirlo antes de corregirlo.
