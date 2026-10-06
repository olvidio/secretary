<?php

declare(strict_types=1);

namespace Tests\integration;

use PHPUnit\Framework\TestCase;
use src\acceso\application\EtiquetaCuentaIdentidad;
use src\acceso\application\IniciarSesion;
use src\acceso\application\RegistrarCentro;
use src\acceso\application\RegistrarUsuario;
use src\acceso\application\ResolverPersonaActiva;
use src\acceso\domain\contracts\IdentidadRepository;
use src\legal\domain\services\CatalogoDocumentosLegales;
use src\legal\infrastructure\persistence\PdoAceptacionLegalRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;
use Tests\Soporte\ServiciosAcceso;

/** D15: un correo → una identidad; mandatos de centro sobre la misma cuenta. Legacy multi-identidad: elegir cuenta. */
final class CorreoMulticuentaTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_correo_multi';

    public function testMismoCorreoPersonaYCentroCompartenIdentidad(): void
    {
        $ctx = $this->contexto('persona_centro');
        $persona = $this->registrarPersona($ctx, 'ana', 'ana@multi.test', 'secret1', 'Ana');
        $centro = $this->registrarCentro(
            $ctx,
            'casa-a',
            'Casa A',
            'sec-casa-a',
            'ana@multi.test',
            'secret2',
            'Ana Sec',
        );
        self::assertSame($persona['identidad']->id, $centro['identidad']->id);
        self::assertCount(1, $ctx['identidades']->centrosDe((int) $persona['identidad']->id));
        self::assertFalse($ctx['identidades']->esCuentaPersonal((int) $persona['identidad']->id));
        self::assertNull($ctx['identidades']->cuentaPersonalPorEmail('ana@multi.test'));
        $st = $ctx['pdo']->prepare('SELECT 1 FROM identidad_persona WHERE identidad_id = :id LIMIT 1');
        $st->execute([':id' => $persona['identidad']->id]);
        self::assertTrue((bool) $st->fetchColumn());

        $this->expectException(\InvalidArgumentException::class);
        $this->registrarPersona($ctx, 'ana2', 'ana@multi.test', 'secret3', 'Ana');
    }

    public function testLoginPorCorreoConMandatoCentroPideActivarTotp(): void
    {
        $ctx = $this->contexto('login_mandato');
        $this->registrarPersona($ctx, 'bob', 'bob@multi.test', 'clave1', 'Bob');
        $this->registrarCentro($ctx, 'casa-b', 'Casa B', 'sec-casa-b', 'bob@multi.test', 'clave1', 'Bob Sec');

        $login = $this->iniciarSesion($ctx)->ejecutar('bob@multi.test', 'clave1');
        self::assertSame('pendiente_activar', $login->estado);
        self::assertSame('centro', $login->nivel);
        self::assertCount(1, $login->centros);
    }

    public function testDosCentrosMismoCorreoUnaIdentidadDosMandatos(): void
    {
        $ctx = $this->contexto('dos_centros');
        $uno = $this->registrarCentro($ctx, 'casa-1', 'Casa Uno', 'sec-uno', 'sec@multi.test', 'clave1', 'Sec Uno');
        $dos = $this->registrarCentro($ctx, 'casa-2', 'Casa Dos', 'sec-dos', 'sec@multi.test', 'clave1', 'Sec Dos');
        self::assertSame($uno['identidad']->id, $dos['identidad']->id);

        $login = $this->iniciarSesion($ctx)->ejecutar('sec@multi.test', 'clave1');
        self::assertSame('pendiente_activar', $login->estado);
        self::assertCount(2, $login->centros);
        self::assertNull($ctx['identidades']->cuentaPersonalPorEmail('sec@multi.test'));
    }

    public function testAltaCentroActualizaClaveDeIdentidadExistente(): void
    {
        $ctx = $this->contexto('clave_actualizada');
        $this->registrarPersona($ctx, 'car', 'car@multi.test', 'clave-p', 'Car');
        $this->registrarCentro($ctx, 'casa-c', 'Casa C', 'sec-casa-c', 'car@multi.test', 'clave-s', 'Car Sec');

        self::assertSame('fallo', $this->iniciarSesion($ctx)->ejecutar('car@multi.test', 'clave-p')->estado);
        $comoCentro = $this->iniciarSesion($ctx)->ejecutar('car@multi.test', 'clave-s');
        self::assertSame('pendiente_activar', $comoCentro->estado);
        self::assertSame('centro', $comoCentro->nivel);
    }

    public function testLoginPorAliasDeCentroNuevo(): void
    {
        $ctx = $this->contexto('por_alias');
        $centro = $this->registrarCentro($ctx, 'casa-d', 'Casa D', 'sec-dan', 'dan@multi.test', 'clave1', 'Dan Sec');

        $login = $this->iniciarSesion($ctx)->ejecutar('sec-dan', 'clave1');
        self::assertSame('pendiente_activar', $login->estado);
        self::assertSame('centro', $login->nivel);
        self::assertSame($centro['identidad']->id, $login->identidadId);
    }

    public function testLoginPorAliasTrasMandatoSobreCuentaPersonal(): void
    {
        $ctx = $this->contexto('alias_personal');
        $persona = $this->registrarPersona($ctx, 'dan', 'dan@multi.test', 'clave1', 'Dan');
        $this->registrarCentro($ctx, 'casa-e', 'Casa E', 'sec-dan', 'dan@multi.test', 'clave1', 'Dan Sec');

        $login = $this->iniciarSesion($ctx)->ejecutar('dan', 'clave1');
        self::assertSame('pendiente_activar', $login->estado);
        self::assertSame($persona['identidad']->id, $login->identidadId);
    }

    public function testLegacyDosIdentidadesMismoCorreoPidenElegirCuenta(): void
    {
        $ctx = $this->contexto('legacy_dup');
        $this->registrarCentro($ctx, 'casa-f', 'Casa F', 'sec-eva', 'eva@multi.test', 'clave1', 'Eva Sec');
        $this->registrarCentro($ctx, 'casa-g', 'Casa G', 'sec-g', 'otro@multi.test', 'clave1', 'Otro Sec');
        $secG = $ctx['identidades']->porAlias('sec-g');
        self::assertNotNull($secG?->id);
        $ctx['identidades']->guardar(new \src\acceso\domain\entity\Identidad(
            $secG->id,
            'eva@multi.test',
            $secG->passwordHash,
            $secG->nombre,
            $secG->activo,
            $secG->intentosFallidos,
            $secG->bloqueadoHasta,
            $secG->ultimoAcceso,
            $secG->alias,
            $secG->emailVerificadoAt,
        ));

        $login = $this->iniciarSesion($ctx)->ejecutar('eva@multi.test', 'clave1');
        self::assertSame('pendiente_elegir_cuenta', $login->estado);
        self::assertCount(2, $login->cuentas);
    }

    public function testClaveIncorrectaConVariasCuentasNoInvitaARegistrarse(): void
    {
        $ctx = $this->contexto('fallo_multi');
        $this->registrarPersona($ctx, 'fin', 'fin@multi.test', 'clave1', 'Fin');
        $this->registrarCentro($ctx, 'casa-g', 'Casa G', 'sec-fin', 'fin@multi.test', 'clave1', 'Fin Sec');

        $login = $this->iniciarSesion($ctx)->ejecutar('fin@multi.test', 'mal-clave');
        self::assertSame('fallo', $login->estado);
        self::assertFalse($login->desconocido());
        self::assertSame('Alias o contraseña incorrectos', $login->mensaje);
    }

    /** @return array{pdo: \PDO, identidades: IdentidadRepository, catalogo: CatalogoDocumentosLegales, aceptaciones: PdoAceptacionLegalRepository} */
    private function contexto(string $sufijo): array
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia(self::DB_NAME . '_' . $sufijo);
        (new SchemaInstaller($pdo))->install();

        return [
            'pdo' => $pdo,
            'identidades' => ServiciosAcceso::identidades($pdo),
            'catalogo' => CatalogoDocumentosLegales::porDefecto(),
            'aceptaciones' => new PdoAceptacionLegalRepository($pdo),
        ];
    }

    /** @param array{pdo: \PDO, identidades: IdentidadRepository, catalogo: CatalogoDocumentosLegales, aceptaciones: PdoAceptacionLegalRepository} $ctx */
    private function registrarPersona(array $ctx, string $alias, string $email, string $clave, string $nombre): array
    {
        $out = ServiciosAcceso::registrarUsuario($ctx['pdo'])
            ->ejecutar($alias, $email, $clave, $clave, $nombre, true);
        $this->confirmarRegistro($ctx, $out['token_verificacion']);

        return $out;
    }

    /**
     * @param array{pdo: \PDO, identidades: IdentidadRepository, catalogo: CatalogoDocumentosLegales, aceptaciones: PdoAceptacionLegalRepository} $ctx
     *
     * @return array{identidad: \src\acceso\domain\entity\Identidad, centro: \src\ambito\domain\entity\Centro, token_verificacion: string, enviar_correo: bool}
     */
    private function registrarCentro(
        array $ctx,
        string $codigo,
        string $nombreCentro,
        string $alias,
        string $email,
        string $clave,
        string $nombreSec,
    ): array {
        $out = (new RegistrarCentro(
            ServiciosAcceso::crearCentro($ctx['pdo']),
            $ctx['identidades'],
        ))->ejecutar(
            $codigo,
            $nombreCentro,
            'n',
            $alias,
            $email,
            $clave,
            $clave,
            $nombreSec,
            true,
        );
        if ($out['token_verificacion'] !== '') {
            $this->confirmarRegistro($ctx, $out['token_verificacion']);
        }

        return $out;
    }

    /** @param array{pdo: \PDO, identidades: IdentidadRepository, catalogo: CatalogoDocumentosLegales, aceptaciones: PdoAceptacionLegalRepository} $ctx */
    private function confirmarRegistro(array $ctx, string $token): void
    {
        if ($token === '') {
            return;
        }
        ServiciosAcceso::confirmarEmailRegistro($ctx['pdo'], $token, $ctx['identidades'], $ctx['catalogo']);
    }

    /** @param array{pdo: \PDO, identidades: IdentidadRepository} $ctx */
    private function iniciarSesion(array $ctx): IniciarSesion
    {
        $identidades = $ctx['identidades'];

        return new IniciarSesion(
            $identidades,
            new ResolverPersonaActiva($identidades),
            ServiciosAcceso::libroPersonal($ctx['pdo'], $identidades),
            new EtiquetaCuentaIdentidad($identidades),
        );
    }
}
