# Previsión personal

- Ruta: `/prevision-personal`
- Menú: Presupuestos → Previsión personal
- Quién: secretario del centro

## Para qué sirve

Prepara el presupuesto del libro personal, persona a persona. Cada hoja replica los conceptos del 613 P. La columna **acumulado(anterior)** muestra una **referencia del ejercicio en curso**: lo acumulado hasta la fecha de corte y, entre paréntesis, la previsión ya guardada para ese mismo ejercicio (si existe). La columna **Previsión** es la del **año elegido** (por defecto el del ejercicio siguiente al abierto en el centro).

## Cómo se usa

1. Elegir la **persona**, o **Todos (imprimir)** para ver e imprimir las hojas de todas las personas activas del centro.
2. Elegir el **año** (etiqueta del ejercicio: `2027`, `2026-27`, etc.). Por defecto es el **período siguiente** al abierto, con la misma duración (enero–diciembre, septiembre–agosto u otra). Sale aunque ese ejercicio aún no esté abierto: al guardar se crea en estado planificado y los importes quedan en ese período.
3. Revisar **acumulado(anterior)**: acumulado actual (enteros) y, si hay cifra guardada para el ejercicio abierto, `(previsión actual)`.
4. Escribir en **Previsión** el importe del año elegido (enteros, sin céntimos). **Guardar** solo persiste lo escrito: una casilla vacía deja ese concepto sin importe guardado (equivalente a cero en la previsión consolidada).
5. **Guardar**. Esas cifras quedan ligadas al ejercicio del año seleccionado y son las que luego salen en Previsión, en la columna de esa persona, para ese ejercicio.
6. **Imprimir** saca la hoja en A5 vertical, con los capítulos del 613 P. La cabecera incluye persona, centro y el **año seleccionado**. Si una casilla de previsión está vacía, se imprime la proyección calculada.
7. Con **Todos (imprimir)** no se edita ni guarda: se cargan todas las hojas con **Previsión** vacía (para rellenar a mano), **acumulado(anterior)** sí se imprime, y **Imprimir** usa **A4 horizontal** con **dos hojas tamaño A5** en cada página. Una sola persona sigue en **A5 vertical** con previsión impresa como hasta ahora.

## Reglas que conviene saber

- **acumulado(anterior)** no es una proyección: es solo referencia (acumulado + previsión guardada del ejercicio de trabajo). No influye en lo que se guarda al pulsar **Guardar**.
- Al **imprimir**, si una casilla de previsión sigue vacía, en la hoja puede mostrarse la proyección interna solo como ayuda visual; eso no se guarda en base de datos.
- Si el ejercicio no tiene ejercicio anterior, los puntuales se quedan en lo acumulado de este año.
- Guardar una persona no toca el presupuesto P hasta que en Previsión se pulse «Aplicar al presupuesto P».
- En pantalla y al imprimir, los bloques I a IX son los del 613 P (ingresos, gastos, disponible, etc.).

## Problemas frecuentes

- **«Persona no encontrada en este centro»**: esa ficha no está activa en el centro abierto.
- **acumulado(anterior) sigue siendo del ejercicio abierto** (acumulado real y, entre paréntesis, la previsión guardada de ese ejercicio), aunque el desplegable esté en el año siguiente.
- **«La cantidad debe ser numérica»**: alguna casilla lleva texto o un símbolo que no se entiende. Dejarla vacía o escribir solo el número.
- **acumulado(anterior) sin paréntesis**: aún no hay previsión guardada para esa persona en el ejercicio abierto en ese concepto.
- **El calculado de 24 (u otro puntual) no parece el del año pasado**: hace falta un ejercicio anterior enlazado, con apuntes de esa persona en ese concepto.
- **La columna de esa persona en Previsión sigue a cero**: no se ha guardado esta hoja para el ejercicio correspondiente, o se eligió otra persona u otro año.
