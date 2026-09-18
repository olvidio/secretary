# Disponible y destinos 7

Tras aceptar una remesa, el neto (ingresos − gastos, tipo A) se **aparca** en
`DISP.<INICIALES>` para dejar `CC.*` a cero. El valor operativo vive en
`saldos_disponibles` (ajustable a mano). La tesorería enviada en la remesa
puede **sustituir** ese disponible.

La propuesta de destinos 7 reparte según presupuesto, tramos de desgravación
(configurables: 250 € al 80 % y resto al 40 % por defecto) y el **tope** del
art. 69.1 LIRPF: `maximo_pct` % (10 por defecto) de la `base_liquidable` de
cada persona (si está vacía, el 111 de la previsión o el 111 proyectado a
fin de ejercicio). Lo que pase de ese tope va a partidas 7 con `desgrava = false`.
Quien no puede desgravar (Nombres) va entero a esas partidas. Al confirmar se
apuntan gastos 7 contra DISP. Las 7 que la persona ya hizo viajan en la remesa;
las de la propuesta no se vuelven a imputar (se consumen contra `pendiente_cents`).
