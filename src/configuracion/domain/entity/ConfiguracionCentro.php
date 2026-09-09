<?php

declare(strict_types=1);

namespace src\configuracion\domain\entity;

use DateTimeImmutable;
use src\shared\domain\value_objects\PeriodoEjercicio;

final class ConfiguracionCentro
{
    public function __construct(
        public readonly string $centro,
        public readonly int $anio,
        public readonly string $modoEjercicio,
        public readonly DateTimeImmutable $fechaInicio,
        public readonly DateTimeImmutable $fechaCierre,
        public readonly string $tipoCierre,
        public readonly ?int $numResidentes,
        public readonly ?string $version,
        public readonly ?string $observaciones613P,
        public readonly ?string $observaciones613G,
        public readonly ?string $mediaCocinaMes,
        public readonly ?string $mediaCocinaAcum,
        public readonly ?string $saldoCcPersonales,
    ) {
    }

    /**
     * Construye el período a partir de los datos legados de `configuracion`
     * (singleton, Fase 0/1): `anio` + `modoEjercicio` siguen siendo la fuente
     * del fin real del ejercicio para ESTA tabla (no se reestructura en la
     * Fase 2), y `fechaCierre` sigue siendo, como siempre, la fecha de corte
     * (nunca el fin del ejercicio; ver PeriodoEjercicio y docs/dev/ambito.md).
     */
    public function periodo(): PeriodoEjercicio
    {
        return new PeriodoEjercicio($this->fechaInicio, $this->finEjercicioLegado(), $this->fechaCierre);
    }

    private function finEjercicioLegado(): DateTimeImmutable
    {
        if ($this->modoEjercicio === 'Curso') {
            return new DateTimeImmutable(sprintf('%d-08-31', $this->anio + 1));
        }

        return new DateTimeImmutable(sprintf('%d-12-31', $this->anio));
    }

    public function withFechaCierre(DateTimeImmutable $fecha): self
    {
        return new self(
            $this->centro,
            $this->anio,
            $this->modoEjercicio,
            $this->fechaInicio,
            $fecha,
            $this->tipoCierre,
            $this->numResidentes,
            $this->version,
            $this->observaciones613P,
            $this->observaciones613G,
            $this->mediaCocinaMes,
            $this->mediaCocinaAcum,
            $this->saldoCcPersonales,
        );
    }

    public static function tipoCierreDesdeCentro(string $centro): string
    {
        $n = mb_strtolower(trim($centro));
        if (str_starts_with($n, 'agd') || str_starts_with($n, 'sss+')) {
            return 'necesidades';
        }

        return 'vivienda';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'centro' => $this->centro,
            'anio' => $this->anio,
            'modo_ejercicio' => $this->modoEjercicio,
            'fecha_inicio' => $this->fechaInicio->format('Y-m-d'),
            'fecha_cierre' => $this->fechaCierre->format('Y-m-d'),
            'tipo_cierre' => $this->tipoCierre,
            'num_residentes' => $this->numResidentes,
            'version' => $this->version,
            'observaciones_613_p' => $this->observaciones613P,
            'observaciones_613_g' => $this->observaciones613G,
            'media_cocina_mes' => $this->mediaCocinaMes,
            'media_cocina_acum' => $this->mediaCocinaAcum,
            'saldo_cc_personales' => $this->saldoCcPersonales,
            'meses' => $this->periodo()->mesesTranscurridos(),
        ];
    }
}
