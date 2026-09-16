<?php

declare(strict_types=1);

namespace src\remesas\application;

use src\apuntes\domain\contracts\PlantillaApunteRepository;

/** Añade el nombre de plantilla a las líneas de remesa expuestas por API. */
final class EnriquecerLineasRemesa
{
    public function __construct(
        private readonly PlantillaApunteRepository $plantillas,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $lineas
     * @return list<array<string, mixed>>
     */
    public function ejecutar(int $centroId, array $lineas): array
    {
        /** @var array<int, string> $nombres */
        $nombres = [];
        foreach ($lineas as &$linea) {
            if (!isset($linea['detalle']) || !is_array($linea['detalle'])) {
                continue;
            }
            foreach ($linea['detalle'] as &$item) {
                if (empty($item['plantillas']) || !is_array($item['plantillas'])) {
                    continue;
                }
                $plantillas = [];
                foreach ($item['plantillas'] as $pl) {
                    if (!is_array($pl)) {
                        continue;
                    }
                    $pid = (int) ($pl['plantilla_id'] ?? 0);
                    if ($pid <= 0) {
                        continue;
                    }
                    if (!isset($nombres[$pid])) {
                        $plantilla = $this->plantillas->porId($centroId, $pid);
                        $nombres[$pid] = $plantilla !== null ? $plantilla->nombre : ('Plantilla ' . $pid);
                    }
                    $pl['nombre'] = $nombres[$pid];
                    $plantillas[] = $pl;
                }
                $item['plantillas'] = $plantillas;
            }
            unset($item);
        }
        unset($linea);

        return $lineas;
    }
}
