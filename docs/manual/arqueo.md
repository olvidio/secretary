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
- **Sale una diferencia grande** (cientos o miles de euros): no suele ser un error de recuento, sino de apuntes. Repasar, por este orden: que la **Caja física** (o la cuenta de banco) elegida sea la que se ha contado; que la **Fecha** sea la del recuento, porque el saldo contable solo cuenta los apuntes hasta ese día; que los apuntes lleven la procedencia correcta (**B** si el dinero pasó por el banco, **C** si pasó por la caja, **A** si no tocó ninguno de los dos): un apunte de banco anotado como caja descuadra los dos; que los movimientos entre banco y caja estén anotados como traspaso (conceptos 41 y 42) y no falte ninguno; y, en **Saldo banco**, que no queden movimientos del extracto sin importar o sin asignar. Guardar el arqueo no corrige nada: la diferencia desaparece al corregir los apuntes.
- **No aparece Buscar capuchinos**: solo sale si la diferencia no es cero y es múltiplo de nueve.
- **«Ningún apunte de caja encaja»**: no hay ninguno cuyas cifras invertidas cuadren. Puede haber dos errores a la vez, o ser un apunte posterior a la fecha indicada.

## Centro sg

- Ruta: `/arqueo-p` (caja física)
- Menú: Cierre y arqueo → Arqueo

Recuento de la caja del único libro. Se entra también desde el enlace **Arqueo Caja** del 613. No hay reparto entre libro personal y general (solo hay un libro).

## Asociación y fundación

- Ruta: `/arqueo`
- Menú: Arqueo

Recuento de caja y banco hasta una fecha. La tabla muestra tesorería, saldo contable, contado y diferencia.

- **Caja**: debajo de la tabla hay un bloque **Recuento de** la caja, igual que en los centros: cuántos billetes y monedas hay de cada valor, y el importe de vales y cheques. El contado y la diferencia se calculan solos.
- **Banco**: se escribe el saldo del extracto en la casilla Contado.
- **Guardar recuento** guarda el conteo de la caja con la fecha de la pantalla, cuadre o no. Si ese día se vuelve a contar, vale el último. Al elegir una fecha que ya tiene recuento, aparece relleno.
- **Dar por cuadrado** solo se activa si caja y banco cuadran; guarda también el recuento. Un cambio posterior de apuntes que mueva ese saldo avisa del descuadre.
- **Recuentos anteriores**: una fila por día y caja con lo contado, el saldo contable de ese día y la diferencia («cuadra» si no hay). **Ver** lleva a esa fecha con su conteo de billetes y monedas.
- Debajo queda la lista de periodos cuadrados.
