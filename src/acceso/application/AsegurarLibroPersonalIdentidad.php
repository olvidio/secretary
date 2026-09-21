<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\contracts\LibroPersonalIdentidadPort;
use src\ambito\application\CrearEjercicio;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\entity\Centro;
use src\personal\application\AsegurarPlanPersonal;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;
use src\plan\domain\services\CatalogoPlanesContables;

/**
 * Crea (si falta) el ámbito mínimo para el libro X de una identidad de persona
 * sin centro real: centro tipo p, ejercicio del año en curso, persona e identidad_persona.
 */
final class AsegurarLibroPersonalIdentidad implements LibroPersonalIdentidadPort
{
    public const TIPO_CENTRO = 'p';

    public function __construct(
        private readonly PDO $pdo,
        private readonly CentroRepository $centros,
        private readonly CrearEjercicio $crearEjercicio,
        private readonly IdentidadRepository $identidades,
        private readonly PersonaRepository $personas,
        private readonly AsegurarPlanPersonal $planPersonal,
    ) {
    }

    public function ejecutar(int $identidadId): ?Persona
    {
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null || $identidad->id === null) {
            return null;
        }

        foreach ($this->identidades->personasDe($identidadId) as $personaId) {
            $persona = $this->personas->porId($personaId);
            if ($persona === null || $persona->centroId === null || $persona->id === null) {
                continue;
            }
            $centro = $this->centros->porId($persona->centroId);
            if ($centro !== null && $centro->tipo === self::TIPO_CENTRO) {
                $this->planPersonal->ejecutar($persona->centroId, $persona->id);

                return $persona;
            }
        }

        if ($this->identidades->personasDe($identidadId) !== []) {
            return null;
        }

        return $this->crear($identidadId, $identidad->nombre, $identidad->alias ?? 'usr', $identidad->email);
    }

    private function crear(int $identidadId, string $nombre, string $aliasBase, string $email): Persona
    {
        $aliasBase = strtolower(trim($aliasBase));
        if ($aliasBase === '') {
            $aliasBase = 'usr';
        }
        $partes = preg_split('/\s+/', trim($nombre), 2) ?: [];
        $nom = $partes[0] ?? $nombre;
        $ape = $partes[1] ?? '';
        $anio = (int) (new DateTimeImmutable())->format('Y');

        $this->pdo->beginTransaction();
        try {
            $centro = $this->centros->guardar(new Centro(
                null,
                $this->codigoLibre($aliasBase),
                trim($nombre) !== '' ? trim($nombre) : $aliasBase,
                self::TIPO_CENTRO,
                'vivienda',
                CatalogoPlanesContables::H16N,
                true,
            ));
            if ($centro->id === null) {
                throw new InvalidArgumentException(_("No se pudo crear el ámbito personal"));
            }

            $this->crearEjercicio->ejecutar([
                'centro_id' => $centro->id,
                'fecha_inicio' => sprintf('%d-01-01', $anio),
                'fecha_fin' => sprintf('%d-12-31', $anio),
                'fecha_corte' => sprintf('%d-01-01', $anio),
            ]);

            $persona = $this->personas->guardar(new Persona(
                null,
                $nom,
                $ape,
                $this->inicialesLibres($aliasBase),
                null,
                null,
                null,
                null,
                null,
                0,
                $centro->id,
                true,
                $email !== '' ? strtolower($email) : null,
                true,
            ));
            if ($persona->id === null) {
                throw new InvalidArgumentException(_("No se pudo crear la persona"));
            }

            $this->identidades->vincularPersona($identidadId, $persona->id, $anio);
            $this->planPersonal->ejecutar($centro->id, $persona->id);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $persona;
    }

    private function codigoLibre(string $alias): string
    {
        $base = substr(preg_replace('/[^a-z0-9]/', '', $alias) ?? 'usr', 0, 20);
        if ($base === '') {
            $base = 'usr';
        }
        $prefijo = 'p-';
        $candidato = $prefijo . $base;
        $n = 1;
        while ($this->centros->porCodigo($candidato) !== null) {
            $suf = (string) $n;
            $candidato = $prefijo . substr($base, 0, max(1, 24 - strlen($prefijo) - strlen($suf))) . $suf;
            $n++;
            if ($n > 99) {
                throw new InvalidArgumentException(_("No se pudo reservar un código de ámbito personal"));
            }
        }

        return $candidato;
    }

    private function inicialesLibres(string $alias): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($alias)) ?? '';
        $base = substr($base, 0, 6);
        if ($base === '') {
            $base = 'usr';
        }
        $candidato = $base;
        $n = 1;
        while ($this->personas->porIniciales($candidato) !== null) {
            $suf = (string) $n;
            $candidato = substr($base, 0, max(1, 6 - strlen($suf))) . $suf;
            $n++;
            if ($n > 99) {
                throw new InvalidArgumentException(_("No se pudieron generar iniciales únicas"));
            }
        }

        return $candidato;
    }
}
