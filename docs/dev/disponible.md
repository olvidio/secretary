# Disponible y destinos 7

Tras aceptar una remesa, el neto (ingresos − gastos, tipo A) se **aparca** en
`DISP.<INICIALES>` para dejar `CC.*` a cero. El valor operativo vive en
`saldos_disponibles` (ajustable a mano). La tesorería enviada en la remesa
puede **sustituir** ese disponible.

La propuesta de destinos 7 reparte según presupuesto y tramos de desgravación
(configurables). Quien no puede desgravar (Nombres) va a partidas 7 con
`desgrava = false`. Al confirmar se apuntan gastos 7 contra DISP. Las 7 que
la persona ya hizo viajan en la remesa; las de la propuesta no se vuelven a
imputar (se consumen contra `pendiente_cents`).
