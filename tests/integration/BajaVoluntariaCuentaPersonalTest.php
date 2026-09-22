<?php

declare(strict_types=1);

namespace Tests\integration;

use DateTimeImmutable;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\ConfirmarBajaCuentaPersonal;
use src\acceso\application\NotificarCancelacionCuentaPersonal;
use src\acceso\application\SolicitarBajaCuentaPersonal;
use src\acceso\application\NotificarConfirmacionBajaCuenta;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\administracion\application\EliminarCentro;
use src\administracion\application\EliminarCuentaPersonal;
use src\ambito\application\VaciarDatosCentro;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\personal\application\BorrarLibroPersonalDePersona;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;
use Tests\Soporte\EnviadorCorreoEnMemoria;
use Tests\Soporte\ServiciosAcceso;

final class BajaVoluntariaCuentaPersonalTest extends TestCase
{
    use BaseDeDatosAislada;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->saltarSiNoHayPgsql();
        try {
            $this->pdo = $this->prepararBaseDeTestVacia('secretario_test_baja_voluntaria');
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base: ' . $e->getMessage());
        }
        (new SchemaInstaller($this->pdo))->install();
    }

    public function testSolicitudYConfirmacionPorCorreo(): void
    {
        $identidades = new PdoIdentidadRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $centros = new PdoCentroRepository($this->pdo);
        $correo = new EnviadorCorreoEnMemoria();

        $alta = ServiciosAcceso::registrarUsuario($this->pdo)
            ->ejecutar('bajavol', 'bajavol@example.test', 'secret1', 'secret1', 'Voluntaria', true);
        $identidadId = (int) $alta['identidad']->id;
        $identidades->marcarEmailVerificado($identidadId, new DateTimeImmutable());

        $solicitar = new SolicitarBajaCuentaPersonal(
            $identidades,
            new NotificarConfirmacionBajaCuenta($identidades, $correo),
        );
        $solicitar->ejecutar($identidadId, true);
        self::assertCount(1, $correo->enviados);

        preg_match('/token=([a-f0-9]+)/', $correo->enviados[0]['cuerpo'], $m);
        self::assertNotEmpty($m[1] ?? '');

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
        $confirmar = new ConfirmarBajaCuentaPersonal($identidades, $eliminar);
        $confirmar->ejecutar($m[1]);

        self::assertNull($identidades->porId($identidadId));
        self::assertCount(1, $correo->enviados, 'No debe enviar segundo correo de cancelación');
    }
}
