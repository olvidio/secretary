<?php

declare(strict_types=1);

namespace src\informes\domain\entity;

use DateTimeImmutable;

/** Campos manuales del resumen 613 para un mes de cierre concreto. */
final class Informe613Mes
{
    public function __construct(
        public readonly int $ejercicioId,
        public readonly DateTimeImmutable $fechaCierre,
        public readonly string $cuenta,
        public readonly ?string $observaciones = null,
        public readonly ?string $saldoCcPersonales = null,
        public readonly ?string $mediaCocinaMes = null,
        public readonly ?string $mediaCocinaAcum = null,
        public readonly ?string $dineroArqueoCaja = null,
        public readonly ?string $dineroArqueoBanco = null,
        public readonly bool $enviado = false,
        public readonly ?DateTimeImmutable $enviadoEn = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ejercicio_id' => $this->ejercicioId,
            'fecha_cierre' => $this->fechaCierre->format('Y-m-d'),
            'cuenta' => $this->cuenta,
            'observaciones' => $this->observaciones,
            'saldo_cc_personales' => $this->saldoCcPersonales,
            'media_cocina_mes' => $this->mediaCocinaMes,
            'media_cocina_acum' => $this->mediaCocinaAcum,
            'dinero_arqueo_caja' => $this->dineroArqueoCaja,
            'dinero_arqueo_banco' => $this->dineroArqueoBanco,
            'enviado' => $this->enviado,
            'enviado_en' => $this->enviadoEn?->format('c'),
        ];
    }
}
