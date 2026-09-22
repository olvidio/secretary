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
use src\legal\domain\services\DatosOperador;
use src\legal\infrastructure\persistence\PdoAceptacionLegalRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;
use Tests\Soporte\ServiciosAcceso;

final class CorreoMulticuentaTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_correo_multi';

    public function testMismoCorreoPersonaYCentroDistintosAlias(): void
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
        self::assertNotSame($persona['identidad']->id, $centro['identidad']->id);
        self::assertTrue($ctx['identidades']->esCuentaPersonal((int) $persona['identidad']->id));
        self::assertFalse($ctx['identidades']->esCuentaPersonal((int) $centro['identidad']->id));
        self::assertSame(
            (int) $persona['identidad']->id,
            (int) $ctx['identidades']->cuentaPersonalPorEmail('ana@multi.test')?->id,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cuenta personal');
        $this->registrarPersona($ctx, 'ana2', 'ana@multi.test', 'secret3', 'Ana');
    }

    public function testLoginPorCorreoPideElegirCuenta(): void
    {
        $ctx = $this->contexto('login_elegir');
        $this->registrarPersona($ctx, 'bob', 'bob@multi.test', 'clave1', 'Bob');
        $this->registrarCentro($ctx, 'casa-b', 'Casa B', 'sec-casa-b', 'bob@multi.test', 'clave1', 'Bob Sec');

        $login = $this->iniciarSesion($ctx)->ejecutar('bob@multi.test', 'clave1');
        self::assertSame('pendiente_elegir_cuenta', $login->estado);
        self::assertCount(2, $login->cuentas);
        foreach ($login->cuentas as $cuenta) {
            self::assertNotSame('', $cuenta['etiqueta']);
        }
    }

    public function testDosCentrosMismoCorreoPidenElegirCuenta(): void
    {
        $ctx = $this->contexto('dos_centros');
        $this->registrarCentro($ctx, 'casa-1', 'Casa Uno', 'sec-uno', 'sec@multi.test', 'clave1', 'Sec Uno');
        $this->registrarCentro($ctx, 'casa-2', 'Casa Dos', 'sec-dos', 'sec@multi.test', 'clave1', 'Sec Dos');

        $login = $this->iniciarSesion($ctx)->ejecutar('sec@multi.test', 'clave1');
        self::assertSame('pendiente_elegir_cuenta', $login->estado);
        self::assertCount(2, $login->cuentas);
        self::assertNull($ctx['identidades']->cuentaPersonalPorEmail('sec@multi.test'));
    }

    public function testLoginPorCorreoConClaveUnicaEntraSinElegir(): void
    {
        $ctx = $this->contexto('clave_unica');
        $persona = $this->registrarPersona($ctx, 'car', 'car@multi.test', 'clave-p', 'Car');
        $this->registrarCentro($ctx, 'casa-c', 'Casa C', 'sec-casa-c', 'car@multi.test', 'clave-s', 'Car Sec');

        $comoPersona = $this->iniciarSesion($ctx)->ejecutar('car@multi.test', 'clave-p');
        self::assertSame('autenticado', $comoPersona->estado);
        self::assertSame('persona', $comoPersona->nivel);
        self::assertSame($persona['identidad']->id, $comoPersona->identidadId);

        $comoCentro = $this->iniciarSesion($ctx)->ejecutar('car@multi.test', 'clave-s');
        self::assertSame('pendiente_activar', $comoCentro->estado);
        self::assertSame('centro', $comoCentro->nivel);
    }

    public function testLoginPorAliasNoPideElegirCuenta(): void
    {
        $ctx = $this->contexto('por_alias');
        $this->registrarPersona($ctx, 'dan', 'dan@multi.test', 'clave1', 'Dan');
        $centro = $this->registrarCentro($ctx, 'casa-d', 'Casa D', 'sec-dan', 'dan@multi.test', 'clave1', 'Dan Sec');

        $login = $this->iniciarSesion($ctx)->ejecutar('sec-dan', 'clave1');
        self::assertSame('pendiente_activar', $login->estado);
        self::assertSame('centro', $login->nivel);
        self::assertSame($centro['identidad']->id, $login->identidadId);
    }

    public function testContinuarConIdentidadTrasElegir(): void
    {
        $ctx = $this->contexto('continuar');
        $persona = $this->registrarPersona($ctx, 'eva', 'eva@multi.test', 'clave1', 'Eva');
        $centro = $this->registrarCentro($ctx, 'casa-e', 'Casa E', 'sec-eva', 'eva@multi.test', 'clave1', 'Eva Sec');
        $iniciar = $this->iniciarSesion($ctx);

        $pendiente = $iniciar->ejecutar('eva@multi.test', 'clave1');
        self::assertSame('pendiente_elegir_cuenta', $pendiente->estado);

        $idPersona = (int) $persona['identidad']->id;
        $idCentro = (int) $centro['identidad']->id;
        $comoPersona = $iniciar->continuarConIdentidad($ctx['identidades']->porId($idPersona));
        self::assertSame('autenticado', $comoPersona->estado);
        self::assertSame('persona', $comoPersona->nivel);

        $comoCentro = $iniciar->continuarConIdentidad($ctx['identidades']->porId($idCentro));
        self::assertSame('pendiente_activar', $comoCentro->estado);
        self::assertSame('centro', $comoCentro->nivel);
    }

    public function testClaveIncorrectaConVariasCuentasNoInvitaARegistrarse(): void
    {
        $ctx = $this->contexto('fallo_multi');
        $this->registrarPersona($ctx, 'fin', 'fin@multi.test', 'clave1', 'Fin');
        $this->registrarCentro($ctx, 'casa-f', 'Casa F', 'sec-fin', 'fin@multi.test', 'clave1', 'Fin Sec');

        $login = $this->iniciarSesion($ctx)->ejecutar('fin@multi.test', 'mal-clave');
        self::assertSame('fallo', $login->estado);
        self::assertFalse($login->desconocido());
        self::assertSame('Usuario o contraseña incorrectos', $login->mensaje);
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
