<?php

declare(strict_types=1);

namespace Tests\unit;

use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use PHPUnit\Framework\TestCase;
use src\acceso\application\CatalogoRutas;

final class CatalogoRutasTest extends TestCase
{
    public function testTodaRutaDelDispatcherEstaEnElCatalogo(): void
    {
        $r = new RecolectorRutas(new Std(), new GroupCountBased());
        $root = dirname(__DIR__, 2);
        foreach ([$root . '/src/shared/config/routes.php', $root . '/frontend/shared/config/routes.php'] as $file) {
            $def = require $file;
            $def($r);
        }
        $catalogo = [];
        foreach (CatalogoRutas::todas() as $fila) {
            $catalogo[$fila['clase'] . '::' . $fila['metodo']] = $fila['ambito'];
        }
        $faltan = [];
        foreach ($r->handlers as [$clase, $metodo]) {
            $clave = $clase . '::' . $metodo;
            if (!isset($catalogo[$clave])) {
                $faltan[] = $clave;
            }
        }
        self::assertSame([], $faltan, 'Rutas sin fila en CatalogoRutas (default deny)');
    }
}

final class RecolectorRutas extends RouteCollector
{
    /** @var list<array{0:string,1:string}> */
    public array $handlers = [];

    public function addRoute($httpMethod, $route, $handler): void
    {
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            $this->handlers[] = [(string) $handler[0], (string) $handler[1]];
        }
        parent::addRoute($httpMethod, $route, $handler);
    }
}
