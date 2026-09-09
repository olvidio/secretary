<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\acceso\application\AutorizarPeticion;
use src\acceso\application\CatalogoRutas;
use src\acceso\domain\contracts\AccesoRutaRepository;
use src\acceso\domain\contracts\IdentidadRepository;

final class AutorizarPeticionTest extends TestCase
{
    private const PAGINA = 'frontend\\shared\\http\\PageController';
    private const APUNTES = 'src\\apuntes\\infrastructure\\http\\ApunteController';

    public function testRutaNoCatalogadaSeDeniega(): void
    {
        $d = $this->autorizar()->ejecutar(
            'src\\noexiste\\HackController',
            'run',
            'GET',
            true,
            1,
            null,
            1,
            'centro',
            true,
        );
        self::assertFalse($d->permitido);
        self::assertSame(401, $d->status);
    }

    public function testSinSesionLaHomeExigeLogin(): void
    {
        $d = $this->autorizar()->ejecutar(
            self::PAGINA,
            'page',
            'GET',
            true,
            null,
            null,
            null,
            '',
            false,
        );
        self::assertFalse($d->permitido);
        self::assertSame('/login', $d->redirect);
    }

    public function testCentroSinTotpNoOpera(): void
    {
        $d = $this->autorizar([1 => false])->ejecutar(
            self::APUNTES,
            'list',
            'GET',
            true,
            1,
            null,
            10,
            'centro',
            true,
        );
        self::assertFalse($d->permitido);
        self::assertSame(401, $d->status);
        self::assertSame('Debe confirmar el segundo factor', $d->error);
    }

    public function testPersonaNoLeeDatosDeCentro(): void
    {
        $d = $this->autorizar([2 => true])->ejecutar(
            self::APUNTES,
            'list',
            'GET',
            true,
            2,
            null,
            null,
            'persona',
            true,
            9,
        );
        self::assertFalse($d->permitido);
        self::assertSame(403, $d->status);
        self::assertSame('Esta área es del centro', $d->error);
    }

    public function testPersonaConSesionEntraEnYo(): void
    {
        $d = $this->autorizar([2 => true])->ejecutar(
            self::PAGINA,
            'yo',
            'GET',
            true,
            2,
            null,
            null,
            'persona',
            false,
            9,
        );
        self::assertTrue($d->permitido);
    }

    public function testPersonaSinPersonaIdNoEntraEnYo(): void
    {
        $d = $this->autorizar([2 => true])->ejecutar(
            self::PAGINA,
            'yo',
            'GET',
            true,
            2,
            null,
            null,
            'persona',
            false,
        );
        self::assertFalse($d->permitido);
    }

    public function testCentroNoEntraEnAreaPersonal(): void
    {
        $d = $this->autorizar([1 => true])->ejecutar(
            self::PAGINA,
            'yo',
            'GET',
            true,
            1,
            null,
            10,
            'centro',
            false,
        );
        self::assertFalse($d->permitido);
        self::assertSame('/', $d->redirect);
    }

    public function testMutacionSinCsrfSeDeniega(): void
    {
        $d = $this->autorizar([1 => true])->ejecutar(
            self::APUNTES,
            'create',
            'POST',
            false,
            1,
            null,
            10,
            'centro',
            true,
        );
        self::assertFalse($d->permitido);
        self::assertSame(403, $d->status);
        self::assertSame('Token CSRF inválido', $d->error);
    }

    public function testLoginPublicoNoExigeSesion(): void
    {
        $d = $this->autorizar()->ejecutar(
            self::PAGINA,
            'login',
            'GET',
            true,
            null,
            null,
            null,
            '',
            false,
        );
        self::assertTrue($d->permitido);
    }

    /** @param array<int, bool> $totpPorId */
    private function autorizar(array $totpPorId = []): AutorizarPeticion
    {
        $rutas = $this->createStub(AccesoRutaRepository::class);
        $rutas->method('ambitoDe')->willReturnCallback(
            static function (string $clase, string $metodo): ?string {
                foreach (CatalogoRutas::todas() as $fila) {
                    if ($fila['clase'] === $clase && $fila['metodo'] === $metodo) {
                        return $fila['ambito'];
                    }
                }

                return null;
            }
        );
        $identidades = $this->createStub(IdentidadRepository::class);
        $identidades->method('totpConfirmado')->willReturnCallback(
            static fn (int $id): bool => $totpPorId[$id] ?? false
        );

        return new AutorizarPeticion($rutas, $identidades);
    }
}
