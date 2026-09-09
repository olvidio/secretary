# Arquitectura Secretario

Aplicación PHP 8.2 con capas DDD. Un centro, un ejercicio, doble contabilidad **P** (personal) y **G** (general).

## Entrada HTTP

`public/index.php` → `Kernel` carga `.env`, sesión (`HttpOnly` + `SameSite=Lax`), PHP-DI y FastRoute. La autorización sale de `rutas_acceso` (`docs/dev/acceso.md`), no de una lista incrustada.

- Rutas HTML en `frontend/shared/config/routes.php`.
- Rutas API en `src/shared/config/routes.php` bajo `/api/...`.
- JSON desde controladores de `src/*/infrastructure/http`.

## Docker

Stack independiente en `/home/dani/docker_images/secretary`. Ver `docs/dev/docker.md`.

## Datos

El libro de apuntes equivale a la hoja Talonarios. Informes 613 y E37 se calculan en dominio (`src/informes/domain/services`), no se materializan en hojas.

Cierre de mes genera apuntes marcados `es_cierre` (regenerables). Traspasos 41/42 generan el apunte espejo B↔C.

## Módulos

| Módulo | Responsabilidad |
| --- | --- |
| acceso | Identidades, TOTP, CSRF, `rutas_acceso` |
| personal | Libro X de la persona (nivel 1), pantallas `/yo` |
| configuracion | Centro, año/curso, fechas, tipo de cierre |
| personas | Residentes e intervalos de exención |
| conceptos | Plan P/G |
| apuntes | Alta, listado, validación, 41/42 |
| presupuestos | Previsto anual por concepto |
| cierre | Reparto vivienda / necesidades |
| informes | 613, E37, saldos, apuntes de un concepto |
| arqueo | Conteo caja vs saldo contable |
| importacion | Lectura del `.xlsm` Secretario v8 |
