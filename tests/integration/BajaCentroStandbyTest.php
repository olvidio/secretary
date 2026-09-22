<?php

declare(strict_types=1);

namespace Tests\integration;

use DateTimeImmutable;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\NotificarBajaSecretarioAVinculados;
use src\acceso\application\NotificarInicioBajaCentro;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\administracion\application\ProgramarBajaCuentaCentro;
use src\administracion\application\PurgarBajasCentroProgramadas;
use src\administracion\application\PurgarIdentidadAcceso;
use src\administracion\application\ReactivarCuentaCentro;
use src\administracion\application\ResumenBajaCuentaCentro;
use src\ambito\application\CrearCentro;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;
use Tests\Soporte\EnviadorCorreoEnMemoria;

final class BajaCentroStandbyTest extends TestCase
{
    use BaseDeDatosAislada;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->saltarSiNoHayPgsql();
        try {
            $this->pdo = $this->prepararBaseDeTestVacia('secretario_test_baja_centro');
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base: ' . $e->getMessage());
        }
        (new SchemaInstaller($this->pdo))->install();
    }

    public function testProgramarReactivarYPurgar(): void
    {
        $identidades = new PdoIdentidadRepository($this->pdo);
        $correo = new EnviadorCorreoEnMemoria();
        $crear = \Tests\Soporte\ServiciosAcceso::crearCentro($this->pdo);
        $alta = $crear->ejecutar([
            'codigo' => 'STBY-1',
            'nombre' => 'Centro standby',
            'tipo_cierre' => 'vivienda',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'usuario' => 'sec-stby',
            'email' => 'sec-stby@test.local',
            'password' => 'secret',
            'verificar_email' => false,
        ]);
        $secId = (int) $alta['identidad']->id;

        $programar = new ProgramarBajaCuentaCentro(
            $identidades,
            new ResumenBajaCuentaCentro($identidades),
            new NotificarInicioBajaCentro($identidades, $correo),
            new NotificarBajaSecretarioAVinculados($correo),
        );
        $programar->ejecutar($secId, true);

        $identidad = $identidades->porId($secId);
        self::assertNotNull($identidad);
        self::assertFalse($identidad->activo);
        self::assertSame([], $identidades->centrosDe($secId));
        self::assertNotNull($identidades->bajaCentroPendiente($secId));
        self::assertCount(1, $correo->enviados);

        (new ReactivarCuentaCentro($identidades))->ejecutar($secId);
        $identidad = $identidades->porId($secId);
        self::assertTrue($identidad?->activo);
        self::assertCount(1, $identidades->centrosDe($secId));
        self::assertNull($identidades->bajaCentroPendiente($secId));

        $programar->ejecutar($secId, true);
        $this->pdo->prepare(
            'UPDATE identidades SET baja_centro_ejecutar_at = :p WHERE id = :id'
        )->execute([
            ':p' => (new DateTimeImmutable('-1 hour'))->format('c'),
            ':id' => $secId,
        ]);

        $purga = new PurgarBajasCentroProgramadas(
            $identidades,
            new PurgarIdentidadAcceso($identidades, $this->pdo),
        );
        $r = $purga->ejecutar();
        self::assertSame(1, $r['purga_total']);
        self::assertNull($identidades->porId($secId));
    }
}
