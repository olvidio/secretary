<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\ambito\domain\entity\Cuenta;
use src\apuntes\domain\entity\Apunte;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\asientos\domain\exceptions\AsientoDescuadrado;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\personas\domain\entity\Persona;
use src\shared\domain\value_objects\Dinero;

final class TraductorApuntesAAsientosTest extends TestCase
{
    private const CENTRO_ID = 1;
    private const EJERCICIO_ID = 10;

    private TraductorApuntesAAsientos $traductor;

    /** @var array<string, Cuenta> */
    private array $cuentas = [];

    protected function setUp(): void
    {
        $this->traductor = new TraductorApuntesAAsientos();
        $this->cuentas = $this->sembrarCuentas();
    }

    public function testIngresoP111OrigenC(): void
    {
        $asientos = $this->traducir([
            $this->apunte('P', 'C', 'xx', '111', '100.00'),
        ]);

        self::assertCount(1, $asientos);
        $movs = $asientos[0]->movimientos;
        self::assertSame($this->id('CAJA.P'), $movs[0]->cuentaId);
        self::assertSame(10000, $movs[0]->debeCents);
        self::assertSame($this->id('111.P'), $movs[1]->cuentaId);
        self::assertSame(10000, $movs[1]->haberCents);
    }

    public function testGastoP21OrigenA(): void
    {
        $asientos = $this->traducir([
            $this->apunte('P', 'A', 'xx', '21', '50.00'),
        ]);

        self::assertCount(1, $asientos);
        $movs = $asientos[0]->movimientos;
        self::assertSame($this->id('21.P'), $movs[0]->cuentaId);
        self::assertSame(5000, $movs[0]->debeCents);
        self::assertSame($this->id('CC.XX'), $movs[1]->cuentaId);
        self::assertSame(5000, $movs[1]->haberCents);
        self::assertSame(99, $movs[1]->personaId);
    }

    public function testIngresoG11OrigenA(): void
    {
        $asientos = $this->traducir([
            $this->apunte('G', 'A', null, '11', '200.00'),
        ]);

        self::assertCount(1, $asientos);
        $movs = $asientos[0]->movimientos;
        self::assertSame($this->id('DEUDORES.VIV'), $movs[0]->cuentaId);
        self::assertSame(20000, $movs[0]->debeCents);
        self::assertSame($this->id('11.G'), $movs[1]->cuentaId);
        self::assertSame(20000, $movs[1]->haberCents);
    }

    public function testGastoG201OrigenB(): void
    {
        $asientos = $this->traducir([
            $this->apunte('G', 'B', null, '201', '75.50'),
        ]);

        self::assertCount(1, $asientos);
        $movs = $asientos[0]->movimientos;
        self::assertSame($this->id('201.G'), $movs[0]->cuentaId);
        self::assertSame(7550, $movs[0]->debeCents);
        self::assertSame($this->id('BANCO.G'), $movs[1]->cuentaId);
        self::assertSame(7550, $movs[1]->haberCents);
    }

    public function testG32OrigenCesApertura(): void
    {
        $asientos = $this->traducir([
            $this->apunte('G', 'C', null, '32', '1000.00'),
        ]);

        self::assertCount(1, $asientos);
        self::assertSame('apertura', $asientos[0]->tipo);
        $movs = $asientos[0]->movimientos;
        self::assertSame($this->id('CAJA.G'), $movs[0]->cuentaId);
        self::assertSame(100000, $movs[0]->debeCents);
        self::assertSame($this->id('32.G'), $movs[1]->cuentaId);
        self::assertSame(100000, $movs[1]->haberCents);
    }

    public function testG41DosApuntesBCUnAsiento(): void
    {
        $resultado = $this->traductor->traducir(
            self::EJERCICIO_ID,
            [
                $this->apunte('G', 'B', null, '41', '500.00', 1, 2),
                $this->apunte('G', 'C', null, '41', '500.00', 2, 1),
            ],
            fn () => null,
            fn (string $libro, string $codigo) => $this->cuentas["$codigo.$libro"] ?? $this->cuentas[$codigo],
            fn (string $libro, string $maestro) => $this->cuentas["$maestro.$libro"],
            fn () => $this->cuentas['CC.XX'],
            fn () => $this->cuentas['DEUDORES.VIV'],
        );

        self::assertCount(1, $resultado['asientos']);
        self::assertSame(1, $resultado['traspasos_fusionados']);
        self::assertSame('traspaso', $resultado['asientos'][0]->tipo);
        $movs = $resultado['asientos'][0]->movimientos;
        self::assertSame($this->id('CAJA.G'), $movs[0]->cuentaId);
        self::assertSame(50000, $movs[0]->debeCents);
        self::assertSame($this->id('BANCO.G'), $movs[1]->cuentaId);
        self::assertSame(50000, $movs[1]->haberCents);
    }

