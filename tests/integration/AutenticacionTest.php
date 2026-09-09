<?php

declare(strict_types=1);

namespace Tests\integration;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\acceso\application\AutorizarPeticion;
use src\acceso\application\ConfirmarTotp;
use src\acceso\application\IniciarSesion;
use src\acceso\application\PrepararTotp;
use src\acceso\application\VerificarSegundoFactor;
use src\acceso\domain\entity\Identidad;
use src\acceso\domain\services\TotpRfc6238;
use src\acceso\infrastructure\crypto\CifradorSecretos;
use src\acceso\infrastructure\persistence\PdoAccesoRutaRepository;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class AutenticacionTest extends TestCase
{
    use BaseDeDatosAislada;

    private const PIMIENTO = 'phpunit-app-key-not-for-production-use-32ch';

    public function testLoginAliasYEmailYTotpObligatorio(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia();
        (new SchemaInstaller($pdo))->install();
        $repo = new PdoIdentidadRepository($pdo);
        $iniciar = new IniciarSesion($repo);

        $porAlias = $iniciar->ejecutar('scl', 'cambiar');
        self::assertSame('pendiente_activar', $porAlias->estado);
        self::assertNotNull($porAlias->identidadId);
        self::assertSame('centro', $porAlias->nivel);

        $porEmail = $iniciar->ejecutar('scl@secretario.local', 'cambiar');
        self::assertSame('pendiente_activar', $porEmail->estado);

        $malo = $iniciar->ejecutar('scl', 'no-es');
        self::assertSame('fallo', $malo->estado);

        $cifrador = new CifradorSecretos(self::PIMIENTO);
        $prep = new PrepararTotp($repo, $cifrador);
        $datos = $prep->ejecutar((int) $porAlias->identidadId);
        $codigo = TotpRfc6238::codigo($datos['secreto']);
        $confirmar = new ConfirmarTotp($repo, $cifrador, self::PIMIENTO);
        $codigos = $confirmar->ejecutar((int) $porAlias->identidadId, $codigo);
        self::assertCount(8, $codigos);
        self::assertTrue($repo->totpConfirmado((int) $porAlias->identidadId));

        $despues = $iniciar->ejecutar('scl', 'cambiar');
        self::assertSame('pendiente_verificar', $despues->estado);

        $verificar = new VerificarSegundoFactor($repo, $cifrador, self::PIMIENTO);
        $ok = $verificar->ejecutar((int) $porAlias->identidadId, TotpRfc6238::codigo($datos['secreto']));
        self::assertSame('autenticado', $ok->estado);

        $auth = new AutorizarPeticion(new PdoAccesoRutaRepository($pdo), $repo);
        $id = (int) $porAlias->identidadId;
        $centroId = $porAlias->centros[0]['centro_id'];
        $sinTotp = $auth->ejecutar(
            'src\\apuntes\\infrastructure\\http\\ApunteController',
            'list',
            'GET',
            true,
            null,
            $id,
            null,
            '',
            true,
        );
        self::assertFalse($sinTotp->permitido);

        $conSesion = $auth->ejecutar(
            'src\\apuntes\\infrastructure\\http\\ApunteController',
            'list',
            'GET',
            true,
            $id,
            null,
            $centroId,
            'centro',
            true,
        );
        self::assertTrue($conSesion->permitido);
    }

    public function testPersonaNoLeeCentroYRutaPublicaExigeCatalogo(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia();
        (new SchemaInstaller($pdo))->install();
        $st = $pdo->prepare(
            'INSERT INTO personas (nombre, apellidos, iniciales, orden)
             VALUES (:n, :a, :i, 1) RETURNING id'
        );
        $st->execute([':n' => 'Ana', ':a' => 'Prueba', ':i' => 'apr']);
        $personaId = (int) $st->fetchColumn();
        $repo = new PdoIdentidadRepository($pdo);
        $id = $repo->guardar(new Identidad(
            null,
            'ana@example.test',
            password_hash('clave', PASSWORD_DEFAULT),
            'Ana',
            true,
            0,
            null,
            null,
            null,
        ));
        self::assertNotNull($id->id);
        $repo->vincularPersona($id->id, $personaId);

        $login = (new IniciarSesion($repo))->ejecutar('ana@example.test', 'clave');
        self::assertSame('autenticado', $login->estado);
        self::assertSame('persona', $login->nivel);
        self::assertSame($personaId, $login->personaId);

        $auth = new AutorizarPeticion(new PdoAccesoRutaRepository($pdo), $repo);
        $centro = $auth->ejecutar(
            'frontend\\shared\\http\\PageController',
            'page',
            'GET',
            true,
            $id->id,
            null,
            null,
            'persona',
            false,
            $personaId,
        );
        self::assertFalse($centro->permitido);
        self::assertSame('/yo', $centro->redirect);

        $desconocida = $auth->ejecutar(
            'src\\no\\ExisteController',
            'x',
            'GET',
            true,
            $id->id,
            null,
            null,
            'persona',
            true,
        );
        self::assertFalse($desconocida->permitido);
        self::assertSame(401, $desconocida->status);
    }

    public function testBloqueoTrasCincoFallos(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia();
        (new SchemaInstaller($pdo))->install();
        $repo = new PdoIdentidadRepository($pdo);
        $iniciar = new IniciarSesion($repo);
        $ahora = new DateTimeImmutable('2026-09-09 12:00:00');
        for ($i = 0; $i < 5; $i++) {
            $r = $iniciar->ejecutar('scl', 'mal', $ahora);
            self::assertSame('fallo', $r->estado);
        }
        $bloqueado = $iniciar->ejecutar('scl', 'cambiar', $ahora);
        self::assertSame('fallo', $bloqueado->estado);
        self::assertStringContainsString('bloqueada', $bloqueado->mensaje);

        $libre = $iniciar->ejecutar('scl', 'cambiar', $ahora->modify('+16 minutes'));
        self::assertSame('pendiente_activar', $libre->estado);
    }

    public function testCodigoDeRecuperacionEsDeUnSoloUso(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia();
        (new SchemaInstaller($pdo))->install();
        $repo = new PdoIdentidadRepository($pdo);
        $id = (int) $repo->porEmailOAlias('scl')?->id;
        self::assertGreaterThan(0, $id);
        $cifrador = new CifradorSecretos(self::PIMIENTO);
        $datos = (new PrepararTotp($repo, $cifrador))->ejecutar($id);
        $codigos = (new ConfirmarTotp($repo, $cifrador, self::PIMIENTO))
            ->ejecutar($id, TotpRfc6238::codigo($datos['secreto']));
        $verificar = new VerificarSegundoFactor($repo, $cifrador, self::PIMIENTO);
        $primero = $verificar->ejecutar($id, $codigos[0]);
        self::assertSame('autenticado', $primero->estado);
        $reuso = $verificar->ejecutar($id, $codigos[0]);
        self::assertSame('fallo', $reuso->estado);
    }
}
