# Ejercicios

- Ruta: `/ejercicios`
- Menú: Utilidades → Ejercicios
- Quién: secretario del centro

## Para qué sirve

Da de alta los ejercicios contables del centro y permite cerrarlos, reabrirlos y rehacer su saldo de partida. Un ejercicio no tiene que ir de enero a diciembre: puede empezar y acabar cuando corresponda.

La tabla muestra sus fechas, cuántos meses abarca, cuántos han transcurrido y si está abierto o cerrado.

## Cómo se usa

1. Poner la fecha de inicio y la fecha de fin del ejercicio.
2. Si se quiere, indicar la fecha de corte y una etiqueta.
3. Pulsar «Crear ejercicio».
4. Cuando el ejercicio termina y no hay que anotar nada más, pulsar «Cerrar» en su fila.
5. Para corregir algo de un ejercicio cerrado, «Reabrir»; para recalcular su saldo de partida, «Regenerar apertura». Las tres acciones piden confirmación.

## Reglas que conviene saber

- La fecha de inicio y la de fin delimitan el ejercicio completo. La **fecha de corte** solo dice hasta dónde hay datos introducidos, para que los resúmenes a mitad de ejercicio salgan bien; al cerrar, el corte pasa a ser la fecha de fin.
- Si se deja la etiqueta vacía, se pone el año, o algo como «2026-27» cuando abarca dos.
- Dos ejercicios del mismo centro no pueden solaparse, y solo puede haber uno abierto: hay que cerrar el anterior antes de crear el siguiente.
- Al crear un ejercicio que continúa a uno ya cerrado, los saldos finales del anterior se arrastran solos como saldo de partida («disponible a 1 de enero», el concepto 32 del libro general). En el primer ejercicio cargado, que no tiene anterior, ese disponible se teclea a mano.
- Un ejercicio cerrado no admite apuntes nuevos.

## Problemas frecuentes

- **«El ejercicio se solapa con …»**: las fechas pisan otro ejercicio ya dado de alta.
- **«Cierre primero el ejercicio …»**: hay uno abierto; cerrarlo antes de crear el nuevo.
- **«El ejercicio … ya tiene movimientos; no se puede reabrir …»**: el ejercicio siguiente tiene ya apuntes propios, así que el anterior queda fijo.
- **«Este ejercicio no tiene anterior: la apertura es manual»**: en el primer ejercicio el disponible de partida se teclea.
- **«El ejercicio anterior tiene … descuadrados; corríjalos antes de generar la apertura»**: hay apuntes del ejercicio anterior a los que les falta su pareja; revisarlos en Apuntes.
- **«No hay ningún centro dado de alta todavía»**: primero hay que crear el centro.
