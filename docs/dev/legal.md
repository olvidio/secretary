# Condiciones, privacidad y prueba de aceptación

Proceso para agentes. Los textos que ve el usuario están en `docs/legal/` (no en
`docs/manual/`: el corpus de la ayuda no debe tragarse el contrato entero).

> Encomendamos el trabajo a S. José.

## Qué hay que conservar

Una denuncia o un requerimiento de la AEPD se aguanta si se puede reconstruir:

1. Qué texto se mostró (versión + hash SHA-256 del fichero, y copia en
   `documentos_legales`).
2. Quién aceptó (identidad, correo, alias).
3. Cuándo (UTC), desde qué IP y con qué navegador.
4. Por qué canal: casilla del registro, clic del correo, alta de nombres,
   importación Excel o aprobación de vínculo.
5. El texto exacto de la casilla que marcó.

La tabla `aceptaciones_legales` es de solo inserción. No se actualiza ni se borra
al borrar la cuenta: se deja `identidad_id` a nulo y se conserva el hecho.

## Cómo cambiar el texto

1. **No edites** `docs/legal/condiciones-v1.*.md` ni `privacidad-v1.*.md` una vez
   que alguien haya aceptado esa versión en un entorno real.
2. Crea `condiciones-v2.es.md` (y `.ca.md`) y `privacidad-v2.es.md`.
3. Cambia `CatalogoDocumentosLegales::VERSION_VIGENTE` a `v2`.
4. `db:migrate` / `LegalSeeder` inserta la fila nueva; las antiguas no se tocan.
5. Quien se registre a partir de entonces acepta v2. Quien ya tenía cuenta sigue
   ligado a la versión que aceptó, hasta que se pida una nueva aceptación.

## Operador (responsable de las cuentas)

En `.env`:

```
LEGAL_RESPONSABLE_NOMBRE=
LEGAL_RESPONSABLE_EMAIL=
LEGAL_RESPONSABLE_DIRECCION=
```

Si faltan, se usa `MAIL_FROM` y un nombre genérico. Esos datos se sustituyen en
la política al mostrarla y se copian en `extra` de cada aceptación.

## Nombres del centro

El alta, la importación y el vínculo de un nombre nuevo exigen la casilla
«el centro es responsable». El programa es encargado (alojamiento gratuito); no
asume la responsabilidad del fichero de personas del centro.
