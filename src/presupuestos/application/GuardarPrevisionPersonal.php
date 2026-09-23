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
        private readonly ConstruirHojaPrevision $hojaPrevision,
    ) {
    }

    /**
     * @param array<string, mixed> $lineas codigo => importe (vacío = no guardar esa línea)
     * @return array<string, mixed>
     */
    public function ejecutar(int $personaId, array $lineas, ?string $etiquetaObjetivo = null): array
    {
        $persona = $this->obtener->exigirPersonaDelCentro($personaId);
        $hoja = $this->obtener->ejecutar($personaId, $etiquetaObjetivo);
        $etiqueta = (string) ($hoja['etiqueta_presupuesto'] ?? '');
        $ejercicio = $this->hojaPrevision->asegurarEjercicioPrevision($etiqueta);
        if ($ejercicio->id === null) {
            throw new InvalidArgumentException(_("No hay ejercicio para el año elegido"));
        }
        $ejercicioId = $ejercicio->id;
        $guardadas = [];
        foreach ($hoja['lineas'] as $fila) {
            $codigo = (string) $fila['codigo'];
            if (!array_key_exists($codigo, $lineas)) {
                continue;
            }
            $raw = $lineas[$codigo];
            if (is_array($raw)) {
                throw new InvalidArgumentException(_("La cantidad debe ser numérica"));
            }
            $texto = trim((string) $raw);
            if ($texto === '') {
                continue;
            }
            $cents = Dinero::fromInput($texto)->toCents();
            $guardadas[] = new LineaPrevisionPersonal($ejercicioId, (int) $persona->id, $codigo, $cents);
        }
        $this->repo->reemplazarDePersona($ejercicioId, (int) $persona->id, $guardadas);

        return $this->obtener->ejecutar($personaId, $etiqueta);
    }
}
