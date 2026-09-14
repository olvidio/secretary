# Conceptos básicos

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

Los apuntes P con **concepto 9** (saldo en las cuentas corrientes personales) no
mueven ni la caja ni el banco.

## Año o curso

Lo normal es llevar la contabilidad por año natural, pero el programa admite de
septiembre a agosto. Para eso, en Configuración se escribe **Curso** en lugar de
Año, y **2024** para el curso 2024-2025.

## Vivienda y exención de meses

En Nombres se marca, persona a persona, si su vivienda aporta a generales. Al
cerrar el mes se carga la vivienda solo a quien lo tenga marcado.

Quien llega o se va a mitad de año necesita un **intervalo de exención**: los
meses en los que no paga por gastos del centro. Quien no vive en el centro deja
la exención vacía, y así Comprobaciones avisa si algún mes no tiene movimiento.

## Qué se carga al cerrar el mes

Depende del tipo de centro: en los de **n** se carga Vivienda (21) con su
ingreso general 11; en los de **agd** y **sss+** se carga Necesidades de la casa
o sede (6) con su ingreso general 14. En ambos casos las observaciones las pone
el programa.

## Envío mensual

Los resúmenes mensuales **613 P** y **613 G** se envían antes del día 15 del mes
siguiente.
