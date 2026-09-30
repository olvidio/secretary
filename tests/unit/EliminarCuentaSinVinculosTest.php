<?php

declare(strict_types=1);

namespace Tests\unit;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\administracion\application\EliminarCuentaSinVinculos;
use src\administracion\application\ResumenBajaCuentaCentro;
use src\administracion\application\ResumenEliminacionCuentaPersonal;
use src\administracion\application\ResumenEliminacionUsuario;
use src\ambito\domain\contracts\CentroRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\contracts\EnviadorCorreo;

final class EliminarCuentaSinVinculosTest extends TestCase
{
    public function testBorraCuentaSinLibroNiEntidad(): void
    {
        $identidad = new Identidad(11, 'dani@moneders.net', 'hash', 'daniel', true, 0, null, null, 'dani2');
        $identidades = $this->createMock(IdentidadRepository::class);
        $identidades->method('porId')->willReturn($identidad);
        $identidades->method('esCuentaPersonal')->willReturn(false);
        $identidades->method('esCuentaSecretarioCentro')->willReturn(false);
        $identidades->method('personasDe')->willReturn([]);
        $identidades->method('centrosDe')->willReturn([]);
        $identidades->expects(self::once())->method('eliminar')->with(11);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects(self::exactly(2))->method('execute');
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::exactly(2))->method('prepare')->willReturn($stmt);

        $correo = $this->createMock(EnviadorCorreo::class);
        $correo->expects(self::once())->method('enviar')->with(
            'dani@moneders.net',
            self::anything(),
            self::stringContains('dani'),
        );

        (new EliminarCuentaSinVinculos($identidades, $correo, $pdo))->ejecutar(11, true);
    }

    public function testResumenPermiteBorrarCuentaVacia(): void
    {
        $identidad = new Identidad(11, 'dani@moneders.net', 'hash', 'daniel', true, 0, null, null, 'dani2');
        $identidades = $this->createStub(IdentidadRepository::class);
        $identidades->method('esCuentaPersonal')->willReturn(false);
        $identidades->method('esCuentaSecretarioCentro')->willReturn(false);
        $identidades->method('porId')->willReturn($identidad);

        $resumen = (new ResumenEliminacionUsuario(
            $identidades,
            new ResumenEliminacionCuentaPersonal(
                $this->createStub(IdentidadRepository::class),
                $this->createStub(PersonaRepository::class),
                $this->createStub(CentroRepository::class),
                $this->createStub(PDO::class),
            ),
            new ResumenBajaCuentaCentro($this->createStub(IdentidadRepository::class)),
        ))->ejecutar(11);

        self::assertTrue($resumen['puede_borrar']);
        self::assertTrue($resumen['es_vacia']);
        self::assertFalse($resumen['es_secretario']);
    }
}
