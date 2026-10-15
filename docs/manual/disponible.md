# Disponible

- Ámbito: centro-n
- Ruta: `/disponible`
- Menú: Personales → Disponible
- Quién: secretario del centro

## Para qué sirve

Guarda, persona a persona, el dinero que aún hay que aplicar a labores apostólicas (las partidas 7: fundaciones, prelatura, etc.). No es la cuenta corriente de las comprobaciones: se puede corregir a mano, porque no siempre coincide con ingresos menos gastos.

Palabras clave: **saldo contable**, **cuenta corriente (banco)**, **colocar en partida 7**, **destinos 7**, **concepto 90**.

## Saldo contable (9) vs cuenta corriente banco (90) y partidas 7

En **Saldos** y **E37** la columna **Saldo cont.** (concepto **9**) es el saldo de la cuenta **CC** (persona–centro). **No** es el banco ni se reparte a mano entre 71, 72…

- Lo normal es **cero** cuando los apuntes A cuadran. La vivienda del cierre no se cuadra con un 111: queda en **Deuda viv.** y se salda en Saldos.
- En la **remesa**, la persona comunica **cuenta corriente (banco)** (concepto **90**, informativo: caja+banco a cierre). De ahí sale el **disponible** que puede ir a partidas 7; **no** crea apuntes en P del centro.
- Las **partidas 7** son gastos de labores apostólicas definidos en Centros → labores apostólicas. **Qué importe va a cada código 7** lo propone el programa, no el secretario mirando la c/c persona a persona.

Orden habitual cuando hay dinero para labores (sobrante de trabajo, no un agujero de vivienda):

1. Terminar apuntes del mes y, si toca, **Cierre de mes** (vivienda).
2. Cada persona **cierra y envía** su mes; el secretario **Acepta** en **Remesas**. Al aceptar, el neto ingresos menos gastos de esa remesa se **aparca** (la c/c de esa persona queda a cero en contabilidad) y el importe pasa al **disponible** operativo de esta pantalla — salvo que se marque **Sustituir el disponible por este importe** con la tesorería que comunicó la persona.
3. **Disponible → Proponer destinos 7**: el programa reparte el disponible entre las partidas 7 del centro (presupuesto, tramos de desgravación, tope de la base liquidable, quién puede desgravar).
4. Revisar el texto «Deberías ingresar…» y **Confirmar instrucciones**. Se reserva el disponible y queda una **instrucción pendiente** (sin asiento en P del centro). La persona lo ve en **Mensajes** y en **Remesa**. Los gastos 7 y la liquidación de c/c se apuntan en P **al aceptar** una remesa suya que incluya esas partidas.

Las 7 que la persona ya anotó en su libro personal viajan en la remesa; **no** hay que volver a cargarlas en Disponible.

Si **Deuda viv.** sale en negativo: **no** se corrige con partidas 7 ni con un 111. En **Saldos**, **Saldar**: primero el superávit de vivienda y, si no alcanza, el saldo contable. El saldo contable negativo, si lo hay, es otro descuadre de apuntes A.

## Cómo se usa

1. Tras aceptar remesas, entrar en **Disponible** y ver el saldo de cada uno.
2. Si hace falta, **Ajustar** el importe.
3. Pulsar **Proponer destinos 7**. El programa reparte intentando cumplir el presupuesto de esas partidas y desgravar lo máximo (mejor tres personas con 100 € en el primer tramo que una con 300 €).
4. Revisar el texto («Deberías ingresar…») y pulsar **Confirmar instrucciones**. Baja el disponible operativo y queda pendiente de remesa (no hay asiento en P hasta aceptar).
5. En **Instrucciones pendientes de remesa** puede **Quitar** una instrucción si ya no aplica (devuelve el disponible y quita el aviso a la persona).
6. La persona ve el texto en **Mensajes** y en **Remesa**; puede **Quitar** el aviso (mismo efecto). Al **aceptar** su remesa con esas 7, la instrucción se cierra sola.

Al aceptar una remesa, si la persona **indicó** un disponible (saldo de su caja y banco menos el remanente en el envío de **datos**, no un pago), se puede marcar **Sustituir el disponible por este importe** para que el disponible operativo del centro pase a coincidir con esa cifra comunicada.

## Reglas que conviene saber

- La **remesa no mueve dinero** entre la persona y el centro: comunica importes. El **disponible** de esta pantalla es contabilidad del centro (labores 7, etc.); se actualiza al **aceptar** remesas y al **Confirmar y apuntar** destinos, no porque entre tesorería por la red.
- Quien en Nombres no puede desgravar recibe solo partidas 7 que no desgravan.
- Qué partidas desgravan se marca en Centros → labores apostólicas.
- Los tramos (p. ej. 250 € al 80 % y el resto al 40 %) y el máximo (10 % de la base liquidable) están en Configuración. La base liquidable se escribe en Nombres; si falta, se toma el 111 de la previsión o el ingreso 111 proyectado a fin de año.
- Las 7 que la persona ya anotó en su libro viajan en la remesa. Las de la propuesta se apuntan al confirmar; cuando las haga después, el programa no las vuelve a cargar.

## Problemas frecuentes

- **He terminado el mes y no sé en qué partida 7 poner los saldos c/c**: no se elige partida a mano. Revisar la c/c en Saldos; si es negativa, cuadrar con 111 (no con 7). Si es positiva o hay sobrante tras **aceptar remesas**, usar **Proponer destinos 7** aquí: el programa reparte entre 71, 72… y al confirmar liquida la c/c.
- **La propuesta está vacía**: nadie tiene disponible (o está a cero). Hay que aceptar remesas o ajustar.
- **Todo va a la prelatura**: esa persona no puede desgravar, o las partidas que desgravan ya cubrieron presupuesto.
- **El disponible no cuadra con ingresos menos gastos**: es normal; por eso se puede ajustar o sustituir por la tesorería enviada.
