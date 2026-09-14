# Saldos

- Ruta: `/saldos`
- Menú: Utilidades → Saldos
- Quién: secretario del centro

## Para qué sirve

Muestra, a una fecha concreta, cuánto dinero hay en caja y en banco y cuánto le corresponde a cada persona en su cuenta personal. Es el repaso rápido de antes de cerrar el mes: si algo no cuadra, aquí se ve.

Además tiene un botón que ejecuta unas revisiones automáticas y señala, persona a persona, dónde está el problema.

## Cómo se usa

1. Al entrar, la casilla «Hasta» viene rellena con la fecha de cierre del centro. Se puede cambiar por cualquier otra.
2. Pulsar **Calcular** para rehacer las cifras con esa fecha.
3. Arriba aparece el resumen: dinero en Caja, en Banco y el saldo global de las cuentas personales.
4. La tabla **Tesorería física** pone una fila por cada caja o cuenta bancaria real del centro, con su parte en el libro personal (P), en el general (G) y el total físico.
5. La tabla **Cuentas personales** da el saldo de cada persona. Las filas distintas de cero se resaltan.
6. **Ejecutar comprobaciones** añade abajo tres revisiones: que no haya apuntes descuadrados, que la suma de todas las cuentas personales sea cero y el detalle por persona.

## Reglas que conviene saber

- Se cuenta desde la fecha de inicio del ejercicio hasta la fecha indicada; los apuntes posteriores no entran.
- Los apuntes A son los que no mueven la caja ni el banco del centro.
- El saldo de cada cuenta personal debería ser cero: cada gasto personal lleva su contrapartida, normalmente un ingreso del concepto 111 (Trabajo).
- El total físico de una caja es la suma de lo que hay en P y en G: el dinero es el mismo, aunque la contabilidad lo reparta en dos libros.
- Caja y Banco del resumen suman todas las cajas y bancos del centro.

## Problemas frecuentes

- **Avisa de que hay apuntes descuadrados**: algún apunte se grabó con un lado distinto del otro. Hasta corregirlo, los saldos no son fiables.
- **Una persona sale con saldo distinto de cero**: le falta la contrapartida. En las comprobaciones se propone el importe del apunte que falta y hay un enlace para ver sus apuntes A.
- **Los saldos parecen antiguos**: revisar la fecha «Hasta» y volver a pulsar Calcular.
