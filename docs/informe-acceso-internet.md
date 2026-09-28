# Informe: acceso desde internet con prueba legal y medidas de seguridad

**Asunto.** Cómo está resuelto hoy en Secretario el alta, la aceptación de condiciones y la baja de una cuenta, y qué habría que reforzar para aplicar el mismo criterio a Orbix-Aquinate abierto a internet.

**Fecha.** 28 de septiembre de 2026.

**Alcance.** Describe el comportamiento real del programa Secretario (código, textos legales versionados y pantallas de administración). No es un dictamen jurídico ni una certificación de seguridad. Sirve para que dirección y asesoría vean que el diseño es construible, qué prueba deja y qué falta antes de exponer otra plataforma.

---

## 1. Conclusión

Sí se puede operar una plataforma en internet con un rastro defendible ante una reclamación o un requerimiento. Secretario ya lo hace en cuatro piezas que se pueden trasladar:

1. **Contrato formado en dos actos.** Casilla vacía en el alta, más un enlace de correo que caduca a las 48 horas. Sin el segundo acto la cuenta no entra.
2. **Texto congelado.** Cada aceptación guarda la versión, el hash SHA-256 del documento, el texto exacto de la casilla, la fecha, la IP, el navegador y quién era el operador en ese momento.
3. **La prueba no se borra con la cuenta.** Al eliminar al usuario, el registro legal permanece (el vínculo a la cuenta se anula). La contabilidad del centro no se destruye por borrar a un secretario.
4. **El acceso no es solo una contraseña.** Quien administra datos de terceros tiene segundo factor obligatorio, la sesión se renueva al entrar, las acciones de escritura exigen un testigo anti-falsificación y lo que no está autorizado se deniega.

Eso demuestra el método. No demuestra, por sí solo, que Orbix-Aquinate ya cumpla. Orbix trata, con toda probabilidad, datos de más personas y con más sensibilidad. Antes de abrirlo haría falta cerrar los huecos del apartado 8: contraseñas más exigentes, segundo factor para quien vea datos ajenos, alta por invitación si la plataforma no es pública, registro de seguridad, anonimización efectiva de la prueba cuando proceda el derecho de supresión, y nueva aceptación cuando cambien las condiciones de forma relevante.

---

## 2. Quién es responsable de qué

La política de privacidad (versión v2, vigente desde el 21 de septiembre de 2026) separa dos tratamientos. Esa separación es la que suele pedir un abogado cuando el programa lo usa un centro y el programa lo aloja otro:

| Tratamiento | Responsable | Papel del operador del programa |
| --- | --- | --- |
| La cuenta (correo, alias, contraseña, segundo factor, preferencias) | Quien publica la instancia | Responsable. Base: el contrato de uso (art. 6.1.b RGPD). |
| Nombres y contabilidad de un centro | El centro, y quien actúa en su nombre | Encargado: solo aloja y trata para prestar el servicio. |

Quien da de alta, importa o vincula nombres marca otra casilla, distinta de la del registro. El texto que se guarda dice, en esencia, que el centro es responsable, que tiene base legal y que el programa solo aloja. Canales registrados: alta de persona, importación y vinculación a un centro.

La cuenta personal de quien se registra por sí mismo no usa esa declaración: esa persona es interesada de su propia cuenta, y se le informa en la política de privacidad.

Los textos existen en castellano y en catalán. La casilla y el expediente usan el idioma de la sesión.

El nombre, el correo y la dirección del operador salen de la configuración (`LEGAL_RESPONSABLE_NOMBRE`, `LEGAL_RESPONSABLE_EMAIL`, `LEGAL_RESPONSABLE_DIRECCION`) y se copian dentro de cada aceptación. Así el expediente no depende de que esos datos cambien años después.

---

## 3. Alta de una cuenta

Hay dos orígenes. Los dos exigen la misma casilla y el mismo correo de confirmación.

### 3.1. La persona se registra sola

Ruta pública `/registro`, desde «Registrarse» en la pantalla de entrada.

