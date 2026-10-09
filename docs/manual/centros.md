# Centros

- Ámbito: centro-n
- Ruta: `/centros`
- Menú: Parámetros → Centro (layout burger) o Inicio → Centro (layout excel)
- Quién: secretario del centro. Quien solo consulta ve la pantalla y no puede cambiarla.

## Para qué sirve

Reúne lo que afecta al centro como entidad: quién puede entrar a llevar sus cuentas, la carga del libro de Excel del que se viene y las partidas del capítulo VII. El alta de centros nuevos la hace el administrador de plataforma.

Cada centro tiene sus propias cuentas, sus propios nombres y su propio secretario. Un secretario de otro centro no ve nada de este.

## Cómo se usa

1. Arriba aparece **Centro** y la sigla; la sección **Secretarios** (centro n) lista quien puede llevar el centro.
2. Para dar acceso a otra persona, usar **Invitar secretario**: escribir su **correo**. Si ya tiene cuenta, basta el correo (y el rol); se añade un **mandato** sobre este centro sin crear otro usuario. Si es cuenta nueva, indicar alias y contraseña (mínimo seis caracteres). **Puede modificar** anota y cambia datos. **Solo consulta** entra, ve las pantallas y los informes, y no puede guardar ni borrar. En la tabla se puede cambiar el rol o pulsar **Quitar** para revocar el acceso (no sobre uno mismo; tiene que quedar al menos un usuario que pueda modificar).
3. Para cargar el libro de Excel, elegir el fichero en **Importar datos de un excel de secretario**, marcar que el centro es responsable de los nombres que se importan y pulsar «Importar Excel». Al acabar se indica cuántos nombres y apuntes se han cargado.
4. En «VII. Otras labores apostólicas»: **Añadir partida** crea una fila; **Quitar** en una fila la elimina. En cada partida se escribe código, etiqueta y si **desgrava**. **Guardar partidas** persiste los cambios.
5. Volver a **Importar Excel** actualiza los movimientos del fichero (importación idempotente); no hace falta vaciar nada antes.

## Reglas que conviene saber

- Las partidas del capítulo VII llevan código que empieza por 7 (71, 791…), etiqueta obligatoria y como máximo doce. Salen en el resumen 613 del libro personal y como conceptos de gasto de ese libro. La casilla «Desgrava» decide si entran en el primer tramo de donativos al proponer destinos.
- Importar nombres es un alta de datos personales: el centro (el secretario) es el responsable; el programa solo aloja.
- Un usuario de solo consulta usa el mismo segundo factor que el secretario. Puede cambiar su contraseña, el correo y el idioma. No puede dar de alta usuarios ni importar.
- En el Excel antiguo, una **exención de 1 a 12** (todo el año) marcaba a quien no participa en el cierre. Al importar se deja la exención vacía y «Vivienda aporta a generales» pasa a **no**. Una exención de unos meses (llegada o salida a mitad de año) se conserva.

## Problemas frecuentes

- **«Código duplicado»**: dos partidas del capítulo VII llevan el mismo código.
- **«Debe aceptar que el centro es responsable…»**: falta marcar la casilla antes de importar el Excel.
- **«Esta cuenta es de solo consulta»**: se ha intentado guardar o borrar con un usuario que solo puede ver.
- **«Debe quedar al menos un usuario que pueda modificar»**: el cambio de rol dejaría el centro sin nadie que pueda anotar.
