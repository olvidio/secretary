<?php

declare(strict_types=1);

namespace src\remesas\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\apuntes\application\CrearApunte;
use src\apuntes\domain\contracts\PlantillaApunteRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\remesas\domain\entity\Remesa;
use src\shared\domain\value_objects\Dinero;

/** Expande las plantillas del centro al aceptar una remesa (como los apuntes manuales del centro). */
final class RegistrarPlantillasDeRemesa
{
    public function __construct(
        private readonly CrearApunte $crearApunte,
        private readonly PlantillaApunteRepository $plantillas,
        private readonly PersonaRepository $personas,
    ) {
    }

    public function ejecutar(Remesa $remesa, DateTimeImmutable $fecha, int $remesaId): void
    {
        if ($remesa->id === null) {
            throw new InvalidArgumentException('Remesa sin identificador');
        }
        $persona = $this->personas->porId($remesa->personaId);
        if ($persona === null) {
            throw new InvalidArgumentException('Persona no encontrada');
        }
        $iniciales = strtoupper($persona->iniciales);
        $glosaBase = sprintf('Remesa %s %02d/%d v%d', $iniciales, $remesa->mes, $remesa->anio, $remesa->version);

        foreach ($remesa->lineas as $linea) {
            foreach ($linea->detalle as $item) {
                if (empty($item['plantillas']) || !is_array($item['plantillas'])) {
                    continue;
                }
                foreach ($item['plantillas'] as $pl) {
                    $plantillaId = (int) ($pl['plantilla_id'] ?? 0);
                    $cents = (int) ($pl['cents'] ?? 0);
                    if ($plantillaId <= 0 || $cents === 0) {
                        continue;
                    }
                    $plantilla = $this->plantillas->porId($remesa->centroId, $plantillaId);
                    if ($plantilla === null) {
                        throw new InvalidArgumentException('Plantilla de remesa no encontrada');
                    }
                    $cantidad = Dinero::fromCents(abs($cents))->toString();
                    foreach ($plantilla->lineas as $i => $lineaPl) {
                        $obs = trim((string) ($lineaPl->observaciones ?? ''));
                        if ($obs === '') {
                            $obs = $glosaBase;
                        }
                        $payload = [
                            'fecha' => $fecha->format('Y-m-d'),
                            'cuenta' => $lineaPl->cuenta,
                            'origen' => $lineaPl->origen,
                            'iniciales' => $iniciales,
                            'concepto_codigo' => $lineaPl->conceptoCodigo,
                            'observaciones' => $obs,
                            'cantidad' => $cantidad,
                            'remesa_id' => $remesaId,
                        ];
                        if ($i > 0) {
                            $payload['fecha_imputacion'] = $fecha->format('Y-m-d');
                        }
                        $this->crearApunte->ejecutar($payload);
                    }
                }
            }
        }
    }
}
