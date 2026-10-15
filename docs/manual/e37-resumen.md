# Resumen de cuentas personales

- Ámbito: centro-n
- Ruta: `/e37-resumen`
- Menú: Personales → Resumen E37
- Quién: secretario del centro

## Para qué sirve

Es la vista de conjunto de las cuentas personales: una línea por persona del centro con sus totales del ejercicio. En vez de abrir la hoja de cada uno, se ve todo de golpe y se detecta enseguida quién se sale de lo normal.

## Cómo se usa

1. Entrar en la pantalla; la tabla se calcula sola, sin filtros.
2. Repasar las columnas.
3. Pulsar **Imprimir** si hace falta llevarlo en papel.

## Reglas que conviene saber

- Se cuenta desde la fecha de inicio del ejercicio hasta la fecha de cierre del centro, por **fecha de imputación** (igual que la hoja E37). Lo imputado después no entra.
- Aparecen todas las personas dadas de alta en el centro, también las que no tengan movimientos; esas salen a cero.
- **Ingresos**: suma de los ingresos personales (trabajo, familia y varios) y de los extraordinarios.
- **Gastos**: suma de los gastos personales, es decir vivienda, ordinarios, ropa, «ca, crt, cv», médicos, coche y viajes, estudios y obligaciones económicas.
- **Disponible**: Ingresos menos Gastos.
- **Ay. fam.**: las ayudas familiares.
- **Lab. ap.**: las otras labores apostólicas.
- **Saldo F.**: el Disponible menos las ayudas familiares, menos la atención de labores, menos las necesidades de la sede y menos las labores apostólicas. Es la columna que más se pregunta: descuenta dos conceptos (atención de labores y necesidades de la sede) que **no tienen columna propia** en esta tabla, así que no cuadra restando solo lo que se ve. Para ver esos importes hay que abrir la hoja de la persona en Cuentas personales.
- **Saldo cont.**: cuenta CC (concepto 9). Debería estar a cero. No es la deuda del cierre.
- **Deuda viv.**: vivienda del cierre pendiente de cobro. Si sale en negativo, en Saldos pulsar **Saldar** (superávit de vivienda y, si falta, saldo contable; no aumenta el 111).
- **Sup. viv.**: lo ingresado en vivienda por encima de la cuota. Si tiene importe, el Disponible y el saldo F. quedan más bajos porque ese exceso está en los gastos de vivienda. La nota bajo la tabla lo explica.

## Problemas frecuentes

- **Saldo F. no cuadra con las columnas visibles**: es lo normal; también resta la atención de labores y las necesidades de la sede, que aquí no se muestran.
- **Deuda viv. distinta de cero**: en Saldos, **Saldar** (superávit de vivienda y, si falta, saldo contable).
- **Saldo cont. distinto de cero**: falta una contrapartida A que no es el cierre. La pantalla Saldos indica el importe.
- **Faltan movimientos del último mes**: la tabla corta por la fecha de cierre; hay que actualizarla en Fecha de cierre.
