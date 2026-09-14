<?php

declare(strict_types=1);

namespace src\remesas\domain\entity;

use src\shared\domain\value_objects\Dinero;

final class RemesaLinea
{
    /**
     * @param list<array{codigo:string,nombre:string,cents:int}> $detalle
     */
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $remesaId,
        public readonly string $codigoMaestro,
        public readonly int $importeCents,
        public readonly array $detalle,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $detalle = [];
        foreach ($this->detalle as $item) {
            $fila = [
                'codigo' => $item['codigo'],
                'nombre' => $item['nombre'],
                'cents' => $item['cents'],
                'importe' => Dinero::fromCents($item['cents'])->toString(),
                'importe_es' => Dinero::fromCents($item['cents'])->formatEs(),
            ];
            if (!empty($item['generales']) && is_array($item['generales'])) {
                $generales = [];
                foreach ($item['generales'] as $gen) {
                    $cents = (int) ($gen['cents'] ?? 0);
                    $generales[] = [
                        'concepto' => (string) ($gen['concepto'] ?? ''),
                        'cents' => $cents,
                        'importe_es' => Dinero::fromCents(abs($cents))->formatEs(),
                    ];
                }
                $fila['generales'] = $generales;
            }
            $detalle[] = $fila;
        }

        return [
            'id' => $this->id,
            'codigo_maestro' => $this->codigoMaestro,
            'importe_cents' => $this->importeCents,
            'importe' => Dinero::fromCents($this->importeCents)->toString(),
            'importe_es' => Dinero::fromCents($this->importeCents)->formatEs(),
            'detalle' => $detalle,
        ];
    }
}
