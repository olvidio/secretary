<?php

declare(strict_types=1);

namespace src\importacion\application;

use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Cuenta;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\importacion\domain\contracts\ImportEjecucionRepository;
use src\importacion\domain\contracts\ImportFilaRepository;
use src\importacion\domain\entity\FilaImportada;
use src\importacion\domain\entity\FilaOrigenExcel;
use src\personas\domain\contracts\PersonaRepository;

/**
 * Aplica D8: alta / salto / actualización / anulación por (ejercicio, hoja, fila).
 * No borra asientos manuales, de cierre ni de remesa.
 */
final class SincronizarAsientosImportados
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly PersonaRepository $personas,
        private readonly TraductorApuntesAAsientos $traductor,
        private readonly ImportFilaRepository $filas,
        private readonly ImportEjecucionRepository $ejecuciones,
    ) {
    }

    /**
     * @param list<FilaOrigenExcel> $filasExcel
     * @return array{
     *     asientos: int,
     *     omitidos_concepto_9: int,
     *     traspasos_fusionados: int,
     *     altas: int,
     *     cambios: int,
     *     bajas: int,
     *     saltados: int
     * }
     */
    public function ejecutar(
        int $centroId,
        int $ejercicioId,
        string $fichero,
        string $sha256,
        array $filasExcel,
        bool $dryRun = false,
    ): array {
        $stats = [
            'omitidos_concepto_9' => 0,
            'traspasos_fusionados' => 0,
            'altas' => 0,
            'cambios' => 0,
            'bajas' => 0,
            'saltados' => 0,
        ];
        $vivos = [];
        $clavesVistas = [];

        usort(
            $filasExcel,
            static function (FilaOrigenExcel $a, FilaOrigenExcel $b): int {
                $cmp = $a->apunte->fecha->format('Y-m-d') <=> $b->apunte->fecha->format('Y-m-d');
                if ($cmp !== 0) {
                    return $cmp;
                }
                $cmpHoja = $a->hoja <=> $b->hoja;
                if ($cmpHoja !== 0) {
                    return $cmpHoja;
                }

                return $a->fila <=> $b->fila;
            }
        );

        $registradas = [];
        foreach ($this->filas->listarDeEjercicio($ejercicioId) as $reg) {
            $registradas[$reg->clave()] = $reg;
        }

        if (!$dryRun && $registradas === []) {
            $this->asientos->borrarPorOrigen($ejercicioId, 'import');
        }

        $personasPorIniciales = [];
        foreach ($this->personas->listar() as $persona) {
            $personasPorIniciales[strtolower($persona->iniciales)] = $persona;
        }

        $conId = [];
        $porId = [];
        $seq = 1;
        foreach ($filasExcel as $fila) {
            $apunte = $fila->apunte->withId($seq);
            $marcada = $fila->conApunte($apunte);
            $conId[] = $marcada;
            $porId[$seq] = $marcada;
            $clavesVistas[$marcada->clave()] = true;
            ++$seq;
        }

        $nueves = [];
        $traspasos = [];
        $normales = [];
        foreach ($conId as $fila) {
            if ($fila->apunte->conceptoCodigo === '9') {
                $nueves[] = $fila;
            } elseif (in_array($fila->apunte->conceptoCodigo, ['41', '42'], true)) {
                $traspasos[] = $fila;
            } else {
                $normales[] = $fila;
            }
        }

        foreach ($nueves as $fila) {
            $this->aplicarUnidad($centroId, $ejercicioId, [$fila], $registradas, $dryRun, $stats, $vivos, $personasPorIniciales);
        }

        $apuntesTraspaso = [];
        foreach ($traspasos as $fila) {
            $apuntesTraspaso[] = $fila->apunte;
        }
        foreach ($this->traductor->gruposDeTraspaso($apuntesTraspaso) as $grupo) {
            $unidad = [];
            foreach ($grupo as $apunte) {
                if ($apunte->id === null || !isset($porId[$apunte->id])) {
                    continue;
                }
                $unidad[] = $porId[$apunte->id];
            }
            if ($unidad === []) {
                continue;
            }
            if (count($unidad) >= 2) {
                ++$stats['traspasos_fusionados'];
            }
            $this->aplicarUnidad($centroId, $ejercicioId, $unidad, $registradas, $dryRun, $stats, $vivos, $personasPorIniciales);
        }

        foreach ($normales as $fila) {
            $this->aplicarUnidad($centroId, $ejercicioId, [$fila], $registradas, $dryRun, $stats, $vivos, $personasPorIniciales);
        }

        foreach ($registradas as $reg) {
            if (isset($clavesVistas[$reg->clave()])) {
                continue;
            }
            ++$stats['bajas'];
            $candidato = $reg->asientoId;
            if ($candidato !== null && !isset($vivos[$candidato])) {
                $asiento = $this->asientos->porId($candidato);
                if ($asiento !== null && $asiento->origen === 'import') {
                    if (!$dryRun) {
                        $this->asientos->anular($candidato);
                    }
                }
            }
            if (!$dryRun) {
                $this->filas->borrarClave($ejercicioId, $reg->hoja, $reg->fila);
            }
        }

        $this->ejecuciones->registrar(
            $centroId,
            $ejercicioId,
            $fichero,
            $sha256,
            $dryRun,
            $stats['altas'],
            $stats['cambios'],
            $stats['bajas'],
            $stats['saltados'] + $stats['omitidos_concepto_9'],
        );

        return [
            'asientos' => count($this->asientos->listarPorEjercicio($ejercicioId)),
            'omitidos_concepto_9' => $stats['omitidos_concepto_9'],
            'traspasos_fusionados' => $stats['traspasos_fusionados'],
            'altas' => $stats['altas'],
            'cambios' => $stats['cambios'],
            'bajas' => $stats['bajas'],
            'saltados' => $stats['saltados'],
        ];
    }

    /**
     * @param list<FilaOrigenExcel> $unidad
     * @param array<string, FilaImportada> $registradas
     * @param array<string, int> $stats
     * @param array<int, true> $vivos
     * @param array<string, \src\personas\domain\entity\Persona> $personasPorIniciales
     */
    private function aplicarUnidad(
        int $centroId,
        int $ejercicioId,
        array $unidad,
        array $registradas,
        bool $dryRun,
        array &$stats,
        array &$vivos,
        array $personasPorIniciales,
    ): void {
        $hashesIguales = true;
        $asientoIds = [];
        foreach ($unidad as $fila) {
            $prev = $registradas[$fila->clave()] ?? null;
            if ($prev === null || $prev->hashContenido !== $fila->hash()) {
                $hashesIguales = false;
            }
            if ($prev?->asientoId !== null) {
                $asientoIds[$prev->asientoId] = true;
            }
        }

        $existente = null;
        foreach (array_keys($asientoIds) as $aid) {
            $asiento = $this->asientos->porId($aid);
            if ($asiento === null || $asiento->origen !== 'import') {
                continue;
            }
            if ($existente === null) {
                $existente = $asiento;
            } elseif (!$dryRun) {
                $this->asientos->anular($aid);
            }
        }

        $esNueve = $unidad[0]->apunte->conceptoCodigo === '9';
        if ($esNueve) {
            ++$stats['omitidos_concepto_9'];
            if ($hashesIguales) {
                ++$stats['saltados'];
            }
            if (!$dryRun) {
                $this->persistirFilas($ejercicioId, $unidad, null);
            }

            return;
        }

        if ($hashesIguales && $existente !== null && $existente->id !== null) {
            ++$stats['saltados'];
            $vivos[$existente->id] = true;

            return;
        }

        $traducido = $this->traducirUnidad($centroId, $ejercicioId, $unidad, $personasPorIniciales);
        if ($traducido === null) {
            return;
        }

        if ($existente !== null && $existente->id !== null && $existente->numero !== null) {
            ++$stats['cambios'];
            if (!$dryRun) {
                $guardado = $this->asientos->actualizar(
                    $traducido->withId($existente->id)->withNumero($existente->numero)
                );
                if ($guardado->id !== null) {
                    $vivos[$guardado->id] = true;
                    $this->persistirFilas($ejercicioId, $unidad, $guardado->id);
                }
            } else {
                $vivos[$existente->id] = true;
            }

            return;
        }

        ++$stats['altas'];
        if (!$dryRun) {
            $guardado = $this->asientos->guardar($traducido);
            if ($guardado->id !== null) {
                $vivos[$guardado->id] = true;
                $this->persistirFilas($ejercicioId, $unidad, $guardado->id);
            }
        }
    }

    /**
     * @param list<FilaOrigenExcel> $unidad
     * @param array<string, \src\personas\domain\entity\Persona> $personasPorIniciales
     */
    private function traducirUnidad(int $centroId, int $ejercicioId, array $unidad, array $personasPorIniciales): ?Asiento
    {
        $apuntes = [];
        foreach ($unidad as $fila) {
            $apuntes[] = $fila->apunte;
        }
        $resultado = $this->traductor->traducir(
            $ejercicioId,
            $apuntes,
            static function (string $iniciales) use ($personasPorIniciales) {
                return $personasPorIniciales[strtolower($iniciales)] ?? null;
            },
            fn (string $libro, string $codigo): Cuenta => $this->resolverConcepto($centroId, $libro, $codigo),
            fn (string $libro, string $codigoMaestro): Cuenta => $this->resolverTesoreria($centroId, $libro, $codigoMaestro),
            fn (int $personaId): Cuenta => $this->resolverPersonal($centroId, $personaId),
            fn (): Cuenta => $this->resolverDeudores($centroId),
            'import',
        );
        $asientos = $resultado['asientos'];

        return $asientos[0] ?? null;
    }

    /**
     * @param list<FilaOrigenExcel> $unidad
     */
    private function persistirFilas(int $ejercicioId, array $unidad, ?int $asientoId): void
    {
        foreach ($unidad as $fila) {
            $this->filas->guardar(new FilaImportada(
                $ejercicioId,
                $fila->hoja,
                $fila->fila,
                $fila->hash(),
                $asientoId,
            ));
        }
    }

    private function resolverConcepto(int $centroId, string $libro, string $codigo): Cuenta
    {
        $cuenta = $this->cuentas->buscar($centroId, null, $libro, $codigo);
        if ($cuenta === null) {
            throw new InvalidArgumentException(
                sprintf('Cuenta de concepto no encontrada: %s/%s', $libro, $codigo)
            );
        }

        return $cuenta;
    }

    private function resolverTesoreria(int $centroId, string $libro, string $codigoMaestro): Cuenta
    {
        $cuenta = $this->cuentas->tesoreria($centroId, $libro, $codigoMaestro);
        if ($cuenta === null) {
            throw new InvalidArgumentException(
                sprintf('Cuenta de tesorería no encontrada: %s/%s', $libro, $codigoMaestro)
            );
        }

        return $cuenta;
    }

    private function resolverPersonal(int $centroId, int $personaId): Cuenta
    {
        $cuenta = $this->cuentas->personalDe($centroId, $personaId);
        if ($cuenta === null) {
            throw new InvalidArgumentException('Cuenta personal no encontrada para persona ' . $personaId);
        }

        return $cuenta;
    }

    private function resolverDeudores(int $centroId): Cuenta
    {
        $cuenta = $this->cuentas->deudoresVivienda($centroId);
        if ($cuenta === null) {
            throw new InvalidArgumentException('Cuenta DEUDORES.VIV no encontrada');
        }

        return $cuenta;
    }
}
