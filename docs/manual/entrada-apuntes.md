# Entrada de apuntes

- Ruta: `/entrada-p` y `/entrada-g`
- Ámbito: centro-n, centro-sg, asociacion, fundacion
- Quién: secretario del centro

## Centro n

- Menú: Personales → Entrada P; Generales → Entrada G

## Para qué sirve

Es la pantalla de teclear. Se rellena una cabecera común —persona, procedencia del dinero y fecha— y debajo se añaden líneas con observaciones, concepto y cantidad. P es el libro personal; G, el general.

## Cómo se usa

### Entrada G — extracto del banco

En **Entrada G** hay un bloque **Importar extracto del banco** (mismo criterio que el banco personal en `/yo/banco`). Es la forma de cargar o subir los datos (movimientos) del banco sin teclearlos:

1. Elegir el fichero (CSV o Excel) y pulsar **Importar**. El formato se reconoce solo (N26, CaixaBank, BBVA, Banco Santander, Banco Sabadell o Arquia; de otro banco, el formato propio que se explica más abajo). Si hay varias cuentas de tesorería activas, indicar cuál es. Dos apuntes iguales del extracto entran los dos. Al volver a subir el mismo extracto no se duplican filas ya importadas. Hasta que no se categorice, no se crean asientos ni apuntes.
2. Los movimientos nuevos quedan en **Por categorizar**: elegir concepto e iniciales si procede, observaciones y pulsar **Asignar**. **Cambiar a P** sustituye el desplegable por conceptos P (vuelve con **Cambiar a G**); al **Asignar** en modo P las iniciales son obligatorias. En el desplegable de concepto G, al final aparecen **Otra contabilidad** (registra el movimiento en el banco del centro sin apunte ni ingreso/gasto del plan) y **Traspaso a caja** (usa los conceptos 41/42); ambas se confirman con **Asignar**.

### Teclear apuntes

1. Elegir las iniciales de la persona.
2. Elegir A, B o C: A si no toca la caja ni el banco del centro, B si es del banco, C si es de la caja. Con varias, se indica cuál.
3. Comprobar la fecha: sale la de hoy si estamos en el mes de la fecha de cierre y, si no, esta última.
4. Escribir las observaciones: a partir de dos letras se ofrecen las de esa persona, las más usadas primero, y al elegir una se copia el concepto.
5. Elegir el concepto y teclear la cantidad; el tabulador salta de una a otra.
6. Pulsar **Añadir**, o Intro en la cantidad. La línea guardada se queda a la vista.

## Reglas que conviene saber

- La cantidad se escribe normalmente en positivo: el signo lo pone el concepto. Se admite una cantidad **negativa** (p. ej. una devolución o una rectificación): se anota en sentido contrario al habitual del concepto y, al añadirla, la pantalla pide confirmación. La cantidad no puede ser cero.
- En el libro personal las iniciales son obligatorias.
- **Extracto de uno de los bancos admitidos: no hay que preparar nada.** Se sube tal como lo descarga el banco, sin abrirlo ni retocarlo. El programa lo reconoce por las cabeceras que trae de origen; si se cambian las columnas o se guarda con otro programa, puede dejar de reconocerlo.
- **Otro banco, o movimientos de otra procedencia: formato propio.** Se prepara un fichero CSV o Excel con una primera fila de cabecera con tres columnas, **Fecha**, **Concepto** e **Importe** (sin ninguna más), y debajo un movimiento por fila. La fecha, como 15/01/2026 (o 2026-01-15). El importe, en positivo si entra dinero y en negativo si sale, con coma o punto decimal (-23,50). En CSV las columnas se separan con punto y coma. Ejemplo:

  ```
  Fecha;Concepto;Importe
  15/01/2026;Compra de papelería;-23,50
  16/01/2026;Donativo recibido;100,00
  ```

  En la pantalla, dentro de las instrucciones de importar, **Descargar plantilla** baja un fichero de ejemplo para rellenar. Se importa igual que un extracto de banco: las líneas quedan en «Por categorizar» y volver a subir el mismo fichero no duplica nada.