1. Elige el tipo: cuenta personal, centro (secretario), centro con plan específico, o asociación.
2. Escribe usuario, correo, nombre opcional y la contraseña dos veces.
3. La casilla de condiciones **aparece vacía**. Si no la marca, el alta se rechaza con «Debe aceptar las Condiciones de uso». No hay casilla premarcada.
4. El programa comprueba el formato del usuario y del correo, que la contraseña tenga al menos 6 caracteres y coincida, que el usuario no exista y que ese correo no tenga ya una cuenta personal.
5. Crea la identidad con la contraseña en hash (no en claro) y un enlace de un solo uso, válido 48 horas.
6. Graba la primera aceptación, canal `formulario_registro`.
7. Envía el correo. Hasta que se abre el enlace, el intento de entrar responde: «Confirme su correo antes de entrar».

Si el alta la pidió otra persona, basta con ignorar el correo: la cuenta no se activa. Las condiciones lo dicen de forma expresa.

En un entorno de desarrollo se puede saltar el correo con un ajuste de configuración. En producción ese ajuste debe estar apagado. Un acceso por internet no puede depender de esa excepción.

### 3.2. Un secretario da de alta a otra persona del centro

Eso no es un registro público. Ocurre dentro del centro, con la sesión ya autenticada. Exige la declaración de responsable de nombres (apartado 2), no sustituye las condiciones de quien luego active su propia cuenta.

### 3.3. Confirmación del correo

Al abrir el enlace:

- si ha caducado o ya se usó, no confirma;
- si es un cambio de correo posterior, el buzón nuevo pasa a ser el vigente y el anterior sigue valiendo hasta ese momento;
- si es el alta, marca el correo como verificado y graba una **segunda** aceptación, canal `confirmacion_email`, con el hash del enlace (no el enlace en claro).

Hay, por tanto, dos momentos distintos: la casilla en el formulario y el clic en un mensaje llegado a ese buzón. Cada uno tiene su fecha, su IP y su navegador.

### 3.4. Primer acceso

1. Usuario o correo, y contraseña.
2. Si el correo no está verificado, no entra.
3. Si la cuenta administra un centro, el segundo factor es obligatorio: la primera vez hay que activarlo (aplicación de códigos de seis dígitos); después, cada entrada lo pide. Una cuenta solo personal puede entrar sin él.
4. Si el mismo correo vale para varias cuentas, elige cuál. Si administra varios centros, elige el centro de esa sesión.
5. Al acertar la contraseña y al completar el segundo factor, el identificador de sesión se regenera. Así una sesión robada antes del login no sigue valiendo después.

---

## 4. Qué se acepta, y qué no es un consentimiento genérico

La casilla de registro, en castellano, queda guardada con este texto (la versión es la vigente en ese instante):

> He leído y acepto las Condiciones de uso (v2) y he sido informado de la Política de privacidad (v2). El servicio es gratuito.

Esa redacción distingue dos cosas que el RGPD no trata igual:

- **Aceptar las condiciones** es celebrar el contrato de uso.
- **Haber sido informado** de la privacidad no es un permiso en blanco «para cualquier fin». La política enumera cada finalidad con su base: contrato, interés legítimo (seguridad y prueba de la aceptación), obligación legal. No hay boletín ni cesión comercial. La única cookie es la de sesión, necesaria para entrar.

Los documentos se publican en `/condiciones` y `/privacidad` antes de marcar la casilla.

### Versiones

Los ficheros `condiciones-v1` y `privacidad-v1` no se reescriben después de que alguien los haya aceptado. Un cambio relevante es un fichero nuevo (`v2`, ya vigente) y un cambio de la versión que ofrece el programa. Las filas antiguas de la base de datos no se tocan. Quien se registra a partir del cambio acepta la versión nueva. Quien ya tenía cuenta sigue ligado a la que aceptó.

Las condiciones anuncian que un cambio relevante pedirá una nueva aceptación cuando la ley lo exija. **Ese re-aviso automático aún no está construido:** hoy el programa no bloquea a un usuario antiguo hasta que acepte la versión nueva. Para Orbix hay que construirlo antes de publicar un cambio que afecte a responsabilidad o a datos. Ver apartado 8.

---

## 5. Qué se puede enseñar después

La tabla de aceptaciones solo admite inserciones. No hay pantalla ni caso de uso que la actualice o la borre. Si se elimina la cuenta, la base de datos pone a nulo el identificador de usuario y **conserva el hecho**.

Cada fila guarda:

