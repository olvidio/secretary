# Previsión personal

- Ruta: `/prevision-personal`
- Menú: Presupuestos → Previsión personal
- Quién: secretario del centro

## Para qué sirve

Prepara el presupuesto del libro personal, persona a persona. Cada hoja replica los conceptos del 613 P. La columna **Calc.** muestra una **referencia del ejercicio en curso**: lo acumulado hasta la fecha de corte y, entre paréntesis, la previsión ya guardada para ese mismo ejercicio (si existe). La columna **Previsión** es la del **año elegido** (por defecto el del ejercicio siguiente al abierto en el centro).

## Cómo se usa

1. Elegir la **persona**, o **Todos (imprimir)** para ver e imprimir las hojas de todas las personas activas del centro.
2. Elegir el **año** (etiqueta del ejercicio: `2027`, `2026-27`, etc.). Por defecto es el **período siguiente** al abierto, con la misma duración (enero–diciembre, septiembre–agosto u otra). Sale aunque ese ejercicio aún no esté abierto: al guardar se crea en estado planificado y los importes quedan en ese período.
3. Revisar **Calc.**: acumulado actual (enteros) y, si hay cifra guardada para el ejercicio abierto, `(previsión actual)`.
4. Escribir en **Previsión** el importe del año elegido. Dejar la casilla vacía equivale, al guardar, a usar el **calculado** (proyección lineal o reglas de conceptos puntuales hasta fin de ejercicio — ver más abajo).
5. **Usar previsión del ejercicio actual** copia a **Previsión** los importes ya guardados para el ejercicio abierto (útil al preparar el año siguiente).
6. **Usar calculados** copia la proyección calculada a todas las casillas, por si se quiere retocar solo algunas.
7. **Guardar**. Esas cifras quedan ligadas al ejercicio del año seleccionado y son las que luego salen en Previsión, en la columna de esa persona, para ese ejercicio.
8. **Imprimir** saca la hoja en A5 vertical, con los capítulos del 613 P. La cabecera incluye persona, centro y el **año seleccionado**. Si una casilla de previsión está vacía, se imprime el calculado (proyección).
9. Con **Todos (imprimir)** no se edita ni guarda: se cargan todas las hojas con **Previsión** vacía (para rellenar a mano), **Calc.** sí se imprime, y **Imprimir** usa **A4 horizontal** con **dos hojas tamaño A5** en cada página. Una sola persona sigue en **A5 vertical** con previsión impresa como hasta ahora.

## Reglas que conviene saber

- **Calc.** no es la proyección: es solo referencia (acumulado + previsión guardada del ejercicio de trabajo). La proyección se usa al guardar con casilla vacía y con **Usar calculados**.
- La proyección **lineal** (sueldo, vivienda, ordinarios, etc.) es: acumulado × meses del ejercicio ÷ meses transcurridos hasta la fecha de corte.
- Los conceptos **puntuales** (12 extraordinarios, 24 ca/crt/cv, 4 ayudas familiares, 51 y 52) no se prorratean: se mira cuánto hubo en el ejercicio anterior en los meses que aún faltan; si este año todavía no hay nada y el año pasado el evento ya había pasado, se toma el total del ejercicio anterior. Por eso un 24-ca de julio no sale a cero en junio.
- El **9** (saldo de las c/c personales) es el saldo actual, sin extrapolar.
- Si el ejercicio no tiene ejercicio anterior, los puntuales se quedan en lo acumulado de este año.
- Guardar una persona no toca el presupuesto P hasta que en Previsión se pulse «Aplicar al presupuesto P».
- En pantalla y al imprimir, los bloques I a IX son los del 613 P (ingresos, gastos, disponible, etc.).

## Problemas frecuentes

- **«Persona no encontrada en este centro»**: esa ficha no está activa en el centro abierto.
- **Calc. sigue siendo del ejercicio abierto** (acumulado real y, entre paréntesis, la previsión guardada de ese ejercicio), aunque el desplegable esté en el año siguiente.
- **«La cantidad debe ser numérica»**: alguna casilla lleva texto o un símbolo que no se entiende. Dejarla vacía o escribir solo el número.
- **Calc. sin paréntesis**: aún no hay previsión guardada para esa persona en el ejercicio abierto en ese concepto.
- **El calculado de 24 (u otro puntual) no parece el del año pasado**: hace falta un ejercicio anterior enlazado, con apuntes de esa persona en ese concepto.
- **La columna de esa persona en Previsión sigue a cero**: no se ha guardado esta hoja para el ejercicio correspondiente, o se eligió otra persona u otro año.
