# Entradas periódicas (centro sg)

- Ruta: `/entradas-periodicas` y `/ejecutar-entradas-periodicas`
- Ámbito: centro-sg
- Quién: secretario del centro

## Para qué sirve

Permite definir apuntes que se repiten (mensual, trimestral o anual) y contabilizarlos cuando tocan, sin teclearlos uno a uno en Entrada.

## Definición (`/entradas-periodicas`)

1. Rellenar concepto (al inicio del desplegable aparecen las **plantillas** de Cuentas → Plantillas), observaciones, cantidad y periodicidad. **Iniciales** puede quedar en blanco si el apunte no va asociado a una persona.
2. Indicar la **fecha de referencia**: el día del mes en que debe caer cada ocurrencia; en trimestral y anual también marca el mes de partida.
3. **Guardar**. El listado inferior permite **Editar** o **Borrar** cada definición.

La contrapartida al ejecutar es la caja, igual que en Entrada del plan H16s.

## Ejecución (`/ejecutar-entradas-periodicas`)

1. Se listan las ocurrencias pendientes hasta la fecha indicada (por defecto, hoy).
2. Todas van marcadas; desmarque las que no quiera anotar ahora.
3. **Ejecutar seleccionados** crea los apuntes en G y registra que esa fecha ya se contabilizó para esa definición.

## Reglas que conviene saber

- Solo está disponible en centros del plan H16s (centro sg).
- La cantidad es siempre positiva; el signo lo pone el concepto.
- Si un mes no tiene el día de referencia (p. ej. 31 en febrero), se usa el último día de ese mes.

## Problemas frecuentes

- **«Esta función es del plan H16s»**: el centro no es tipo sg.
- **«La fecha … no está pendiente»**: ya se ejecutó esa ocurrencia o la fecha no corresponde al calendario de la definición.
- **«No hay ejercicio que cubra la fecha …»**: igual que en Entrada: revisar ejercicios o fechas.
