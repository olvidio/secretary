<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\NotificarCancelacionCuentaPersonal;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\administracion\application\EliminarCuentaPersonal;
use src\administracion\application\EliminarCentro;
use src\administracion\application\ResumenEliminacionCuentaPersonal;
use src\ambito\application\VaciarDatosCentro;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\legal\application\RegistrarAceptacion;
use src\legal\domain\services\CatalogoDocumentosLegales;
use src\legal\domain\services\DatosOperador;
use src\legal\domain\value_objects\HuellaAceptacion;
use src\legal\infrastructure\persistence\PdoAceptacionLegalRepository;
use src\personal\application\BorrarLibroPersonalDePersona;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;
use Tests\Soporte\EnviadorCorreoEnMemoria;
use Tests\Soporte\ServiciosAcceso;

final class EliminarCuentaPersonalTest extends TestCase
{
    use BaseDeDatosAislada;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->saltarSiNoHayPgsql();
        try {
            $this->pdo = $this->prepararBaseDeTestVacia('secretario_test_eliminar_cuenta_personal');
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base: ' . $e->getMessage());
        }
        (new SchemaInstaller($this->pdo))->install();
    }

    public function testResumenYBorradoConservaConsentimientos(): void
    {
        $identidades = new PdoIdentidadRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $centros = new PdoCentroRepository($this->pdo);
        $correo = new EnviadorCorreoEnMemoria();

        $alta = ServiciosAcceso::registrarUsuario($this->pdo)
            ->ejecutar('baja1', 'baja1@example.test', 'secret1', 'secret1', 'Usuario Baja', true);
        $identidadId = (int) $alta['identidad']->id;

        $registrar = new RegistrarAceptacion(
            new PdoAceptacionLegalRepository($this->pdo),
            CatalogoDocumentosLegales::porDefecto(),
            new DatosOperador('Op', 'op@test.local', 'Dir'),
        );
        $registrar->ejecutar(
            $identidadId,
            'formulario_registro',
            'Texto',
            new HuellaAceptacion('127.0.0.1', 'PHPUnit', 'es'),
        );

        $resumen = new ResumenEliminacionCuentaPersonal(
            $identidades,
            $personas,
            $centros,
            $this->pdo,
        );
        $prev = $resumen->ejecutar($identidadId);
        self::assertTrue($prev['es_personal']);
        self::assertTrue($prev['puede_borrar']);
        self::assertGreaterThan(0, $prev['datos']['consentimientos']);

        $eliminar = new EliminarCuentaPersonal(
            $identidades,
            $personas,
            $centros,
            new BorrarLibroPersonalDePersona($this->pdo, $personas),
            new EliminarCentro(
                $this->pdo,
                $centros,
                new PdoEjercicioRepository($this->pdo),
                new VaciarDatosCentro(
                    $this->pdo,
                    new PdoEjercicioRepository($this->pdo),
                    new PdoAsientoRepository($this->pdo),
                ),
            ),
            new NotificarCancelacionCuentaPersonal($correo),
            $this->pdo,
        );
        $eliminar->ejecutar($identidadId, true, true);

        self::assertNull($identidades->porId($identidadId));
        self::assertCount(1, $correo->enviados);
        self::assertSame('baja1@example.test', $correo->enviados[0]['destinatario']);

        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM aceptaciones_legales WHERE identidad_id IS NULL'
        );
        $st->execute();
        self::assertGreaterThan(0, (int) $st->fetchColumn());
    }
}
