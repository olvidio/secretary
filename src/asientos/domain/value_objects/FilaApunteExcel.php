<?php

declare(strict_types=1);

namespace src\asientos\domain\value_objects;

use DateTimeImmutable;
use src\shared\domain\value_objects\Dinero;

/** Proyección Excel de un asiento; compatible con `Apunte::toArray()` para la API. */
final class FilaApunteExcel
{
    public function __construct(
        public readonly int $id,
        public readonly DateTimeImmutable $fecha,
        public readonly string $cuenta,
        public readonly string $origen,
        public readonly ?string $iniciales,
        public readonly string $conceptoCodigo,
        public readonly ?string $observaciones,
        public readonly Dinero $cantidad,
        public readonly bool $esCierre,
        public readonly ?DateTimeImmutable $fechaImputacion = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $imputacion = $this->fechaImputacion;
        $imputacionDistinta = $imputacion !== null
            && $imputacion->format('Y-m-d') !== $this->fecha->format('Y-m-d');

        $out = [
            'id' => $this->id,
            'fecha' => $this->fecha->format('Y-m-d'),
            'fecha_es' => $this->fecha->format('d/m/Y'),
            'cuenta' => $this->cuenta,
            'origen' => $this->origen,
            'iniciales' => $this->iniciales,
            'concepto_codigo' => $this->conceptoCodigo,
            'observaciones' => $this->observaciones,
            'cantidad' => $this->cantidad->toString(),
            'cantidad_es' => $this->cantidad->formatEs(),
            'es_cierre' => $this->esCierre,
            'par_id' => null,
        ];
        if ($imputacionDistinta) {
            $out['fecha_imputacion'] = $imputacion->format('Y-m-d');
            $out['fecha_imputacion_es'] = $imputacion->format('d/m/Y');
        }

        return $out;
    }
}
