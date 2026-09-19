# Copias de seguridad

- Ruta: `/copias`
- Menú: Inicio → Copias
- Quién: secretario del centro

## Para qué sirve

Permite guardar una copia completa de todos los datos y volver a ella si algo se estropea. Conviene hacer una antes de una carga grande, antes de vaciar datos o antes de cualquier prueba que pueda salir mal.

## Cómo se usa

1. Pulsar **Crear copia ahora**. Se genera un fichero en el servidor y aparece en el listado con su fecha y su tamaño.
2. Si ya hay 5 copias en el servidor, avisa «Solo se permite tener 5 copias en el servidor» y ofrece **Borrar la más antigua y guardar**. Esa opción quita la copia más vieja y deja la nueva.
3. En la tabla **Copias en el servidor**, cada fila tiene tres acciones: **Descargar** para guardarla aparte, **Restaurar** y **Borrar**.
4. Para volver a una copia del servidor, pulsar **Restaurar** en su fila y confirmar el aviso.
5. Si la copia está en el ordenador, elegirla en «O fichero local» y pulsar **Restaurar desde fichero local**.

## Reglas que conviene saber

- La copia es de todos los datos, no solo de un centro. Si en la instalación hay varios centros, la restauración afecta a todos.
- Restaurar **sobrescribe** todo, y lo anotado después de esa copia se pierde. Conviene avisar, cerrar las demás sesiones y hacer una copia nueva justo antes, para poder deshacer.
- Conviene descargar de vez en cuando alguna copia y guardarla fuera del servidor: si se pierde la máquina, se pierden también las copias que solo estén allí.
- La copia no incluye los ficheros de Excel: hay que archivarlos aparte.
- Tampoco incluye la configuración del servidor, que es la que descifra los códigos del segundo factor. Al restaurar en otra máquina habrá que volver a configurarlo.
- En el servidor caben como máximo 5 copias. Para hacer otra hay que borrar alguna o usar **Borrar la más antigua y guardar**.
- Solo se admiten ficheros `.sql` o `.dump`, y como máximo de 256 MB.

## Problemas frecuentes

- **«Solo se permite tener 5 copias en el servidor»**: hay que borrar alguna de la tabla o pulsar **Borrar la más antigua y guardar**.
- **«El directorio de copias no es escribible» o «No se pudo crear el directorio de copias»**: el programa no tiene permiso para escribir en el servidor. Lo resuelve quien administra la instalación.
- **«El fichero debe ser una copia .sql o .dump»**: el fichero elegido no es una copia, o se ha comprimido. Hay que subirlo tal como se descargó.
- **«La copia supera el tamaño máximo (256 MB)»**: hay que dejarla en el servidor y restaurarla desde el listado.
- **«No existe el fichero de copia indicado»**: se ha borrado o cambiado de nombre. Recargar la pantalla.
- **Avisa de comprobar el estado del programa**: la copia puede ser anterior a una actualización; que lo revise quien administra la instalación antes de seguir.
