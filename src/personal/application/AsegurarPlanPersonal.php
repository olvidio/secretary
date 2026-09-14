<?php

declare(strict_types=1);

namespace src\personal\application;

use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Cuenta;
use src\personal\domain\services\CatalogoMaestroPersonal;

final class AsegurarPlanPersonal
{
    public const CODIGO_PENDIENTE_GASTO = '22.pendiente';
    public const CODIGO_PENDIENTE_INGRESO = '113.pendiente';
    public const CODIGO_OTRA_GASTO = 'OTRA.gasto';
    public const CODIGO_OTRA_INGRESO = 'OTRA.ingreso';
    public const CODIGO_MAESTRO_OTRA = 'OTRA';

    public function __construct(private readonly CuentaRepository $cuentas)
    {
    }

    public function ejecutar(int $centroId, int $personaId): void
    {
        foreach (CatalogoMaestroPersonal::cuentas() as $c) {
            if ($this->cuentas->buscar($centroId, $personaId, 'X', $c['codigo']) !== null) {
                continue;
            }
            $this->cuentas->guardar(new Cuenta(
                null,
                $centroId,
                $personaId,
                null,
                null,
                'X',
                $c['codigo'],
                $c['nombre'],
                $c['descripcion'],
                $c['tipo'],
                $c['naturaleza'],
                $c['codigo'],
                true,
                $c['orden'],
            ));
        }
        $this->asegurarTesoreria($centroId, $personaId, 'CAJA', 'Caja', 1);
        $this->asegurarTesoreria($centroId, $personaId, 'BANCO', 'Banco', 2);
        if ($this->cuentas->buscar($centroId, $personaId, 'X', 'PUENTE.PERIODIFICACION') === null) {
            $this->cuentas->guardar(new Cuenta(
                null,
                $centroId,
                $personaId,
                null,
                null,
                'X',
                'PUENTE.PERIODIFICACION',
                'Periodificación',
                'Contrapartida de imputación a período distinto de la tesorería (D13)',
                'puente',
                'deudora',
                'PERIODIFICACION',
                true,
                999,
            ));
        }
        $this->asegurarPendiente(
            $centroId,
            $personaId,
            self::CODIGO_PENDIENTE_GASTO,
            '22',
            'Por categorizar (gasto)',
        );
        $this->asegurarPendiente(
            $centroId,
            $personaId,
            self::CODIGO_PENDIENTE_INGRESO,
            '113',
            'Por categorizar (ingreso)',
        );
        $this->asegurarOtra(
            $centroId,
            $personaId,
            self::CODIGO_OTRA_GASTO,
            'gasto',
            'deudora',
        );
        $this->asegurarOtra(
            $centroId,
            $personaId,
            self::CODIGO_OTRA_INGRESO,
            'ingreso',
            'acreedora',
        );
    }

    public function pendienteDe(int $centroId, int $personaId, string $sentido): Cuenta
    {
        $this->ejecutar($centroId, $personaId);
        $codigo = $sentido === 'ingreso' ? self::CODIGO_PENDIENTE_INGRESO : self::CODIGO_PENDIENTE_GASTO;
        $cuenta = $this->cuentas->buscar($centroId, $personaId, 'X', $codigo);
        if ($cuenta === null || $cuenta->id === null) {
            throw new \InvalidArgumentException('No hay cuenta para movimientos por categorizar');
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

    public function otraDe(int $centroId, int $personaId, string $sentido): Cuenta
    {
        $this->ejecutar($centroId, $personaId);
        $codigo = $sentido === 'ingreso' ? self::CODIGO_OTRA_INGRESO : self::CODIGO_OTRA_GASTO;
        $cuenta = $this->cuentas->buscar($centroId, $personaId, 'X', $codigo);
        if ($cuenta === null || $cuenta->id === null) {
            throw new \InvalidArgumentException('No hay cuenta de otra contabilidad');
        }

        return $cuenta;
    }

    private function asegurarPendiente(
        int $centroId,
        int $personaId,
        string $codigo,
        string $maestro,
        string $nombre,
    ): void {
        if ($this->cuentas->buscar($centroId, $personaId, 'X', $codigo) !== null) {
            return;
        }
        $padre = $this->cuentas->buscar($centroId, $personaId, 'X', $maestro);
        $this->cuentas->guardar(new Cuenta(
            null,
            $centroId,
            $personaId,
            null,
            $padre?->id,
            'X',
            $codigo,
            $nombre,
            'Movimientos de banco pendientes de categorizar',
            $padre !== null ? $padre->tipo : 'gasto',
            $padre !== null ? $padre->naturaleza : 'deudora',
            $maestro,
            true,
            $padre !== null ? $padre->orden : 0,
        ));
    }

    private function asegurarOtra(
        int $centroId,
        int $personaId,
        string $codigo,
        string $tipo,
        string $naturaleza,
    ): void {
        if ($this->cuentas->buscar($centroId, $personaId, 'X', $codigo) !== null) {
            return;
        }
        $this->cuentas->guardar(new Cuenta(
            null,
            $centroId,
            $personaId,
            null,
            null,
            'X',
            $codigo,
            'Otra contabilidad',
            'Movimientos de banco que no entran en el plan personal; siguen afectando al saldo de tesorería',
            $tipo,
            $naturaleza,
            self::CODIGO_MAESTRO_OTRA,
            true,
            1000,
        ));
    }

    private function asegurarTesoreria(int $centroId, int $personaId, string $maestro, string $nombre, int $orden): void
    {
        if ($this->cuentas->buscar($centroId, $personaId, 'X', $maestro) !== null) {
            return;
        }
        $this->cuentas->guardar(new Cuenta(
            null,
            $centroId,
            $personaId,
            null,
            null,
            'X',
            $maestro,
            $nombre,
            'Tesorería propia del nivel 1 (no es la caja/banco del centro)',
            'tesoreria',
            'deudora',
            $maestro,
            true,
            $orden,
        ));
    }
}
