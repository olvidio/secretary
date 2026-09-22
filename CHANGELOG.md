# Changelog

Cambios visibles para quien usa Secretario. El formato sigue
[Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

La **versión** que aparece en el login se toma, en este orden, de `var/version.json`
(generado en el deploy), del fichero `VERSION` del repositorio o del tag git
del checkout. El changelog se **actualiza a mano** al preparar un tag; no hace
falta en cada push.

## [Unreleased]

## [0.1.6] — 2026-09-22

### Añadido
- Si el mismo correo tiene varias cuentas y la contraseña vale para más de una,
  al entrar se elige con cuál (libro personal o centro).
- Cambiar el correo pide confirmación en el buzón nuevo. Hasta abrir el enlace
  se sigue entrando con el anterior.

### Cambiado
- El mismo correo puede usarse en la cuenta personal y en cuentas de centro.
  No puede repetirse entre dos cuentas personales.
- «Persona activa» y «Tipo» solo aparecen en el menú cuando hay algo que elegir.
- Al pedir acceso a un centro, el nombre del listado no se confunde con el del
  secretario.
- Ayuda breve en Centro y en la fecha de cierre del libro personal.

## [0.1.5] — 2026-09-21

### Añadido
- Al registrarse o entrar como persona se crea el libro personal propio, si aún
  no existe, para poder anotar sin estar vinculado a un centro.

## [0.1.4] — 2026-09-21

### Añadido
- Importar el extracto del banco en Entrada G y categorizar los movimientos
  (concepto del general o del personal, otra contabilidad, traspaso a caja).
- Licencia GPL v3 y pantalla `/licencia`.
- Versión en el pie, con enlace a este changelog.
- En administración, expediente legal: buscar por correo o usuario y descargar
  PDF o HTML de las aceptaciones.
- Guía de procesos habituales en la ayuda (cerrar el mes, remesa, banco, alta).

### Cambiado
- En el cuadre de apuntes, atajos para anotar el descuadre (vivienda o ingreso)
  y botón para volver al listado.
- Las plantillas de apuntes se pueden editar.
- Al cuadrar en la entrada, el botón indica el concepto y el importe.
- En el banco personal: «Otra contabilidad», traspaso a caja y borrar el texto
  recordado de un comercio.
- Las solicitudes de acceso personal se resuelven con alta y vínculo, vínculo a
  un nombre existente, o rechazo.

## [0.1.3] — 2026-09-21

Versión desplegada antes de este changelog automatizado.
