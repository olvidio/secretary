<?php

declare(strict_types=1);

namespace Tests\integration;

use PHPUnit\Framework\TestCase;
use src\acceso\domain\entity\Identidad;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\administracion\application\FusionarIdentidadesLegacy;
use src\administracion\application\ListarIdentidadesDuplicadasPorEmail;
use src\ambito\application\CrearCentro;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;
use Tests\Soporte\ServiciosAcceso;

final class FusionarIdentidadesLegacyTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_fusion_legacy';

    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->saltarSiNoHayPgsql();
        $this->pdo = $this->prepararBaseDeTestVacia(self::DB_NAME);
        (new SchemaInstaller($this->pdo))->install();
    }

    public function testFusionaDosSecretariosMismoCorreo(): void
    {
        $crear = ServiciosAcceso::crearCentro($this->pdo);
        $crear->ejecutar([
            'codigo' => 'A',
            'nombre' => 'Centro A',
            'tipo' => 'sg',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'usuario' => 'sec-a',
            'email' => 'dup@test.local',
            'password' => 'secret1',
        ]);
        $r2 = $crear->ejecutar([
            'codigo' => 'B',
            'nombre' => 'Centro B',
            'tipo' => 'sg',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'usuario' => 'sec-b',
            'email' => 'otro@test.local',
            'password' => 'secret2',
        ]);

        $repo = new PdoIdentidadRepository($this->pdo);
        $secB = $repo->porAlias('sec-b');
        self::assertNotNull($secB);
        self::assertNotNull($secB->id);
        $repo->guardar(new Identidad(
            $secB->id,
            'dup@test.local',
            $secB->passwordHash,
            $secB->nombre,
            $secB->activo,
            $secB->intentosFallidos,
            $secB->bloqueadoHasta,
            $secB->ultimoAcceso,
            $secB->alias,
            $secB->emailVerificadoAt,
        ));

        $grupos = (new ListarIdentidadesDuplicadasPorEmail($repo))->ejecutar();
        self::assertCount(1, $grupos);
        self::assertSame('dup@test.local', $grupos[0]['email']);
        self::assertCount(2, $grupos[0]['identidades']);

        $idA = $repo->porAlias('sec-a')?->id;
        self::assertNotNull($idA);
        self::assertNotNull($r2['centro']->id);

        $fusion = new FusionarIdentidadesLegacy($repo, $this->pdo);
        $fusion->ejecutar('dup@test.local', $idA, 0, true);

        self::assertSame([], (new ListarIdentidadesDuplicadasPorEmail($repo))->ejecutar());
        self::assertCount(2, $repo->centrosDe($idA));
        self::assertNull($repo->porAlias('sec-b'));
    }
}
