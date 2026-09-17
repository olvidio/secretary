<?php

declare(strict_types=1);

namespace src\presupuestos\application;

use InvalidArgumentException;
use src\presupuestos\domain\contracts\PrevisionPersonalRepository;
use src\presupuestos\domain\entity\LineaPrevisionPersonal;
use src\shared\domain\value_objects\Dinero;

final class GuardarPrevisionPersonal
{
    public function __construct(
        private readonly ObtenerPrevisionPersonal $obtener,
        private readonly PrevisionPersonalRepository $repo,
    ) {
    }

    /**
     * @param array<string, mixed> $lineas codigo => importe (vacío = valor calculado)
     * @return array<string, mixed>
     */
    public function ejecutar(int $personaId, array $lineas): array
    {
        $persona = $this->obtener->exigirPersonaDelCentro($personaId);
        $hoja = $this->obtener->ejecutar($personaId);
        $ejercicioId = (int) $hoja['ejercicio_id'];
        $guardadas = [];
        foreach ($hoja['lineas'] as $fila) {
            $codigo = (string) $fila['codigo'];
            $raw = $lineas[$codigo] ?? '';
            if (is_array($raw)) {
                throw new InvalidArgumentException(_("La cantidad debe ser numérica"));
            }
            $texto = trim((string) $raw);
            if ($texto === '') {
                $cents = (int) $fila['calculado_cents'];
            } else {
                $cents = Dinero::fromInput($texto)->toCents();
            }
            $guardadas[] = new LineaPrevisionPersonal($ejercicioId, (int) $persona->id, $codigo, $cents);
        }
        $this->repo->reemplazarDePersona($ejercicioId, (int) $persona->id, $guardadas);

        return $this->obtener->ejecutar($personaId);
    }
}
