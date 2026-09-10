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
- En **Apuntes**, **Por concepto** y **E37** se puede editar o borrar (salvo remesas). Al abrir, el cursor va a observaciones; si se cambia otro campo, pide confirmación al guardar.
- Conceptos **41** (banco→caja) y **42** (caja→banco): se genera el apunte espejo.
- Los apuntes A de una persona deberían cuadrar (ingresos = gastos) si se anota el 111 de contrapartida.
- En **Nombres**, «vivienda aporta a generales» (por persona, no n/agd del centro): si está en sí, cada P/21 origen A debe tener la misma cantidad en G/11. **Comprobaciones** lo verifica. Ahí también se ve quién no ha anotado movimiento en el mes de cierre o en los anteriores (la exención deja fuera esos meses).
- En **Entrada G**, un gasto con iniciales anota cuatro apuntes: ingreso P/111, gasto P/21, ingreso G/11 y el gasto de G. Si la persona no aporta vivienda a generales, solo el gasto.

## Cierre de mes

En centros de n se carga **vivienda (21)** y el ingreso G **11**. En agd/sss+ se carga **necesidades (6)** y G **14**. Solo quien tiene «vivienda aporta a generales» en sí. La exención de meses (llegada o salida a mitad de año) deja fuera esos meses; quien no aporta no usa la exención para quedar fuera del cierre. Importe fijo: columna de vivienda en Nombres. Observaciones: **automático**.
