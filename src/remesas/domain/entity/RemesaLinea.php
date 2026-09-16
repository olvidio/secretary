<?php

declare(strict_types=1);

namespace src\remesas\domain\entity;

use src\shared\domain\value_objects\Dinero;

final class RemesaLinea
{
    /**
     * @param list<array{
     *     codigo:string,
     *     nombre:string,
     *     cents:int,
     *     generales?:list<array{concepto:string,cents:int}>,
     *     plantillas?:list<array{plantilla_id:int,cents:int,nombre?:string}>
     * }> $detalle
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
                    if (!is_array($gen)) {
                        continue;
                    }
                    $cents = (int) ($gen['cents'] ?? 0);
                    $generales[] = [
                        'concepto' => (string) ($gen['concepto'] ?? ''),
                        'cents' => $cents,
                        'importe_es' => Dinero::fromCents(abs($cents))->formatEs(),
                    ];
                }
                if ($generales !== []) {
                    $fila['generales'] = $generales;
                }
            }
            if (!empty($item['plantillas']) && is_array($item['plantillas'])) {
                $plantillas = [];
                foreach ($item['plantillas'] as $pl) {
                    if (!is_array($pl)) {
                        continue;
                    }
                    $cents = (int) ($pl['cents'] ?? 0);
                    $entry = [
                        'plantilla_id' => (int) ($pl['plantilla_id'] ?? 0),
                        'cents' => $cents,
                        'importe_es' => Dinero::fromCents(abs($cents))->formatEs(),
                    ];
                    if (!empty($pl['nombre'])) {
                        $entry['nombre'] = (string) $pl['nombre'];
                    }
                    $plantillas[] = $entry;
                }
                if ($plantillas !== []) {
                    $fila['plantillas'] = $plantillas;
                }
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