- **Qué es un CSV.** Es un fichero de texto con los movimientos, una línea por movimiento y los datos separados por comas o punto y coma (termina en `.csv`). Es uno de los formatos que ofrece el banco al descargar los movimientos; también puede ofrecer Excel (`.xls` o `.xlsx`). Sirve cualquiera de los que el programa admite para ese banco.
- Al cambiar la cabecera (fecha, persona o procedencia) se vacía la lista de lo ya añadido con la cabecera anterior, pero lo que se esté escribiendo en la línea (observaciones, concepto, cantidad) se conserva hasta pulsar **Añadir**.
- La **fecha de imputación** solo se rellena si el movimiento hay que contarlo otro día: operación el 8 de enero, gasto el 31 de diciembre. Vacía, se usa la de cabecera.
- Si un gasto general lo paga una persona y se anotan a la vez sus contrapartidas (111, 211, G/11), la fecha de imputación se aplica a todas las líneas.
- Si se anota suelto un P/211 o un G/11 (o un P/6 o un G/14) con fecha de imputación distinta, el programa recuerda que la otra mitad tiene que llevar la misma fecha de imputación. Si no, P/211 y G/11 caen en meses distintos y no cuadran en Comprobaciones ni en el 613.
- Los conceptos 41 (banco a caja) y 42 (caja a banco) generan el movimiento completo de una vez, sin fecha de imputación distinta.
- En Entrada G, un gasto con iniciales anota cuatro líneas: ingreso 111 y gasto 21 en el personal, ingreso 11 en el general, y el gasto. Si no aporta vivienda a generales, solo el gasto.
- Las plantillas salen en el desplegable de concepto; al elegirlas, solo falta la cantidad.
- En el libro personal con procedencia A, el pie avisa si los apuntes de esa persona no cuadran y muestra **Cuadrar (concepto · importe)** —por ejemplo «Cuadrar (111 · 50,00)»—, que añade el ingreso 111 que falta con un clic (pide confirmación).

## Problemas frecuentes

- **«Las iniciales son obligatorias en P»**: elegir la persona en la cabecera.
- **«No se reconoce el formato del extracto. Use un CSV o Excel de N26, CaixaBank, BBVA, Banco Santander, Banco Sabadell o Arquia, o un fichero propio con las columnas Fecha, Concepto e Importe.»**: el fichero no es el extracto de uno de esos bancos, o se ha modificado después de descargarlo: volver a descargarlo y subirlo sin tocar. Si es de otro banco, pasarlo al formato propio (Fecha, Concepto e Importe); si ya lo es, comprobar que la cabecera está en la primera fila y que no hay más columnas que esas tres.
- **«Fila …: Fecha no reconocida»** o **«Fila …: Falta la cantidad»** (formato propio): esa fila del fichero tiene la fecha mal escrita o el importe vacío; el número es la fila contando la cabecera. No se importa nada hasta corregirla.
- **«Iniciales no reconocidas en este centro»**: ese nombre no está dado de alta aquí.
- **«La cantidad no puede ser cero»**: falta el importe o es 0.
- **Aviso de cantidad negativa**: se ha tecleado el signo menos. Aceptar solo si de verdad se quiere el sentido contrario; si no, Cancelar y corregir.
- **«No hay ejercicio que cubra la fecha …»**: cae fuera de los ejercicios dados de alta.
- **«… en un ejercicio cerrado»**: reabrirlo, o cambiar la fecha.

## Centro sg

- Ruta: `/entrada-g`
- Menú: Talonario → Entrada

Un solo libro. Se anota el apunte del talonario: nombre, concepto, observaciones, cantidad y fecha. La contrapartida es la caja; la pantalla no pide procedencia A, B o C. No hay libro P, ni el gasto de vivienda que genera P/111 y G/11. No hay traspasos caja↔banco ni menú Traspasos: el **41** es Necesidades generales y el **42–54** son destinos de gasto (como el 43). Las plantillas de Talonario → Plantillas salen en el desplegable de concepto.

Para movimientos que se repiten cada mes, trimestre o año: menú Talonario → **Entradas periódicas** (definición) y **Ejecutar periódicas** (contabilizar los que tocan). Ver `docs/manual/entradas-periodicas.md`.

## Asociación y fundación

- Ruta: `/entrada-g`
- Menú: Movimientos → Entrada

Sirve para anotar a mano un movimiento del libro único. La cabecera no pide iniciales (no hay personas ni libro P). La procedencia A/B/C viene en **Caja** la primera vez y después se queda en la última que se eligió, igual que la caja o el banco concretos si hay varios; se recuerda en ese navegador. En **Por categorizar** tampoco se piden iniciales. Al escribir en observaciones se proponen las ya usadas en cualquier apunte de la asociación, de cualquier año (las más frecuentes primero); al elegir una se copian las observaciones y su concepto. Lo que ya viene en el fichero Grisbi entra por Configuración → Importar Grisbi, no por esta pantalla.
