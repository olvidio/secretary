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
            $detalle[] = [
                'codigo' => $item['codigo'],
                'nombre' => $item['nombre'],
                'cents' => $item['cents'],
                'importe' => Dinero::fromCents($item['cents'])->toString(),
                'importe_es' => Dinero::fromCents($item['cents'])->formatEs(),
            ];
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