- canal (formulario, confirmación de correo, alta de nombres, importación o vínculo);
- momento (fecha con zona horaria);
- versión y hash SHA-256 de las condiciones y de la privacidad que estaban vigentes;
- el texto exacto de la casilla;
- idioma, IP, navegador, correo y alias tal como eran entonces;
- identificador del centro o de la persona, si el acto iba de nombres;
- hash del enlace de confirmación, cuando el canal es el correo;
- copia del operador (nombre, correo, dirección) en ese momento.

El administrador de la plataforma, y solo él, puede buscar por correo, alias o número de usuario y descargar un expediente en PDF o en HTML: cronología, hashes y anexos con el texto que correspondía a cada versión. Ese expediente es el documento que se entregaría ante una reclamación o un juzgado, sin reconstruir el contrato de memoria.

La política fija el plazo de conservación de esa prueba en el tiempo de prescripción de las acciones (orientativamente seis años en España) y dice que, si procede el derecho de supresión y la prueba sigue siendo necesaria, se anonimiza el correo. **La conservación al borrar la cuenta sí está hecha. El procedimiento de anonimizar el correo dentro de la prueba no está programado todavía.** Hay que decirlo así: el compromiso está en la política; la herramienta aún no.

---

## 6. Baja y borrado

No es el mismo acto según el tipo de cuenta. La diferencia protege los libros del centro, que no son «datos del secretario» a secas.

### 6.1. Cuenta personal (la pide el propio usuario)

1. Desde su cuenta, confirma que quiere la baja.
2. El programa exige correo ya verificado y envía un enlace de 48 horas. No borra en el acto: quien no controle el buzón no puede consumar la baja.
3. Al abrir el enlace, y solo si no ha caducado ni se ha usado:
   - se borra el libro personal;
   - en los centros ajenos se quita el vínculo y, si el correo del nombre era el de esa cuenta, se vacía ese correo; los apuntes del centro siguen;
   - se eliminan credenciales y sesión de esa identidad;
   - las aceptaciones legales permanecen, desligadas del identificador de cuenta;
   - no se envía un segundo correo de «ya está borrado» en este camino, porque el enlace ya salió de ese buzón. Si el borrado lo ordena el administrador, sí se avisa por correo.

La cuenta del administrador de la plataforma no se puede borrar por este camino ni desde el listado.

### 6.2. Cuenta de secretario de centro (la ordena el administrador)

No hay autobaja. El administrador inicia una baja en espera de **60 días** (el plazo es configurable entre 1 y 365):

- la cuenta deja de poder entrar;
- se desvincula del centro **sin borrar la contabilidad**;
- se avisa por correo al secretario y a las cuentas personales vinculadas a ese centro;
- si era el único secretario, el resumen lo advierte;
- durante el plazo se puede reactivar;
- pasado el plazo, un proceso programado elimina las credenciales. Si esa identidad tenía también libro personal, queda solo como cuenta personal y no se destruye ese libro.

Borrar un centro entero es otra operación del administrador: elimina el centro y sus datos, con confirmación. No es la baja de un usuario.

### 6.3. Qué responde esto a «derecho de supresión»

Se puede dar de baja la cuenta y dejar de tratar el libro personal. No se puede prometer que desaparece toda huella el mismo día: la prueba del contrato y, en un centro, las obligaciones contables tienen un plazo propio. La política lo dice. Para Orbix hay que escribir el mismo reparto con los plazos que correspondan a sus datos (contabilidad, menores, historial académico u otros), y construir la anonimización de la prueba cuando el interesado pida supresión y la defensa jurídica siga exigiendo conservar el hecho sin el correo en claro.

---

## 7. Medidas de seguridad que sostienen el diseño

Son las que ya ejecuta Secretario. Están pensadas como medidas del artículo 32 del RGPD (adecuadas al riesgo de esta contabilidad), no como una lista cerrada para cualquier dato.

