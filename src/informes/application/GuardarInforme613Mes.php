<?php

declare(strict_types=1);

namespace src\informes\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\informes\domain\contracts\Informe613MesRepository;
use src\informes\domain\entity\Informe613Mes;

final class GuardarInforme613Mes
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly Informe613MesRepository $informes613,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(string $cuenta, array $datos): Informe613Mes
    {
        $cuenta = strtoupper(trim($cuenta));
        if (!in_array($cuenta, ['P', 'G'], true)) {
            throw new InvalidArgumentException('Cuenta: P o G');
        }

        $fechaRaw = trim((string) ($datos['fecha_cierre'] ?? ''));
        if ($fechaRaw === '') {
            throw new InvalidArgumentException('Falta fecha_cierre');
        }
        $fechaCierre = $this->fecha($fechaRaw);

        $ctx = $this->ambito->ejecutar();
        $actual = $this->informes613->buscar($ctx->ejercicioId, $fechaCierre, $cuenta);

        $obs = $actual?->observaciones;
        if (array_key_exists('observaciones', $datos)) {
            $obs = $this->texto($datos['observaciones']);
        } elseif ($cuenta === 'P' && array_key_exists('observaciones_613_p', $datos)) {
            $obs = $this->texto($datos['observaciones_613_p']);
        } elseif ($cuenta === 'G' && array_key_exists('observaciones_613_g', $datos)) {
            $obs = $this->texto($datos['observaciones_613_g']);
        }

        $informe = new Informe613Mes(
            $ctx->ejercicioId,
            $fechaCierre,
            $cuenta,
            $obs,
            $cuenta === 'P' && array_key_exists('saldo_cc_personales', $datos)
                ? $this->texto($datos['saldo_cc_personales'])
                : $actual?->saldoCcPersonales,
            $cuenta === 'G' && array_key_exists('media_cocina_mes', $datos)
                ? $this->texto($datos['media_cocina_mes'])
                : $actual?->mediaCocinaMes,
            $cuenta === 'G' && array_key_exists('media_cocina_acum', $datos)
                ? $this->texto($datos['media_cocina_acum'])
                : $actual?->mediaCocinaAcum,
            $cuenta === 'G' && array_key_exists('dinero_arqueo_caja', $datos)
                ? $this->texto($datos['dinero_arqueo_caja'])
                : $actual?->dineroArqueoCaja,
            $cuenta === 'G' && array_key_exists('dinero_arqueo_banco', $datos)
                ? $this->texto($datos['dinero_arqueo_banco'])
                : $actual?->dineroArqueoBanco,
            $actual?->enviado ?? false,
            $actual?->enviadoEn,
        );

        $this->informes613->guardar($informe);

        return $informe;
    }

    private function fecha(string $raw): DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return new DateTimeImmutable($raw);
        }
        $dt = DateTimeImmutable::createFromFormat('!d/m/Y', $raw);
        if ($dt === false) {
            throw new InvalidArgumentException('Fecha de cierre inválida');
        }

        return $dt;
    }

    private function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $s = trim((string) $valor);

        return $s === '' ? null : $s;
    }
}
