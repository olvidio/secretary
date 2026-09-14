# Equivalencias con el Excel

Para quien viene del `moviments.xlsm`: cada hoja de la cinta **Secretario** del
Excel tiene su pantalla en el programa web. Lo que cambia de nombre está en esta
tabla; lo que no existía en el Excel va marcado como nuevo.

| Excel | Programa web |
| --- | --- |
| Configuración | Configuración |
| Nombres | Nombres (el correo abre el libro personal de esa persona) |
| Presupuesto P / G | Presupuesto P / Presupuesto G |
| Apuntes | Apuntes |
| Apuntes cierre mes | Cierre de mes |
| Entrada apuntes P / G | Entrada P / Entrada G |
| Resumen mensual P / G | 613 P / 613 G |
| Cuentas personales | Cuentas personales (E37) |
| Resumen cuentas P | Resumen E37 |
| Fecha cierre | Fecha cierre |
| Saldos | Saldos |
| Conceptos P / G | Conceptos P / Conceptos G |
| Apuntes de un concepto | Por concepto |
| Arqueo P / G | Arqueo P / Arqueo G |
| Ayuda (Schema) | Ayuda |
| *(nuevo)* | Centros: alta de otra entidad y de su secretario |
| *(nuevo)* | Ejercicios: apertura y cierre de año |
| *(nuevo)* | Tesorería: las cajas y cuentas reales |
| *(nuevo)* | Plantillas P / G: apuntes que se repiten igual |
| *(nuevo)* | Traspasos: mover dinero entre caja y banco |
| *(nuevo)* | Comprobaciones: descuadres y meses sin anotar |
| *(nuevo)* | Copias: guardar y recuperar todo |
| *(nuevo)* | Mis cuentas (`/yo`): el libro personal de cada persona |
| *(nuevo)* | Remesas: el mes que cada persona envía al centro |

## Reglas que conviene saber

- Los apuntes P y G siguen en una sola lista, distinguidos con P o G, igual que
  en el Excel.
- La cantidad sigue siendo siempre positiva: el signo lo pone el concepto.
- En Configuración se sigue escribiendo **Curso** en lugar de Año, y **2024**
  para el curso 2024-2025, si la contabilidad va de septiembre a agosto.
- Al crear un centro (o después, en Centros) se puede subir el `.xlsm` para
  cargar el libro. Mientras se está de pruebas, **Vaciar datos** borra el libro
  de ese centro para volver a cargarlo.
- Lo que se anota en Mis cuentas **no** mueve la caja del centro: llega al
  centro cuando la persona envía la remesa del mes.
