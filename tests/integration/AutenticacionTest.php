<?php

declare(strict_types=1);

namespace Tests\integration;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\acceso\application\AutorizarPeticion;
use src\acceso\application\ConfirmarTotp;
use src\acceso\application\IniciarSesion;
use src\acceso\application\ResolverPersonaActiva;
use src\acceso\application\PrepararTotp;
use src\acceso\application\ConfirmarEmailRegistro;
use src\acceso\application\RegistrarUsuario;
use src\acceso\application\VerificarSegundoFactor;
use src\acceso\domain\entity\Identidad;
use src\acceso\domain\services\TotpRfc6238;
use src\acceso\infrastructure\crypto\CifradorSecretos;
use src\acceso\infrastructure\persistence\PdoAccesoRutaRepository;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;
use Tests\Soporte\ServiciosAcceso;

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
        $resolver = new ResolverPersonaActiva($repo);
        $iniciar = ServiciosAcceso::iniciarSesion($pdo, $repo);

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

        $verificar = ServiciosAcceso::verificarSegundoFactor($pdo, $cifrador, self::PIMIENTO, $repo);
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
        $repo->marcarEmailVerificado($id->id, new DateTimeImmutable());

        $resolver = new ResolverPersonaActiva($repo);
        $login = ServiciosAcceso::iniciarSesion($pdo, $repo)->ejecutar('ana@example.test', 'clave');
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
        $iniciar = ServiciosAcceso::iniciarSesion($pdo, $repo);
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
        $resolver = new ResolverPersonaActiva($repo);
        $verificar = ServiciosAcceso::verificarSegundoFactor($pdo, $cifrador, self::PIMIENTO, $repo);
        $primero = $verificar->ejecutar($id, $codigos[0]);
        self::assertSame('autenticado', $primero->estado);
        $reuso = $verificar->ejecutar($id, $codigos[0]);
        self::assertSame('fallo', $reuso->estado);
    }

    public function testRegistroCreaPersonaYPermiteLogin(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia();
        (new SchemaInstaller($pdo))->install();
        $identidades = new PdoIdentidadRepository($pdo);
        $alta = ServiciosAcceso::registrarUsuario($pdo)
            ->ejecutar('dani', 'dani@example.test', 'secret1', 'secret1', 'Dani', true);
        self::assertNotNull($alta['identidad']->id);
        $resolver = new ResolverPersonaActiva($identidades);
        $pendiente = ServiciosAcceso::iniciarSesion($pdo, $identidades)->ejecutar('dani', 'secret1');
        self::assertSame('fallo', $pendiente->estado);
        self::assertStringContainsString('correo', strtolower($pendiente->mensaje));
        $catalogo = \src\legal\domain\services\CatalogoDocumentosLegales::porDefecto();
        $aceptaciones = new \src\legal\infrastructure\persistence\PdoAceptacionLegalRepository($pdo);
        (new ConfirmarEmailRegistro(
            $identidades,
            new \src\legal\application\RegistrarAceptacion(
                $aceptaciones,
                $catalogo,
                new \src\legal\domain\services\DatosOperador('Op', 'op@test.local', 'Dir'),
            ),
            $catalogo,
        ))->ejecutar($alta['token_verificacion']);
        $login = ServiciosAcceso::iniciarSesion($pdo, $identidades)->ejecutar('dani', 'secret1');
        self::assertSame('autenticado', $login->estado);
        self::assertSame('persona', $login->nivel);
        self::assertNotNull($login->personaId);
        self::assertFalse($identidades->tienePersonaEnAlgunCentro((int) $alta['identidad']->id));
        $centroPersonal = (int) $pdo->query(
            "SELECT COUNT(*) FROM centros WHERE tipo = 'p'"
        )->fetchColumn();
        self::assertSame(1, $centroPersonal);
        $nAcept = (int) $pdo->query("SELECT COUNT(*) FROM aceptaciones_legales WHERE canal = 'confirmacion_email'")->fetchColumn();
        self::assertSame(1, $nAcept);
        $desconocido = ServiciosAcceso::iniciarSesion($pdo, $identidades)->ejecutar('nadie', 'secret1');
        self::assertSame('desconocido', $desconocido->estado);
    }
}
