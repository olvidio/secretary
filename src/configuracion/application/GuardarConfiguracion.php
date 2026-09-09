<?php

declare(strict_types=1);

namespace src\configuracion\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\configuracion\domain\entity\ConfiguracionCentro;

final class GuardarConfiguracion
{
    public function __construct(private readonly ConfiguracionRepository $repo)
    {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos): ConfiguracionCentro
    {
        $actual = $this->repo->get();
        $anio = (int) ($datos['anio'] ?? $actual->anio);
        $modo = (string) ($datos['modo_ejercicio'] ?? $actual->modoEjercicio);
        if (!in_array($modo, ['Año', 'Curso'], true)) {
            throw new InvalidArgumentException('Modo: Año o Curso');
        }
        $tipo = (string) ($datos['tipo_cierre'] ?? $actual->tipoCierre);
        if (!in_array($tipo, ['vivienda', 'necesidades'], true)) {
            throw new InvalidArgumentException('Tipo de cierre: vivienda o necesidades');
        }
        $ini = $this->fecha((string) ($datos['fecha_inicio'] ?? $actual->fechaInicio->format('Y-m-d')));
        $cie = $this->fecha((string) ($datos['fecha_cierre'] ?? $actual->fechaCierre->format('Y-m-d')));
        $cfg = new ConfiguracionCentro(
            trim((string) ($datos['centro'] ?? $actual->centro)),
            $anio,
            $modo,
            $ini,
            $cie,
            $tipo,
            isset($datos['num_residentes']) && $datos['num_residentes'] !== ''
                ? (int) $datos['num_residentes'] : $actual->numResidentes,
            $actual->version,
            $datos['observaciones_613_p'] ?? $actual->observaciones613P,
            $datos['observaciones_613_g'] ?? $actual->observaciones613G,
            $datos['media_cocina_mes'] ?? $actual->mediaCocinaMes,
            $datos['media_cocina_acum'] ?? $actual->mediaCocinaAcum,
            $datos['saldo_cc_personales'] ?? $actual->saldoCcPersonales,
        );
        $this->repo->guardar($cfg);

        return $cfg;
    }

    private function fecha(string $raw): DateTimeImmutable
    {
        $raw = trim($raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return new DateTimeImmutable($raw);
        }
        $dt = DateTimeImmutable::createFromFormat('!d/m/Y', $raw);
        if ($dt === false) {
            throw new InvalidArgumentException('Fecha inválida');
        }

        return $dt;
    }
}
