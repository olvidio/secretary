# Manual breve (equivalencia con Excel)

Cinta **Secretario** del `.xlsm` → menú web.

Entrar con usuario o email, contraseña y (en cuentas de centro) un código TOTP de 6
dígitos. La primera vez hay que registrar la clave en la app de autenticación y
guardar los códigos de recuperación. Las cuentas **personales** no exigen TOTP y
abren `/yo`: se dan de alta poniendo el correo en **Nombres** de su centro. Un
secretario de otro centro (`scl2`) se crea en **Centros** y no ve las cuentas de éste.
Al crear el centro (o después, en **Este centro**) se puede subir el `.xlsm`. Mientras
estemos de pruebas, **Vaciar datos** borra el libro de ese centro para volver a cargarlo.

| Excel | Web |
| --- | --- |
| Configuración | Configuración |
| *(no existe en el Excel)* | Centros (alta de otra entidad, su secretario, importar Excel y, en pruebas, vaciar datos para recargar) |
| Nombres | Nombres (el correo abre el libro personal) |
| Presupuesto P / G | Presupuesto P / G |
| Apuntes | Apuntes (listado) |
| Apuntes cierre mes | Cierre de mes |
| Entrada apuntes P / G | Entrada P / Entrada G |
| Resumen mensual P / G | 613 P / 613 G |
| Cuentas personales | E37 |
| Resumen cuentas P | E37 resumen |
| Fecha cierre | Fecha de cierre |
| Saldos | Saldos |
| Conceptos P / G | Conceptos |
| Apuntes de un concepto | Por concepto |
| Ayuda (Schema) | Ayuda |
| Arqueo P / G | Arqueo P / G (desde 613) |
| *(no existe en el Excel)* | Mis cuentas (`/yo`): gastos e ingresos propios, caja y banco de la persona |
| *(no existe en el Excel)* | Remesas: envío mensual de `/yo` al centro (`/remesas`) |

## Mis cuentas (nivel personal)

Pantalla sencilla (saldo, gráfico del mes, alta rápida de gasto o ingreso). Las
categorías son el plan P más subcuentas propias; cada subcuenta debe colgar de un
código del plan. No aparecen 613 ni E37. Lo que se anota aquí **no** mueve la caja
del centro.

Cerrar el mes y enviarlo al centro: pestaña **Remesa**. El centro acepta o
rechaza en **Remesas**. La caja propia no se mueve al centro.

## Apuntes

- **P/G:** personal o general.
- **A/B/C:** A = no toca caja/banco del centro; B = banco; C = caja.
- En Entrada P/G, con iniciales elegidas, al escribir en observaciones aparecen las de esa persona (las más usadas primero); al elegir una se copian observaciones y concepto.
- Cantidad siempre positiva; el signo lo da el concepto.
- Conceptos **41** (banco→caja) y **42** (caja→banco): se genera el apunte espejo.
- Los apuntes A de una persona deberían cuadrar (ingresos = gastos) si se anota el 111 de contrapartida.

## Cierre de mes

En centros de n se carga **vivienda (21)** y el ingreso G **11**. En agd/sss+ se carga **necesidades (6)** y G **14**. Quien tiene intervalo de meses en Nombres no se incluye. Importe fijo: columna de vivienda en Nombres.
