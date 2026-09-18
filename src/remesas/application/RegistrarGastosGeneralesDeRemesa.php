<?php

declare(strict_types=1);

namespace src\remesas\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\apuntes\application\CrearApuntesDeEntrada;
use src\personas\domain\contracts\PersonaRepository;
use src\remesas\domain\entity\Remesa;
use src\shared\domain\value_objects\Dinero;

/** Genera P/211, G/11 y el gasto G al aceptar una remesa con líneas marcadas como generales. */
final class RegistrarGastosGeneralesDeRemesa
{
    public function __construct(
        private readonly CrearApuntesDeEntrada $crearApuntes,
        private readonly PersonaRepository $personas,
    ) {
    }

    public function ejecutar(Remesa $remesa, DateTimeImmutable $fecha, int $remesaId): void
    {
        if ($remesa->id === null) {
            throw new InvalidArgumentException(_("Remesa sin identificador"));
        }
        $persona = $this->personas->porId($remesa->personaId);
        if ($persona === null) {
            throw new InvalidArgumentException(_("Persona no encontrada"));
        }
        $iniciales = strtoupper($persona->iniciales);
        $glosaBase = sprintf('Remesa %s %02d/%d v%d', $iniciales, $remesa->mes, $remesa->anio, $remesa->version);

        foreach ($remesa->lineas as $linea) {
            foreach ($linea->detalle as $item) {
                if (empty($item['generales']) || !is_array($item['generales'])) {
                    continue;
                }
                foreach ($item['generales'] as $gen) {
                    $concepto = trim((string) ($gen['concepto'] ?? ''));
                    $cents = (int) ($gen['cents'] ?? 0);
                    if ($concepto === '' || $cents === 0) {
                        continue;
                    }
                    $this->crearApuntes->ejecutar([
                        'fecha' => $fecha->format('Y-m-d'),
                        'cuenta' => 'G',
                        'origen' => 'A',
                        'iniciales' => $iniciales,
                        'concepto_codigo' => $concepto,
                        'observaciones' => $glosaBase,
                        'cantidad' => Dinero::fromCents(abs($cents))->toString(),
                        'contrapartidas' => true,
                        'remesa_id' => $remesaId,
                    ]);
                }
            }
        }
    }
}
