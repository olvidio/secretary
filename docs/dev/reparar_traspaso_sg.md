# Reparar asientos traspaso en centro sg (H16s)

Antes del arreglo del plan H16s, algunos apuntes al **41** o **42** quedaron como asientos `tipo=traspaso` (solo caja/banco). En sg esos códigos son **gastos**, no traspaso.

El comando los reescribe a **gasto + caja** (mismo importe, fecha y número):

```bash
# Ver qué haría (por defecto no escribe)
docker compose exec php-fpm php bin/console.php sg:reparar-traspasos

# Un centro concreto
docker compose exec php-fpm php bin/console.php sg:reparar-traspasos --centro=sgMontagut

# Aplicar cambios (también si el ejercicio está cerrado)
docker compose exec php-fpm php bin/console.php sg:reparar-traspasos --centro=sgMontagut --execute
```

Convive con las migraciones **0073** (cuentas 41–54 como gasto) y **0074** (plan_conceptos). Tras `--execute`, el 613 y el listado cuadran con partida doble en G/concepto.
