<?php

declare(strict_types=1);

namespace src\apuntes\application;

use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Cuenta;

final class AsegurarPendienteBancoCentro
{
    public const CODIGO_PENDIENTE_GASTO = 'BANCO.PEND.gasto';
    public const CODIGO_PENDIENTE_INGRESO = 'BANCO.PEND.ingreso';
    public const CODIGO_OTRA_GASTO = 'OTRA.gasto';
    public const CODIGO_OTRA_INGRESO = 'OTRA.ingreso';
    public const CODIGO_MAESTRO = 'BANCO.PEND';
    public const CODIGO_MAESTRO_OTRA = 'OTRA';

    public function __construct(private readonly CuentaRepository $cuentas)
    {
    }

    public function ejecutar(int $centroId): void
    {
        $this->asegurar($centroId, self::CODIGO_PENDIENTE_GASTO, 'gasto', 'deudora', _('Por categorizar (gasto)'));
        $this->asegurar($centroId, self::CODIGO_PENDIENTE_INGRESO, 'ingreso', 'acreedora', _('Por categorizar (ingreso)'));
        $this->asegurarOtra($centroId, self::CODIGO_OTRA_GASTO, 'gasto', 'deudora');
        $this->asegurarOtra($centroId, self::CODIGO_OTRA_INGRESO, 'ingreso', 'acreedora');
    }

    public function pendienteDe(int $centroId, string $sentido): Cuenta
    {
        $this->ejecutar($centroId);
        $codigo = $sentido === 'ingreso' ? self::CODIGO_PENDIENTE_INGRESO : self::CODIGO_PENDIENTE_GASTO;
        $cuenta = $this->cuentas->buscar($centroId, null, 'G', $codigo);
        if ($cuenta === null || $cuenta->id === null) {
            throw new InvalidArgumentException(_('No hay cuenta para movimientos por categorizar'));
        }

        return $cuenta;
    }

    public static function esPendiente(string $codigo): bool
    {
        return in_array($codigo, [self::CODIGO_PENDIENTE_GASTO, self::CODIGO_PENDIENTE_INGRESO], true);
    }

    public static function esOtra(string $codigo): bool
    {
        return in_array($codigo, [self::CODIGO_OTRA_GASTO, self::CODIGO_OTRA_INGRESO], true);
    }

    public function otraDe(int $centroId, string $sentido): Cuenta
    {
        $this->ejecutar($centroId);
        $codigo = $sentido === 'ingreso' ? self::CODIGO_OTRA_INGRESO : self::CODIGO_OTRA_GASTO;
        $cuenta = $this->cuentas->buscar($centroId, null, 'G', $codigo);
        if ($cuenta === null || $cuenta->id === null) {
            throw new InvalidArgumentException(_('No hay cuenta de otra contabilidad'));
        }

        return $cuenta;
    }

    private function asegurar(int $centroId, string $codigo, string $tipo, string $naturaleza, string $nombre): void
    {
        if ($this->cuentas->buscar($centroId, null, 'G', $codigo) !== null) {
            return;
        }
        $this->cuentas->guardar(new Cuenta(
            null,
            $centroId,
            null,
            null,
            null,
            'G',
            $codigo,
            $nombre,
            _('Movimientos de banco del centro pendientes de categorizar'),
            $tipo,
            $naturaleza,
            self::CODIGO_MAESTRO,
            true,
            900,
        ));
    }

    private function asegurarOtra(int $centroId, string $codigo, string $tipo, string $naturaleza): void
    {
        if ($this->cuentas->buscar($centroId, null, 'G', $codigo) !== null) {
            return;
        }
        $this->cuentas->guardar(new Cuenta(
            null,
            $centroId,
            null,
            null,
            null,
            'G',
            $codigo,
            _('Otra contabilidad'),
            _('Movimientos de banco del centro que no entran en el plan general; siguen afectando al saldo de tesorería'),
            $tipo,
            $naturaleza,
            self::CODIGO_MAESTRO_OTRA,
            true,
            910,
        ));
    }
}
