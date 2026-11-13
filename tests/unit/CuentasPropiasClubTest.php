<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\ambito\domain\entity\Cuenta;
use src\conceptos\domain\entity\Concepto;
use src\plan\domain\services\CuentasPropiasClub;

final class CuentasPropiasClubTest extends TestCase
{
    public function testElCatalogoSonLasCuentasDelCentroYNoElModelo(): void
    {
        $plan = [
            (new Concepto('61', 'G', 'Despeses generals', '', 'grupo-gasto', 20))->toArray(),
            (new Concepto('61.3', 'G', 'Neteja', '', 'gasto', 23))->toArray(),
            (new Concepto('61.6', 'G', 'Taxes', '', 'gasto', 26))->toArray(),
            (new Concepto('70', 'G', 'Ingressos del club', '', 'grupo-ingreso', 30))->toArray(),
        ];
        $cuentas = [
            $this->cuenta('61', 'Despeses generals', 'gasto', imputable: false),
            // Renombrada en el centro: manda su nombre, no el del modelo.
            $this->cuenta('61.6', 'Taxes i impostos', 'gasto'),
            $this->cuenta('61.10', 'Varis', 'gasto'),
            $this->cuenta('61.7', 'Alimentació', 'gasto'),
            $this->cuenta('70', 'Ingressos del club', 'ingreso', imputable: false),
            $this->cuenta('BANCO.1/G', 'Banc', 'tesoreria'),
            $this->cuenta('BANCO.PEND.gasto', 'Por categorizar (gasto)', 'gasto'),
            $this->cuenta('OTRA.ingreso', 'Otra contabilidad', 'ingreso'),
            $this->cuenta('61.8', 'Baixa', 'gasto', activo: false),
            $this->cuenta('61.9', 'De persona', 'gasto', personaId: 4),
        ];

        $out = CuentasPropiasClub::fusionar($plan, $cuentas);

        // 61.3 solo está en el modelo (el centro le cambió el código): ya no sale.
        self::assertSame(['61', '61.6', '61.7', '61.10', '70'], array_column($out, 'codigo'));
        self::assertSame('grupo-gasto', $out[0]['naturaleza']);
        self::assertSame('61.6 Taxes i impostos', $out[1]['etiqueta']);
        self::assertSame('gasto', $out[2]['naturaleza']);
    }

    public function testElModeloDelPlanNoIntervieneAunqueElCentroNoTengaCuentas(): void
    {
        $plan = [(new Concepto('61.6', 'G', 'Taxes', '', 'gasto', 26))->toArray()];

        $out = CuentasPropiasClub::fusionar($plan, [$this->cuenta('BANCO.1/G', 'Banc', 'tesoreria')]);

        self::assertSame([], $out);
    }

    public function testElGrupoPropioNoEsImputable(): void
    {
        $out = CuentasPropiasClub::fusionar([], [$this->cuenta('95', 'Nou grup', 'ingreso', imputable: false)]);

        self::assertSame('grupo-ingreso', $out[0]['naturaleza']);
    }

    private function cuenta(
        string $codigo,
        string $nombre,
        string $tipo,
        bool $imputable = true,
        bool $activo = true,
        ?int $personaId = null,
    ): Cuenta {
        return new Cuenta(null, 5, $personaId, null, null, 'G', $codigo, $nombre, $nombre, $tipo, 'deudora', $codigo, $imputable, 100, $activo);
    }
}
