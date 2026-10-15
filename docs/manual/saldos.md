# Saldos

- Ruta: `/saldos`
- Ámbito: centro-n, asociacion, fundacion
- Quién: secretario del centro

## Centro n

- Menú: Utilidades → Saldos

## Para qué sirve

Muestra, a una fecha concreta, cuánto dinero hay en caja y en banco y cuánto le corresponde a cada persona en su cuenta personal. Es el repaso rápido de antes de cerrar el mes: si algo no cuadra, aquí se ve.

Además tiene un botón que ejecuta unas revisiones automáticas y señala, persona a persona, dónde está el problema.

## Cómo se usa

1. Al entrar, la casilla «Hasta» viene rellena con la fecha de cierre del centro. Se puede cambiar por cualquier otra.
2. Pulsar **Calcular** para rehacer las cifras con esa fecha.
3. Arriba aparece el resumen: dinero en Caja, en Banco y el saldo global de las cuentas personales.
4. La tabla **Tesorería física** pone una fila por cada caja o cuenta bancaria real del centro, con su parte en el libro personal (P), en el general (G) y el total físico.
5. La tabla **Cuentas personales** muestra, por persona, el cuadre de apuntes **A**, **C** y **B** (positivo si ha ingresado más de lo que ha gastado; debería ser cero), el **saldo contable** (concepto 9), la **deuda de vivienda** del cierre y el **superávit** (lo ingresado en vivienda por encima de la cuota, todavía sin usar). Las filas con algún valor distinto de cero se resaltan. **Saldar (importe)** aplica primero ese superávit y después el saldo contable positivo, hasta cubrir la deuda. El importe no se teclea.
6. **Ejecutar comprobaciones** añade abajo tres revisiones: que no haya apuntes descuadrados, que la suma de todas las cuentas personales sea cero y el detalle por persona.

## Reglas que conviene saber

- Se cuenta desde la fecha de inicio del ejercicio hasta la fecha indicada; los apuntes posteriores no entran.
- **A:** apuntes que no mueven caja ni banco; deberían cuadrar (ingresos = gastos). En la tabla, positivo = más ingresos que gastos.
- **C** y **B:** apuntes desde caja o banco del centro; misma regla de signo y también deberían cuadrar por persona.
- **Saldo cont.** (concepto **9**): saldo de la cuenta **CC** en el libro P (persona–centro). La suma es el **IX del 613 P**. No es el dinero en el banco ni la deuda de vivienda del cierre.
- **Deuda viv.**: vivienda ya imputada en el cierre (P/211 y G/11). Negativa = debe. **Superávit**: lo ingresado en G/11, sin el cierre automático, por encima de la cuota acumulada (gastos G 201–215 repartidos, o el importe fijo). La cuota no está en una cuenta: se recalcula. **Saldar (importe)** aplica primero el superávit y, si no alcanza, el saldo contable positivo. No crea otro ingreso ni otro 211. Lo que sobre de deuda sigue pendiente. La columna Vivienda del E37 no cambia.
- **Cuenta corriente (banco)** (concepto **90**, informativo): caja y banco que la persona comunica en la remesa. No genera apuntes. El disponible hacia partidas 7 es esa cifra menos el remanente. En el 613 P, la línea **90** suma la última remesa aceptada de cada persona hasta el mes de cierre. Quien no tiene remesa aceptada no suma.
- Saldo cont. negativo: falta una contrapartida A que no es la deuda del cierre (esa va en Deuda viv.). Puede coexistir con arqueo físico cuadrado.
- El total físico de una caja es la suma de lo que hay en P y en G: el dinero es el mismo, aunque la contabilidad lo reparta en dos libros.
- Caja y Banco del resumen suman todas las cajas y bancos del centro.

## Problemas frecuentes

- **Avisa de que hay apuntes descuadrados**: algún apunte se grabó con un lado distinto del otro. Hasta corregirlo, los saldos no son fiables.
- **Hay deuda y no aparece Saldar**: no hay superávit de vivienda ni saldo contable positivo. El superávit sale solo si lo ingresado en vivienda supera la cuota. Si no, anota antes el ingreso (procedencia A). El botón muestra entre paréntesis lo que se puede saldar.
- **Una persona sale con saldo contable distinto de cero**: le falta la contrapartida de un apunte A que no es el cierre. En las comprobaciones se propone el importe y el concepto (111, 211 o 212). P/211 manual imputa a generales (cuadra con G/11); P/212 es vivienda personal.
- **Los saldos parecen antiguos**: revisar la fecha «Hasta» y volver a pulsar Calcular.

## Asociación y fundación

- Menú: Movimientos → Saldos

Dinero en caja y en banco a una fecha. No hay cuentas personales ni columna c/c.
