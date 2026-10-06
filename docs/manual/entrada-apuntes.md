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

En **Entrada G** hay un bloque **Importar extracto del banco** (mismo criterio que el banco personal en `/yo/banco`):

1. Elegir el banco (N26, CaixaBank, BBVA o Banco Sabadell) y, si hay varias cuentas de tesorería activas, cuál es.
2. Elegir el fichero y pulsar **Importar**. Dos apuntes iguales del extracto entran los dos. Al volver a subir el mismo extracto no se duplican filas ya importadas. Hasta que no se categorice, no se crean asientos ni apuntes.
3. Los movimientos nuevos quedan en **Por categorizar**: elegir concepto e iniciales si procede, observaciones y pulsar **Asignar**. **Cambiar a P** sustituye el desplegable por conceptos P (vuelve con **Cambiar a G**); al **Asignar** en modo P las iniciales son obligatorias. En el desplegable de concepto G, al final aparecen **Otra contabilidad** (registra el movimiento en el banco del centro sin apunte ni ingreso/gasto del plan) y **Traspaso a caja** (usa los conceptos 41/42); ambas se confirman con **Asignar**.

### Teclear apuntes

1. Elegir las iniciales de la persona.
2. Elegir A, B o C: A si no toca la caja ni el banco del centro, B si es del banco, C si es de la caja. Con varias, se indica cuál.
3. Comprobar la fecha: sale la de hoy si estamos en el mes de la fecha de cierre y, si no, esta última.
4. Escribir las observaciones: a partir de dos letras se ofrecen las de esa persona, las más usadas primero, y al elegir una se copia el concepto.
5. Elegir el concepto y teclear la cantidad; el tabulador salta de una a otra.
6. Pulsar **Añadir**, o Intro en la cantidad. La línea guardada se queda a la vista.

## Reglas que conviene saber

- La cantidad se escribe siempre en positivo: el signo lo pone el concepto.
- En el libro personal las iniciales son obligatorias.
- La **fecha de imputación** solo se rellena si el movimiento hay que contarlo otro día: operación el 8 de enero, gasto el 31 de diciembre. Vacía, se usa la de cabecera.
- Los conceptos 41 (banco a caja) y 42 (caja a banco) generan el movimiento completo de una vez, sin fecha de imputación distinta.
- En Entrada G, un gasto con iniciales anota cuatro líneas: ingreso 111 y gasto 21 en el personal, ingreso 11 en el general, y el gasto. Si no aporta vivienda a generales, solo el gasto.
- Las plantillas salen en el desplegable de concepto; al elegirlas, solo falta la cantidad.
- En el libro personal con procedencia A, el pie avisa si los apuntes de esa persona no cuadran y muestra **Cuadrar (concepto · importe)** —por ejemplo «Cuadrar (111 · 50,00)»—, que añade el ingreso 111 que falta con un clic (pide confirmación).

## Problemas frecuentes

- **«Las iniciales son obligatorias en P»**: elegir la persona en la cabecera.
- **«Iniciales no reconocidas en este centro»**: ese nombre no está dado de alta aquí.
- **«La cantidad debe ser positiva»**: no se teclea el signo menos.
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

Sirve para anotar a mano un movimiento del libro único. Lo que ya viene en el fichero Grisbi entra por Configuración → Importar Grisbi, no por esta pantalla.
