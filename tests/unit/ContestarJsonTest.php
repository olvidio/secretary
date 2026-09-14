<?php

declare(strict_types=1);

namespace Tests\unit;

use PDOException;
use PHPUnit\Framework\TestCase;
use src\shared\infrastructure\http\ContestarJson;

final class ContestarJsonTest extends TestCase
{
    public function testMensajePdoExtraeErrorPostgres(): void
    {
        $e = new PDOException(
            'SQLSTATE[42703]: Undefined column: 7 ERROR: column "gasto_generales" of relation "asientos" does not exist'
        );
        self::assertSame(
            'column "gasto_generales" of relation "asientos" does not exist',
            ContestarJson::mensajePdo($e),
        );
    }

    public function testMensajePdoSinDetallePostgres(): void
    {
        $e = new PDOException('SQLSTATE[08006]: connection failure');
        self::assertSame('SQLSTATE[08006]: connection failure', ContestarJson::mensajePdo($e));
    }
}
