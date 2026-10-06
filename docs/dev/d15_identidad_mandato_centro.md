# D15 — Identidad humana y mandato sobre el centro

Decisión de diseño (2026-10-06). Fija el modelo de acceso **objetivo** respecto a D7
(autenticación de dos niveles). No obliga a refactor inmediato; sí orienta altas de
usuario, migraciones futuras y UI de login.

Documento maestro: § **D15** en `docs/dev/plan_ampliaciones.md`.

---

## Problema

Hoy conviven ideas mezcladas:

- **Centro** (entidad contable que debe sobrevivir al cambio de secretario).
- **Identidad** (login: alias, correo, contraseña, 2FA).
- **Persona del centro** (ficha en Nombres; puede existir sin login).

En la práctica se han creado identidades “del centro” (`sgmontagut`, …) con el mismo
correo que la persona real, lo que obliga a elegir cuenta, alias distintos o contraseñas
idénticas. Eso **no** es una cuenta del centro: es otra identidad nombrada como el
centro.

---

## Regla de oro

**Siempre entra una persona física** (identidad). El centro **nunca** inicia sesión.

---

## Tres capas (no confundir)

| Capa | Qué es | Persiste |
| --- | --- | --- |
| **Centro** | Ámbito contable (sigla, ejercicios, apuntes, nombres…) | Aunque cambie el secretario |
| **Persona** (`personas`) | Nombre estable en Nombres; libro X y remesas en ese centro | Aunque cambie de ejercicio o deje de estar adscrita (D14) |
| **Identidad** | Cuenta de login de **un** humano | Mientras exista la persona; puede tener varios mandatos |

Relación con D14: la **adscripción** (`persona_ejercicio`) es “¿participa en este
ejercicio?”; **`identidad_persona`** es “¿esta identidad opera el libro X / remesa de
esta ficha?”.

---

## Mandato (semántica de `identidad_centro`)

El vínculo identidad ↔ centro se interpreta como **mandato de acceso**, no como “usuario
del edificio”:

```text
identidad_centro(identidad_id, centro_id, rol)
  rol: admin | consulta | …   -- extensible
  (futuro opcional: vigente_desde, vigente_hasta, revocado_at)
```

- **Varios secretarios** = varias identidades con mandato `admin` (o mezcla admin/consulta).
- **Cambio de secretario** = revocar o caducar mandato del saliente; otorgar mandato al
  entrante. **No** borrar centro ni contabilidad.
- **Auditoría**: acciones sensibles registran `identidad_id` (creado_por, importaciones,
  bajas programadas, etc.), no “el centro”.

---

## Cuenta personal

- **Una identidad ↔ un humano ↔ un libro personal** (centro tipo `p` interno).
- Como mucho **una cuenta personal por correo** (regla ya aplicada en registro).
- Vínculo opcional a **persona(s)** de centro(s) reales vía `identidad_persona`
  (solicitud + aprobación en centro n; ver `docs/dev/acceso.md`).

---

## Sesión tras login (objetivo)

1. Autenticar **una identidad** (contraseña; 2FA obligatorio si tiene mandato de escritura
   en algún centro — D7).
2. Elegir **contexto** si hace falta:
   - Solo libro personal → `/yo`.
   - Un mandato de centro → ese centro.
   - Varios mandatos → `/elegir-centro`.
   - Libro personal **y** al menos un centro → elegir modo (equivalente a **Tipo** hoy),
     **sin** duplicar identidades.

**No** usar `/elegir-cuenta` para distinguir “el mismo humano en distintos sombreros”:
eso indica identidades duplicadas que deberían fusionarse en una sola con varios mandatos.

---

## Cambio de secretario (operativa)

1. Alta de identidad del nuevo responsable (invitación o registro, correo confirmado, 2FA).
2. Quien tenga mandato `admin` otorga mandato al nuevo.
3. Revoca o degrada el mandato del saliente (standby/baja de cuenta es otra vía; ver admin).
4. Centro, ejercicios, nombres y asientos: **sin mover**.

Evitar: cuenta compartida tipo `scl@centro` con contraseña común; alias = sigla del centro
como sustituto de persona.

---

## Estado actual vs objetivo

| Aspecto | Hoy (D7 implementado) | Objetivo D15 |
| --- | --- | --- |
| Alta secretario extra | `InvitarUsuarioCentro` (2026-10): correo primero; mandato si existe; alias+contraseña solo si cuenta nueva | Mandato sobre identidad **existente** (invitar por correo) |
| Mismo correo, varias identidades | Permitido entre secretarios; `/elegir-cuenta` si misma contraseña | **Una identidad por humano**; varios mandatos |
| Centro | Ya es entidad persistente | Igual |
| Persona vs login | Separados; enlace `identidad_persona` | Igual, reforzar en docs y UI |

Hecho (fase 9c parcial): `InvitarUsuarioCentro`, formularios **Invitar secretario**;
`FusionarIdentidadesLegacy` en **Admin → Usuarios** y consola (`cuentas:duplicados-correo`,
`cuentas:fusionar-correo`).

Pendiente: vincular centro desde admin sin pasar por Configuración; copias JSON tras
recrear entidad con otro `centro_id`.

---

## Criterios de aceptación (cuando se implemente el acercamiento)

1. Dar acceso de secretario a un centro **sin** crear identidad cuyo alias sea la sigla del
   centro, salvo datos legacy documentados.
2. Tras login, un humano con un solo `identidad_id` accede a todos sus centros vía mandatos
   (`/elegir-centro`), no vía `/elegir-cuenta`.
3. Revocar mandato deja al usuario sin acceso al centro pero **no** elimina identidad ni
   centro.
4. Manual de acceso y Configuración/Centros describen “persona invitada”, no “usuario del
   centro”.
5. Tests: segundo secretario = segundo mandato sobre misma entidad contable; auditoría
   distingue identidades en una acción de escritura.

---

## Referencias

- D7 / flujo actual: `docs/dev/acceso.md`
- Personas y remesas: `docs/dev/personal.md`, D14 en `plan_ampliaciones.md`
- Admin usuarios (quitar mandato / baja): `docs/manual/_administracion.md`
