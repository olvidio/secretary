<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\RegistrarUsuario;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\ambito\application\AsegurarCuentaCorrientePersona;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Centro;
use src\personal\application\AsegurarPlanPersonal;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;

final class RegistrarUsuarioTest extends TestCase
{
    public function testRechazaAliasInvalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->caso()->ejecutar('1malo', 'a@x.local', 'secret1', 'secret1');
    }

    public function testRechazaCorreoInvalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->caso()->ejecutar('nuevo', 'no-es-correo', 'secret1', 'secret1');
    }

    public function testRechazaSiLasClavesNoCoinciden(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->caso()->ejecutar('nuevo', 'a@x.local', 'secret1', 'secret2');
    }

    public function testRechazaSiYaExisteElUsuario(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $existente = new Identidad(1, 'viejo@x.local', 'h', 'Viejo', true, 0, null, null, 'nuevo');
        $this->caso($existente)->ejecutar('nuevo', 'a@x.local', 'secret1', 'secret1');
    }

    public function testRechazaSiNoHayCentro(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->caso(null, [])->ejecutar('nuevo', 'a@x.local', 'secret1', 'secret1');
    }

    public function testCreaIdentidadYPersonaEnElUnicoCentro(): void
    {
        $identidades = $this->createMock(IdentidadRepository::class);
        $personas = $this->createMock(PersonaRepository::class);
        $centros = $this->createStub(CentroRepository::class);

        $centros->method('listar')->willReturn([
            new Centro(3, 'mt', 'Montagut', 'vivienda', 'H16n'),
        ]);
        $identidades->method('porEmailOAlias')->willReturn(null);
        $personas->method('porEmail')->willReturn(null);
        $personas->method('porInicialesDeCentro')->willReturn(null);
        $personas->method('guardar')->willReturnCallback(
            static function (Persona $p): Persona {
                return new Persona(
                    10,
                    $p->nombre,
                    $p->apellidos,
                    $p->iniciales,
                    null,
                    null,
                    null,
                    null,
                    null,
                    0,
                    3,
                    true,
                    $p->email,
                    $p->viviendaAportaGenerales,
                );
            }
        );
        $identidades->method('guardar')->willReturnCallback(
            static function (Identidad $i): Identidad {
                return new Identidad(5, $i->email, $i->passwordHash, $i->nombre, true, 0, null, null, $i->alias);
            }
        );
        $identidades->expects(self::once())->method('vincularPersona')->with(5, 10);
        $identidades->expects(self::once())->method('guardarVerificacionEmail')->with(5, self::isType('string'), self::isInstanceOf(\DateTimeImmutable::class));
        $identidades->method('porId')->willReturnCallback(
            static fn (int $id): ?Identidad => $id === 5
                ? new Identidad(5, 'dani@x.local', 'h', 'Dani', true, 0, null, null, 'dani', null)
                : null,
        );

        $out = $this->servicio($identidades, $personas, $centros)
            ->ejecutar('dani', 'dani@x.local', 'secret1', 'secret1', 'Dani');
        self::assertSame(5, $out['identidad']->id);
        self::assertSame(10, $out['persona_id']);
        self::assertSame('dani', $out['identidad']->alias);
        self::assertNotSame('', $out['token_verificacion']);
    }

    public function testVariosCentrosExigenElegir(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Indique el centro');
        $this->caso(null, [
            new Centro(1, 'a', 'A', 'vivienda', 'H16n'),
            new Centro(2, 'b', 'B', 'vivienda', 'H16n'),
        ])->ejecutar('nuevo', 'a@x.local', 'secret1', 'secret1');
    }

    /**
     * @param list<Centro>|null $centrosListados
     */
    private function caso(?Identidad $existente = null, ?array $centrosListados = null): RegistrarUsuario
    {
        $identidades = $this->createStub(IdentidadRepository::class);
        $identidades->method('porEmailOAlias')->willReturn($existente);
        $personas = $this->createStub(PersonaRepository::class);
        $centros = $this->createStub(CentroRepository::class);
        $centros->method('listar')->willReturn($centrosListados ?? [
            new Centro(1, 'mt', 'Montagut', 'vivienda', 'H16n'),
        ]);

        return $this->servicio($identidades, $personas, $centros);
    }

    private function servicio(
        IdentidadRepository $identidades,
        PersonaRepository $personas,
        CentroRepository $centros,
    ): RegistrarUsuario {
        $cuentas = $this->createStub(CuentaRepository::class);
        $cuentas->method('guardar')->willReturnCallback(
            static fn ($cuenta) => $cuenta
        );

        return new RegistrarUsuario(
            $identidades,
            $personas,
            $centros,
            new AsegurarPlanPersonal($cuentas),
            new AsegurarCuentaCorrientePersona($cuentas),
        );
    }
}
