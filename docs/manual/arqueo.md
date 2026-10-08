# Arqueo

- Ruta: `/arqueo-p` y `/arqueo-g`
- Ámbito: centro-n, centro-sg, asociacion, fundacion
- Quién: secretario del centro

## Centro n

- Menú: Resúmenes → **Arqueo Caja** o **Saldo banco** (rutas `/arqueo-p` y `/arqueo-g`). **Caja** cuenta el efectivo de la caja física (saldo contable P+G en esa caja). **Saldo banco** no recuenta billetes: se anota el importe del extracto y se compara con el saldo contable P+G en esa cuenta. No es un arqueo «solo del libro P» o «solo del G». Enlaces también desde Fecha cierre y desde el 613 G (caja y banco).

## Para qué sirve

En **Arqueo Caja** se cuenta el efectivo y se compara con la contabilidad (billetes, monedas, vales y cheques). En **Saldo banco** se introduce el saldo del extracto y se compara con el contable; no hay desglose de billetes.

## Cómo se usa

1. Entrar desde el menú, desde el enlace **Arqueo** del 613 o desde Fecha cierre. Arriba sale el saldo contable de esa caja y su reparto entre el libro personal y el general.
2. Si el centro tiene más de una caja, elegirla en **Caja física**. La **Fecha** propone el día de hoy y se puede cambiar (por ejemplo para un recuento de otro día).
3. En **Billetes** y **Monedas**, poner cuántas unidades hay de cada valor. No es el importe, sino el número de billetes o monedas.
4. En **Vales / cheques** sí se pone el importe de cada uno (hasta dos vales y dos cheques).
5. Mirar el **Total** y la **Diferencia**: sale en verde cuando es cero y en rojo cuando no.
6. Pulsar **Guardar arqueo**.

## Reglas que conviene saber

- El recuento se compara con el saldo físico de esa caja, que es la suma de lo que le corresponde en el libro personal y en el general: el dinero es el mismo, aunque la contabilidad lo reparta en dos libros.
- El total es el dinero contado más los vales y cheques, desglosados debajo.
- Guardar el arqueo no crea ni corrige apuntes: solo deja constancia del recuento. El descuadre se arregla corrigiendo apuntes.
- El bloque final del 613 G propone **Dinero y vales de Caja** y **Dinero en banco** con el último arqueo o saldo guardado hasta la fecha de cierre (si no había ya un valor manual en el 613).
- Tras **Guardar arqueo** aparece un mensaje de confirmación con el total.
- Si la diferencia no es cero pero es múltiplo de nueve, aparece **Buscar capuchinos**: eso suele pasar al escribir dos cifras al revés o al correr la coma. Se listan los apuntes de caja que, cambiados así, explicarían el descuadre, con el importe **Anotado**, el que tendría que ser en **Si fuera** y un enlace al apunte.

## Problemas frecuentes

- **La diferencia no es cero**: volver a contar y revisar los apuntes de caja del mes.
- **No aparece Buscar capuchinos**: solo sale si la diferencia no es cero y es múltiplo de nueve.
- **«Ningún apunte de caja encaja»**: no hay ninguno cuyas cifras invertidas cuadren. Puede haber dos errores a la vez, o ser un apunte posterior a la fecha indicada.

## Centro sg

- Ruta: `/arqueo-p` (caja física)
- Menú: Cierre y arqueo → Arqueo

Recuento de la caja del único libro. Se entra también desde el enlace **Arqueo Caja** del 613. No hay reparto entre libro personal y general (solo hay un libro).

## Asociación y fundación

- Ruta: `/arqueo`
- Menú: Arqueo

Recuento de caja y banco hasta una fecha. La tabla muestra tesorería, saldo contable, contado y diferencia. Si cuadra, **Dar por cuadrado**: un cambio posterior de apuntes que mueva ese saldo avisa del descuadre. Debajo queda la lista de periodos cuadrados.
