# Mensaje XML de remesa (1.0)

Documento de negocio del envío mensual del libro personal al centro. Hoy emisor
y receptor son la misma aplicación y el XML se guarda en `remesas.mensaje_xml`.
El transporte (AS4) no forma parte de este documento.

Esquema: `docs/dev/mensajes/remesa-1.0.xsd`. Namespace
`urn:secretario:mensajes:1.0`.

## Documento y transporte

En Tramity (`apps/oasis_as4`) el escrito es un XML propio y el sobre AS4 es otro
fichero (`MessageMetaData` de Holodeck: `MessageId`, `ConversationId`, P-Mode).
Holodeck hace el SOAP, la firma y el acuse. Aquí se repite esa separación:

- Este XML es el payload. No lleva SOAP, WS-Security ni P-Mode.
- Cuando haya dos aplicaciones, el mismo XML será el cuerpo del mensaje. El
  gateway (Holodeck, como en Tramity, o Domibus) hablará el perfil **eDelivery
  AS4** (one-way push). No se implementa el OASIS crudo ni se entra en Peppol:
  las partes se conocen y el documento no es una factura.

`idMensaje` y `emitido` identifican el ejemplar. No entran en el hash.

## Qué viaja

Grano de D6: una línea por `codigo_maestro`, en céntimos enteros, moneda `EUR`.
En el cable no hay ids de base. El centro es `centros.codigo`, la persona son
sus iniciales dentro de ese centro, el periodo es año y mes. Cada lado resuelve
su `ejercicio_id`.

`conversacion` es `CODIGO/INICIALES/AAAA-MM`. Código e iniciales no llevan
espacios ni `/`.

`accion` es `envio` en la versión 1 y `sustitucion` en las siguientes del mismo
mes. La versión local (`sustituida`, `rechazada`, `aceptada`) no viaja: aceptar
y rechazar siguen siendo el flujo del centro.

`<disponible cents="…"/>` es el disponible ya calculado
(`max(0, saldo de caja y banco − remanente)`), el mismo entero que
`remesas.saldo_tesoreria_cents`. No es el saldo bruto.

## Qué no viaja

El desglose (subcuentas, generales, plantillas, apuntes, notas de apunte) no
está en este mensaje. Sigue en `remesa_lineas.detalle_json` y el centro solo lo
ve si la persona autoriza. Cuando las aplicaciones se separen, ese desglose
será otro documento.

## Hash

No es el hash de los bytes del XML: cambiarían con los espacios. Es el SHA-256
canónico de `HashRemesa`: líneas `(codigo, importe)` en el mismo orden que ese
hash, más el disponible. El elemento `<hash algoritmo="sha-256">` permite al
receptor comprobar el contenido. Al leer, si no coincide, el mensaje se rechaza.
Las líneas del XML salen en ese mismo orden.

## Ejemplo

```xml
<?xml version="1.0" encoding="UTF-8"?>
<remesa xmlns="urn:secretario:mensajes:1.0" versionEsquema="1">
  <cabecera>
    <idMensaje>6f1c2a40-7b2e-4c1a-9d0e-1a2b3c4d5e6f</idMensaje>
    <conversacion>CENTRO/aa/2026-10</conversacion>
    <accion>envio</accion>
    <emitido>2026-10-07T10:00:00+02:00</emitido>
    <emisor tipo="persona">aa</emisor>
    <receptor tipo="centro">CENTRO</receptor>
  </cabecera>
  <periodo anio="2026" mes="10"/>
  <version>1</version>
  <nota>cierre de octubre</nota>
  <disponible moneda="EUR" cents="12345"/>
  <lineas>
    <linea codigo="22" cents="-1250"/>
    <linea codigo="111" cents="5000"/>
  </lineas>
  <hash algoritmo="sha-256">0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef</hash>
</remesa>
```

## Dónde se guarda

`migraciones/0077_remesa_mensaje_xml.sql` añade `mensaje_xml` (nullable: las
remesas anteriores no tienen documento). Las columnas de siempre siguen siendo
el índice (persona, mes, versión, estado, hash). Lo escribe `EnviarRemesa` al
guardar. La bandeja y aceptar siguen leyendo las filas.
