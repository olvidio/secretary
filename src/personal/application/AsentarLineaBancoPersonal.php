<?php

declare(strict_types=1);

namespace src\personal\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\importacion\domain\value_objects\LineaExtractoBanco;
use src\personal\domain\services\ConstructorAsientoPersonal;

/**
 * Anota una línea del extracto en el libro personal contra la categoría
 * «Por categorizar» de su sentido (al importar y al recuperar un descartado).
 */
final class AsentarLineaBancoPersonal
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly EjercicioRepository $ejercicios,
        private readonly AsegurarPlanPersonal $plan,
    ) {
    }

    public function tesoreriaBanco(int $centroId, int $personaId): int
    {
        $tesoreria = $this->cuentas->tesoreriaDePersona($centroId, $personaId, 'X', 'BANCO');
        if ($tesoreria === null || $tesoreria->id === null) {
            throw new InvalidArgumentException(_("No hay cuenta BANCO personal"));
        }

        return $tesoreria->id;
    }

    public function ejecutar(int $centroId, int $personaId, int $tesoreriaId, LineaExtractoBanco $linea): int
    {
        $fecha = new DateTimeImmutable($linea->fecha);
        $ejercicio = $this->ejercicios->deCentroEnFecha($centroId, $fecha);
        if ($ejercicio === null || $ejercicio->id === null) {
            throw new InvalidArgumentException(sprintf(_("No hay ejercicio que cubra %s"), $linea->fecha));
        }
        if ($ejercicio->estado === 'cerrado') {
            throw new InvalidArgumentException(sprintf(_("El ejercicio de %s está cerrado"), $linea->fecha));
        }
        $categoria = $this->plan->pendienteDe($centroId, $personaId, $linea->sentido());
        if ($categoria->id === null) {
            throw new InvalidArgumentException(_("No hay cuenta pendiente"));
        }
        $asiento = ConstructorAsientoPersonal::movimiento(
            $ejercicio->id,
            $personaId,
            $fecha,
            $linea->concepto !== '' ? $linea->concepto : null,
            $linea->sentido(),
            $categoria->id,
            $tesoreriaId,
            $linea->centsAbs(),
            $categoria->codigo,
            $fecha,
            'banco',
        );
        $guardado = $this->asientos->guardar($asiento);
        if ($guardado->id === null) {
            throw new InvalidArgumentException(_("No se pudo guardar el movimiento"));
        }

        return $guardado->id;
    }
}
