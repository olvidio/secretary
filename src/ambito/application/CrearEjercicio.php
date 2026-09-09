<?php

declare(strict_types=1);

namespace src\ambito\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Ejercicio;
use src\cierre\application\GenerarApertura;

/**
 * Alta de un ejercicio de período libre (D11). Valida que no se solape con
 * ningún otro ejercicio del mismo centro antes de guardar. Si es contiguo al
 * anterior cerrado, enlaza `ejercicio_anterior_id` y genera la apertura (D12).
 */
final class CrearEjercicio
{
    public function __construct(
        private readonly EjercicioRepository $repo,
        private readonly GenerarApertura $generarApertura,
        private readonly SincronizarConfiguracionConEjercicio $sincronizarConfig,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos): Ejercicio
    {
        $centroId = (int) ($datos['centro_id'] ?? 0);
        if ($centroId <= 0) {
            throw new InvalidArgumentException('centro_id es obligatorio');
        }
        $fechaInicio = self::fecha($datos['fecha_inicio'] ?? null, 'fecha_inicio');
        $fechaFin = self::fecha($datos['fecha_fin'] ?? null, 'fecha_fin');
        $fechaCorteInput = $datos['fecha_corte'] ?? null;
        $fechaCorte = $fechaCorteInput !== null && $fechaCorteInput !== ''
            ? self::fecha($fechaCorteInput, 'fecha_corte')
            : $fechaInicio;
        $etiqueta = trim((string) ($datos['etiqueta'] ?? ''));
        if ($etiqueta === '') {
            $etiqueta = $fechaInicio->format('Y') === $fechaFin->format('Y')
                ? $fechaInicio->format('Y')
                : $fechaInicio->format('Y') . '-' . $fechaFin->format('y');
        }

        $nuevo = new Ejercicio(null, $centroId, $etiqueta, $fechaInicio, $fechaFin, $fechaCorte);
        foreach ($this->repo->listarDeCentro($centroId) as $existente) {
            if ($existente->solapaCon($nuevo)) {
                throw new InvalidArgumentException(sprintf(
                    'El ejercicio se solapa con «%s» (%s a %s)',
                    $existente->etiqueta,
                    $existente->fechaInicio->format('Y-m-d'),
                    $existente->fechaFin->format('Y-m-d'),
                ));
            }
        }

        $anterior = $this->repo->contiguoAnterior($centroId, $fechaInicio);
        $ejercicioAnteriorId = null;
        if ($anterior !== null) {
            if ($anterior->estado === 'abierto') {
                throw new InvalidArgumentException(
                    'Cierre primero el ejercicio ' . $anterior->etiqueta
                );
            }
            $ejercicioAnteriorId = $anterior->id;
        }

        $abiertoActual = $this->repo->abiertoDe($centroId);
        if ($abiertoActual !== null) {
            throw new InvalidArgumentException(
                'Cierre primero el ejercicio ' . $abiertoActual->etiqueta
            );
        }

        $guardado = $this->repo->guardar(new Ejercicio(
            null,
            $centroId,
            $etiqueta,
            $fechaInicio,
            $fechaFin,
            $fechaCorte,
            'abierto',
            $ejercicioAnteriorId,
        ));

        if ($ejercicioAnteriorId !== null && $guardado->id !== null) {
            $this->generarApertura->ejecutar($guardado->id);
            $guardado = $this->repo->porId($guardado->id) ?? $guardado;
            $this->sincronizarConfig->ejecutar($guardado);
        }

        return $guardado;
    }

    private static function fecha(mixed $v, string $campo): DateTimeImmutable
    {
        if (!is_string($v) || $v === '') {
            throw new InvalidArgumentException($campo . ' es obligatorio');
        }
        $f = DateTimeImmutable::createFromFormat('Y-m-d', $v);
        if ($f === false) {
            throw new InvalidArgumentException($campo . ' debe tener formato AAAA-MM-DD');
        }

        return $f->setTime(0, 0);
    }
}
