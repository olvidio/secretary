# Copias de seguridad

- Ruta: `/copias`
- Ámbito: centro-n, centro-sg, asociacion, fundacion
- Quién: secretario del centro

## Para qué sirve

Permite guardar una copia de **este** centro o associació y volver a ella si algo se estropea. No mezcla las copias de otros centros aunque los lleve la misma persona. La copia de toda la base de datos está en la administración de la plataforma (`/admin/copias`).

## Cómo se usa

1. Pulsar **Crear copia ahora**. Se genera un fichero en el servidor y aparece en el listado con su fecha y su tamaño.
2. Si ya hay 5 copias en el servidor, avisa «Solo se permite tener 5 copias en el servidor» y ofrece **Borrar la más antigua y guardar**. Esa opción quita la copia más vieja y deja la nueva.
3. En la tabla **Copias en el servidor**, cada fila tiene tres acciones: **Descargar** para guardarla aparte, **Restaurar** y **Borrar**.
4. Para volver a una copia del servidor, pulsar **Restaurar** en su fila y confirmar el aviso.
5. Si la copia está en el ordenador, elegirla en «O fichero local» y pulsar **Restaurar desde fichero local**.

## Reglas que conviene saber

- La copia es solo de este centro: asientos y listados. Otro centro, aunque lo lleve el mismo usuario, tiene su propia lista y no se restaura con ésta.
- Restaurar sustituye los asientos de este centro posteriores a la copia. Los demás centros no cambian.
- Una copia de otro centro se rechaza. El fichero queda ligado a la **sigla** con la que se creó. Si el centro se ha vuelto a dar de alta con la misma sigla, la copia entra aunque el número interno haya cambiado.
- Conviene descargar de vez en cuando alguna copia y guardarla fuera del servidor: si se pierde la máquina, se pierden también las copias que solo estén allí.
- La copia no incluye los ficheros de Excel: hay que archivarlos aparte.
- Tampoco incluye la configuración del servidor, que es la que descifra los códigos del segundo factor. Al restaurar en otra máquina habrá que volver a configurarlo.
- En el servidor caben como máximo 5 copias. Para hacer otra hay que borrar alguna o usar **Borrar la más antigua y guardar**.
- En esta pantalla solo se admiten ficheros `.json` de este centro. El volcado completo `.sql` de toda la base está en `/admin/copias`.

## Problemas frecuentes

- **«Solo se permite tener 5 copias en el servidor»**: hay que borrar alguna de la tabla o pulsar **Borrar la más antigua y guardar**.
- **«El directorio de copias no es escribible» o «No se pudo crear el directorio de copias»**: el programa no tiene permiso para escribir en el servidor. Lo resuelve quien administra la instalación.
- **«El fichero debe ser una copia .sql o .dump»**: el fichero elegido no es una copia, o se ha comprimido. Hay que subirlo tal como se descargó.
- **«La copia supera el tamaño máximo (256 MB)»**: hay que dejarla en el servidor y restaurarla desde el listado.
- **«No existe el fichero de copia indicado»**: se ha borrado o cambiado de nombre. Recargar la pantalla.
- **«Esta copia es de otro centro y no se puede restaurar aquí»**: la sigla del fichero no es la de este centro. Hay que restaurarla desde el centro con esa sigla.
- **Avisa de comprobar el estado del programa**: la copia puede ser anterior a una actualización; que lo revise quien administra la instalación antes de seguir.

## Centro n

- Menú: Inicio → Copias

## Centro sg

- Menú: Centro → Copias

## Asociación y fundación

- Menú: Parámetros → Copias

La copia lleva el plan de cuentas tal como está (códigos, nombres, naturaleza y código oficial), los apuntes, los listados, las cajas y bancos, los arqueos y recuentos, el historial del extracto del banco, las plantillas de movimientos, las entradas periódicas y los ejercicios. Al restaurar, el plan queda como en la copia: las cuentas de ingreso y gasto que el centro tenga y la copia no traiga se quitan.

Para llevar la asociación a otro servidor: darla de alta allí con la **misma sigla**, entrar con su usuario y restaurar el fichero `.json` descargado. No viajan los usuarios ni sus contraseñas, ni los modelos de plan de cuentas guardados (no hacen falta: el plan va en la copia).