| Medida | Cómo está |
| --- | --- |
| Contraseña | Hash, nunca en claro. Si el usuario no existe, se hace igualmente una comprobación de hash para no delatar por tiempo de respuesta si la cuenta existe. El mensaje al usuario desconocido, en cambio, sí invita a registrarse: eso informa de que no hay cuenta. |
| Segundo factor | Código de seis dígitos (TOTP, estándar RFC 6238), obligatorio para secretario de centro. El secreto se guarda cifrado (AES-256-GCM) con una clave de aplicación. Sin esa clave, las pantallas de acceso no arrancan. Ocho códigos de recuperación, mostrados una sola vez, guardados como hash, de un solo uso. No hay botón para desactivarlo. |
| Bloqueo | Cinco fallos de contraseña o de segundo factor bloquean 15 minutos. Un acierto de contraseña reinicia ese contador. |
| Sesión | Cookie solo de sesión (se cierra el navegador y caduca), no legible por JavaScript, `SameSite=Lax`, y marcada segura cuando la conexión es HTTPS. |
| Falsificación de formularios | Toda escritura exige un testigo ligado a la sesión. |
| Autorización | Cada ruta tiene un ámbito (público, a medias del segundo factor, autenticado, centro, persona, administración). Si una ruta no está catalogada, se deniega. Una sesión personal no lee el libro del centro; una sesión de centro no entra en el libro personal. |
| Cambio de correo | El enlace va al buzón nuevo. Hasta confirmarlo sigue el anterior. |
| Administración | El administrador de plataforma no opera la contabilidad de un centro. Su contraseña sale de la configuración del servidor, no de un alta pública. |

---

## 8. Qué mejorar antes de abrir Orbix-Aquinate a internet

Secretario es el prototipo del circuito. Orbix, expuesto a internet y con datos de terceros, necesita el mismo circuito más las medidas siguientes. Están ordenadas por lo que un abogado o un incidente real preguntarían primero.

1. **Alta cerrada, salvo que se decida lo contrario.** Secretario permite registrarse a cualquiera. Si Orbix es de centros, familias o personal conocido, el alta debe nacer de una invitación del responsable (enlace de un solo uso, caducidad corta, misma doble prueba). El formulario público es una puerta de más.

2. **Segundo factor obligatorio para quien vea datos ajenos.** Hoy es obligatorio solo para el secretario de centro y opcional en la cuenta personal. En internet, quien consulte datos de otras personas debe tenerlo siempre. Conviene ofrecer, además del código de seis dígitos, una llave de dispositivo (passkey), que resiste mejor el robo de contraseña.

3. **Contraseña acorde al riesgo.** El mínimo actual es 6 caracteres. Para un servicio en internet hay que subir la longitud (una frase larga vale más que reglas de símbolos), comprobar filtraciones conocidas y no guardar nunca la contraseña en claro. El hash actual es correcto; el mínimo no lo es para este uso.

4. **Enlaces de un solo uso guardados solo como hash.** El expediente ya guarda el hash del enlace de confirmación. El enlace vivo de verificación y el de baja están en la base de datos en claro. Quien copiara la base podría usarlos hasta que caduquen. Deben guardarse solo el hash, igual que los códigos de recuperación.

5. **Nueva aceptación cuando el texto cambie de verdad.** Publicar v2 sin reescribir v1 ya está. Falta impedir el uso hasta que el usuario acepte la versión nueva, cuando el cambio afecte a responsabilidad, a encargados o a finalidades. Un cambio menor de redacción puede documentarse sin bloquear; el criterio tiene que estar escrito y el programa tiene que aplicarlo.

6. **Anonimización real de la prueba.** Al borrar la cuenta, el hecho permanece y el identificador se anula: correcto. Falta el paso prometido en la política: sustituir correo y alias por un seudónimo irreversible cuando el interesado pida supresión y haya que seguir pudiendo demostrar que *alguien* aceptó ese texto en esa fecha, sin conservar el correo en claro más tiempo del necesario.

7. **Registro de seguridad, no solo de aceptaciones.** Hoy quedan el último acceso y el contador de fallos. Para internet hace falta un diario de solo inserción: entradas, fallos, bloqueos, cambios de contraseña, activación del segundo factor, bajas, accesos de administración. Con plazo de conservación corto y finalidad de seguridad, tal como ya anuncia la política.

8. **Límite por dirección de red, no solo por cuenta.** Cinco fallos bloquean esa cuenta. Un ataque distribuido contra muchas cuentas, o contra el formulario de alta, no queda frenado. Hace falta límite por IP en el acceso, en el registro y en el reenvío de correos, y no revelar si el usuario existe cuando la plataforma sea cerrada.