    public function testConcepto9CeroAsientos(): void
    {
        $resultado = $this->traductor->traducir(
            self::EJERCICIO_ID,
            [$this->apunte('P', 'A', 'xx', '9', '300.00')],
            fn () => $this->persona('xx'),
            fn (string $libro, string $codigo) => $this->cuentas["$codigo.$libro"],
            fn (string $libro, string $maestro) => $this->cuentas["$maestro.$libro"],
            fn () => $this->cuentas['CC.XX'],
            fn () => $this->cuentas['DEUDORES.VIV'],
        );

        self::assertSame([], $resultado['asientos']);
        self::assertSame(1, $resultado['omitidos_concepto_9']);
    }

    public function testAsientoDescuadradoNoSePuedeConstruir(): void
    {
        $this->expectException(AsientoDescuadrado::class);
        new Asiento(
            null,
            self::EJERCICIO_ID,
            'P',
            null,
            new DateTimeImmutable('2026-01-01'),
            null,
            'normal',
            'import',
            null,
            [
                new Movimiento(null, 1, 1, null, 100, 0),
                new Movimiento(null, 2, 2, null, 0, 50),
            ],
        );
    }

    public function testMovimientoInvalidoAmbosCero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Movimiento(null, 1, 1, null, 0, 0);
    }

    /**
     * @param list<Apunte> $apuntes
     * @return list<Asiento>
     */
    private function traducir(array $apuntes): array
    {
        return $this->traductor->traducir(
            self::EJERCICIO_ID,
            $apuntes,
            fn (string $ini) => $this->persona($ini),
            fn (string $libro, string $codigo) => $this->cuentas["$codigo.$libro"],
            fn (string $libro, string $maestro) => $this->cuentas["$maestro.$libro"],
            fn () => $this->cuentas['CC.XX'],
            fn () => $this->cuentas['DEUDORES.VIV'],
        )['asientos'];
    }

    private function apunte(
        string $libro,
        string $origen,
        ?string $iniciales,
        string $concepto,
        string $cantidad,
        ?int $id = null,
        ?int $parId = null,
    ): Apunte {
        return new Apunte(
            $id,
            new DateTimeImmutable('2026-03-15'),
            $libro,
            $origen,
            $iniciales,
            $concepto,
            'obs test',
            new Dinero($cantidad),
            false,
            $parId,
        );
    }

    private function persona(string $iniciales): Persona
    {
        return new Persona(99, 'Test', 'Persona', strtoupper($iniciales), null, null, null, null, null, 0);
    }

    private function id(string $clave): int
    {
        return $this->cuentas[$clave]->id ?? 0;
    }

    /** @return array<string, Cuenta> */
    private function sembrarCuentas(): array
    {
        $n = 1;
        $mk = static function (
            string $clave,
            string $libro,
            string $codigo,
            string $tipo,
            ?int $personaId = null,
        ) use (&$n): Cuenta {
            return new Cuenta(
                $n++,
                self::CENTRO_ID,
                $personaId,
                null,
                null,
                $libro,
                $codigo,
                $codigo,
                '',
                $tipo,
                $tipo === 'ingreso' || $tipo === 'patrimonio' ? 'acreedora' : 'deudora',
                $codigo,
                true,
            );
        };

        return [
            'CAJA.P' => $mk('CAJA.P', 'P', 'CAJA.1/P', 'tesoreria'),
            'BANCO.P' => $mk('BANCO.P', 'P', 'BANCO.1/P', 'tesoreria'),
            'CAJA.G' => $mk('CAJA.G', 'G', 'CAJA.1/G', 'tesoreria'),
            'BANCO.G' => $mk('BANCO.G', 'G', 'BANCO.1/G', 'tesoreria'),
            '111.P' => $mk('111.P', 'P', '111', 'ingreso'),
            '21.P' => $mk('21.P', 'P', '21', 'gasto'),
            '11.G' => $mk('11.G', 'G', '11', 'ingreso'),
            '201.G' => $mk('201.G', 'G', '201', 'gasto'),
            '32.G' => $mk('32.G', 'G', '32', 'patrimonio'),
            '41.G' => $mk('41.G', 'G', '41', 'puente'),
            '42.G' => $mk('42.G', 'G', '42', 'puente'),
            'CC.XX' => new Cuenta(
                $n++,
                self::CENTRO_ID,
                99,
                null,
                null,
                'P',
                'CC.XX',
                'CC.XX',
                '',
                'personal',
                'deudora',
                '9',
                true,
            ),
            'DEUDORES.VIV' => new Cuenta(
                $n++,
                self::CENTRO_ID,
                null,
                null,
                null,
                'G',
                'DEUDORES.VIV',
                'DEUDORES.VIV',
                '',
                'personal',
                'deudora',
                'DEUDORES-VIV',
                true,
            ),
        ];
    }
}
