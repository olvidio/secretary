# Conceptos básicos

- Ámbito: centro-n
Lo que conviene entender antes de usar cualquier pantalla. Es el contenido de la
antigua hoja «Ayuda (Schema)» del Excel.

## La doble contabilidad: P y G

En cada centro se llevan dos contabilidades a la vez:

- **P (Personal):** ingresos y gastos de las personas.
- **G (General):** ingresos y gastos de la casa.

Los apuntes de las dos se anotan en una única lista y se distinguen con una
**P** o una **G**. Contablemente existen Caja P y Caja G, pero conviene usar una
sola caja física y una sola cuenta bancaria.

## El origen del apunte: A, B o C

En cada apunte se escribe una letra según a qué afecta:

- **B:** es un movimiento de la cuenta bancaria del centro.
- **C:** es un movimiento de la caja del centro.
- **A:** no es ni lo uno ni lo otro.

Los apuntes **A** de una persona deberían cuadrar (ingresos iguales a gastos) si
se anota su contrapartida.

## Conceptos y signo

La cantidad se escribe **siempre en positivo**: el signo lo pone el concepto.
Cada concepto tiene un número; los que más se citan son el **41** (traspaso de
banco a caja), el **42** (traspaso de caja a banco), el **21** (vivienda), el
**11** y el **14** (ingresos de generales), el **111** (trabajo) y el **6**
(necesidades de la casa o sede).

En los traspasos (41 y 42) basta anotar uno: el programa añade solo el apunte
espejo en la otra cuenta.

Los apuntes P con **concepto 9** (saldo contable persona–centro) no mueven ni
la caja ni el banco. El **concepto 90** (cuenta corriente banco) tampoco genera
apuntes: es el saldo de caja y banco que la persona comunica en la remesa, y de
ahí sale el disponible hacia las partidas 7.

## Año o curso

Lo normal es llevar la contabilidad por año natural, pero el programa admite de
septiembre a agosto. Para eso, en Configuración se escribe **Curso** en lugar de
Año, y **2024** para el curso 2024-2025.

## Vivienda y exención de meses

En Nombres se marca, persona a persona, si su vivienda aporta a generales. Al
cerrar el mes se carga la vivienda solo a quien lo tenga marcado.

Quien llega o se va a mitad de año necesita un **intervalo de exención**: los
meses en los que no paga por gastos del centro. Quien no vive en el centro deja
la exención vacía y «vivienda aporta a generales» en no; así Comprobaciones
avisa si algún mes no tiene movimiento. Si el Excel trae exención de 1 a 12,
al importar se convierte en eso.

## Qué se carga al cerrar el mes

Depende del tipo de centro: en los de **n** se carga Vivienda (211) con su
ingreso general 11; en los de **agd** y **sss+** se carga Necesidades de la casa
o sede (6) con su ingreso general 14. En ambos casos las observaciones las pone
el programa.

En un centro n, la contrapartida del 211 automático es la **deuda de vivienda**
de esa persona. El saldo contable (concepto 9) queda para el resto de apuntes A.
La cuota no está guardada en una cuenta: cada mes se recalcula con los gastos
de casa (G 201–215) y quién aporta vivienda.

Si lo ingresado en vivienda, sin el cierre automático, supera esa cuota, el
exceso es **superávit**. Al generar el cierre, ese superávit salda deuda de
meses anteriores. Lo que no cubra sigue pendiente. En **Saldos**, **Saldar**
aplica primero el superávit que aún quede y, si no alcanza, el saldo contable
positivo. El importe lo calcula el programa y sale entre paréntesis en el
botón. La columna Vivienda del E37 no cambia. Un 111 corresponde a trabajo
real que aún no está apuntado.

En la hoja E37, un Disponible o un saldo F. bajos con **Sup. viv.** con importe
significan que esa persona ha puesto en vivienda más de lo que le toca: el
exceso está dentro de la columna Vivienda. La nota al pie de la hoja lo explica.

Esto es el cierre del centro n (libros P y G). El club y el centro sg no tienen
esta deuda ni la hoja E37. Al actualizar el programa, en cada centro n que ya
tuviera datos la vivienda automática que estaba en el saldo contable pasa a la
deuda. El superávit se anota al generar el cierre, y Saldar al pulsarlo.

## Envío mensual

Los resúmenes mensuales **613 P** y **613 G** se envían antes del día 15 del mes
siguiente.
