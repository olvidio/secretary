<?php

declare(strict_types=1);

namespace src\apuntes\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\domain\contracts\PlantillaApunteRepository;
use src\apuntes\domain\entity\PlantillaApunte;
use src\apuntes\domain\value_objects\LineaPlantillaApunte;
use src\conceptos\domain\contracts\ConceptoRepository;

final class GuardarPlantillaApunte
{
    public function __construct(
        private readonly PlantillaApunteRepository $plantillas,
        private readonly ConceptoRepository $conceptos,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function ejecutar(array $datos): array
    {
        $ctx = $this->ambito->ejecutar();
        $cuenta = strtoupper(trim((string) ($datos['cuenta'] ?? '')));
        if (!in_array($cuenta, ['P', 'G'], true)) {
            throw new InvalidArgumentException('Cuenta P o G');
        }
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException('Falta el nombre de la plantilla');
        }
        $id = isset($datos['id']) ? (int) $datos['id'] : null;
        if ($this->plantillas->existeNombre($ctx->centroId, $cuenta, $nombre, $id)) {
            throw new InvalidArgumentException('Ya existe una plantilla con ese nombre');
        }

        $lineasRaw = $datos['lineas'] ?? null;
        if (!is_array($lineasRaw) || $lineasRaw === []) {
            throw new InvalidArgumentException('La plantilla necesita al menos una línea');
        }

        $lineas = [];
        $orden = 0;
        foreach ($lineasRaw as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $lineaCuenta = strtoupper(trim((string) ($raw['cuenta'] ?? $cuenta)));
            if (!in_array($lineaCuenta, ['P', 'G'], true)) {
                throw new InvalidArgumentException('Libro P o G en cada línea');
            }
            $origen = strtoupper(trim((string) ($raw['origen'] ?? 'A')));
            if (!in_array($origen, ['A', 'B', 'C'], true)) {
                throw new InvalidArgumentException('Origen A, B o C en cada línea');
            }
            $concepto = trim((string) ($raw['concepto_codigo'] ?? ''));
            if ($concepto === '' || $this->conceptos->buscar($lineaCuenta, $concepto) === null) {
                throw new InvalidArgumentException('Concepto no válido: ' . $concepto);
            }
            $obs = trim((string) ($raw['observaciones'] ?? ''));
            $lineas[] = new LineaPlantillaApunte(
                $lineaCuenta,
                $origen,
                $concepto,
                $obs === '' ? null : $obs,
                $orden++,
            );
        }
        if ($lineas === []) {
            throw new InvalidArgumentException('La plantilla necesita al menos una línea');
        }

        $guardada = $this->plantillas->guardar(new PlantillaApunte(
            $id,
            $ctx->centroId,
            $cuenta,
            $nombre,
            true,
            (int) ($datos['orden'] ?? 0),
            $lineas,
        ));

        return $guardada->toArray();
    }
}