9. **HTTPS forzado y proxy de confianza.** La cookie segura se activa si el servidor ve HTTPS. Detrás de un proxy mal configurado podría salir sin esa marca. En producción hay que forzar HTTPS y fiar la IP del expediente solo del proxy conocido: si no, la IP que se enseña en un juicio puede ser la del balanceador o una cabecera inventada por el cliente.

10. **Secreto de administrador y clave de cifrado.** La documentación interna avisa de que, si no se cambia la configuración, el administrador podría quedar con una contraseña débil. En internet eso es inaceptable: clave de cifrado distinta por entorno, contraseña de administrador larga, y mejor segundo factor también para esa cuenta. El programa ya se niega a arrancar el acceso sin clave de cifrado; hay que tratar igual la contraseña de administración.

11. **Recuperación de contraseña, si se ofrece.** Secretario no la envía por correo: quien la olvida acude al administrador. Es más seguro y peor de operar. Si Orbix la ofrece, el enlace debe ser de un solo uso, caducar pronto, guardarse como hash, invalidar las sesiones abiertas y avisar al correo anterior. No puede ser un formulario que confirme si la cuenta existe.

12. **Encargo de tratamiento por escrito.** El programa ya distingue responsable y encargado en las condiciones y en la casilla de nombres. El contrato entre el operador de Orbix y cada centro (o la orden religiosa, el colegio, etc.) tiene que existir fuera del software: objeto, duración, tipo de datos, medidas, subencargados (alojamiento, correo) y qué pasa al terminar. El expediente de aceptación no sustituye ese contrato.

13. **Menores y otros datos sensibles.** Las condiciones de Secretario prohíben tratar datos de menores de 14 años sin quien los represente. Si Orbix tiene alumnos o menores, el alta del adulto que los representa, la información a ese adulto y la prohibición de una cuenta autónoma del menor tienen que estar en el flujo, no solo en un párrafo.

14. **Copias y cifrado del disco.** La prueba legal y los datos viven en la base de datos. Hace falta copia cifrada, acceso restringido a quien administra el servidor y un sitio claro de quién restaura. Secretario ya tiene copias de centro; el informe de Orbix debe decir dónde están y quién las abre.

---

## 9. Cómo se trasladaría el flujo a Orbix

El circuito que se puede enseñar, ya probado en Secretario, sería este:

```text
Invitación o registro
        │
        ▼
Casilla vacía + texto de la versión vigente
        │
        ▼
Cuenta creada, aún sin acceso
        │
        ▼
Correo con enlace (48 h, un solo uso, guardado como hash)
        │
        ├─ no se abre → la cuenta no existe a efectos de uso
        │
        ▼
Segunda anotación legal (canal «confirmación de correo»)
        │
        ▼
Contraseña + segundo factor obligatorio
        │
        ▼
Sesión con ámbito (solo lo autorizado)
        │
        ▼
Baja confirmada por correo
        │
        ├─ se borran credenciales y datos cuyo responsable era el propio usuario
        ├─ se conservan los datos cuyo plazo legal es del centro
        └─ se conserva la prueba, anonimizada cuando proceda la supresión
```

Cada flecha deja rastro o se niega. Eso es lo que se puede defender: no una promesa de que «el sistema es seguro», sino un procedimiento en el que el alta, el texto aceptado y la baja se pueden reconstruir.

---

## 10. Qué no afirma este informe

- No afirma que Secretario, ni Orbix, estén certificados (ENS, ISO 27001 o equivalente).
- No afirma que el mínimo de 6 caracteres, el segundo factor opcional en cuentas personales ni la falta de anonimización automática sean suficientes para abrir Orbix mañana.
- No sustituye el contrato de encargo, la inscripción o los análisis de riesgos que correspondan al responsable.
- Los plazos de seis años y la referencia a la AEPD son los de la política española de esta instancia. Si el operador de Orbix está en otro país, la ley aplicable y la autoridad de control cambian; las condiciones ya remiten a la ley del país del operador y a las normas imperativas del usuario.

Lo que sí está construido, y se puede recorrer en una demostración, es el alta con casilla vacía, el correo que activa la cuenta, el expediente con versión y hash, la baja que no se traga la prueba, y el segundo factor obligatorio para quien lleva un centro.
