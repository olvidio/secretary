# Comprobaciones personales / generales

- Ámbito: centro-n
- Ruta: `/comprobaciones`
- Menú: Utilidades → Comprobaciones
- Quién: secretario del centro

## Para qué sirve

Contrasta el libro personal con el general y avisa de tres cosas que se escapan con facilidad: que la vivienda que cada persona paga esté también reflejada en el libro general, que nadie se haya quedado sin anotar movimientos en algún mes, y que ninguna partida 7 (labores) se haya apuntado dos veces.

## Cómo se usa

1. Al entrar, «Hasta» viene con la fecha de cierre del centro. Se puede cambiar.
2. Pulsar **Ejecutar**.
3. Aparecen tres bloques, cada uno con una marca de correcto o incorrecto y su tabla de detalle.

## Reglas que conviene saber

- **Vivienda P/211 y generales G/11.** P/211 es vivienda general (cuadra con G/11); P/212 es vivienda personal y no entra aquí. En Nombres se indica quién entra en el cierre automático; quien no aporta puede igualmente imputar a generales puntualmente (211/11). La tabla enseña, por persona, el importe de cada lado, la diferencia y enlaces para ver esos apuntes.
- Esto no depende de si el centro es de un tipo o de otro: en el mismo libro puede haber personas que aporten y personas que no.
- Quien no aporta no debe tener nada en el concepto 11 del general. Si lo tiene, se avisa aparte.
- **Meses sin movimiento.** Se repasa mes a mes, desde la fecha de inicio del ejercicio hasta el mes de la fecha de cierre, quién no tiene ningún apunte. Los apuntes del cierre automático y el de saldo inicial no cuentan como movimiento.
- Si en Nombres una persona está exenta en ciertos meses (por llegada o salida a mitad de curso), esos meses no se le exigen.
- **Partidas 7 apuntadas dos veces.** Avisa cuando una asignación de destinos 7 (el asiento «Asignación labores» que se anotaba al confirmar en Disponible) coincide con otro apunte de la misma persona, partida e importe, normalmente la remesa que trae la transferencia real. Entonces ese gasto cuenta dos veces en el 613 P y la c/c de la persona queda negativa por ese importe. La tabla enseña la fecha y el número de los dos apuntes. **Anular asignación** anula la asignación y su liquidación de c/c (pide confirmación): el gasto queda una sola vez, el 111 no cambia y la c/c recupera el importe. Estos asientos no se pueden borrar desde Apuntes.
- Solo se anula si la asignación está repetida entera. Si solo una parte coincide, o no se encuentra su liquidación de c/c, el programa no la anula y hay que revisarla a mano.

## Problemas frecuentes

- **Alguien aparece con diferencia en vivienda**: falta uno de los dos apuntes, o los importes no coinciden. Los enlaces de la fila llevan directamente a los apuntes de cada lado. El cierre automático genera los dos a la vez (P/211 y G/11); si Comprobaciones cuadra y aun así la c/c está en negativo, G ya cobró esa vivienda y el desfase está en el libro personal (falta el 111 de esos automáticos). Ver Cierre de mes y E37.
- **Alguien sale en «meses sin movimiento» y no debería**: si de verdad no estaba esos meses, hay que marcarle la exención en Nombres. Si sí estaba, faltan apuntes.
- **Sale una partida 7 repetida y de verdad fueron dos donativos por el mismo importe**: no hay que anular nada; el aviso solo compara persona, partida e importe.
- **Aparece un aviso con unas iniciales desconocidas**: hay apuntes a nombre de una persona que no está dada de alta en Nombres, o con las iniciales mal escritas.
