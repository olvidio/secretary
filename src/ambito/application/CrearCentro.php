<?php

declare(strict_types=1);

namespace src\ambito\application;

use InvalidArgumentException;
use PDO;
use src\acceso\application\AsegurarIdentidadCentro;
use src\acceso\domain\entity\Identidad;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\contracts\PobladorCentro;
use src\ambito\domain\entity\Centro;
use src\ambito\domain\entity\Ejercicio;
use src\plan\domain\contracts\PartidaLaboresRepository;
use src\plan\domain\contracts\PlanContableRepository;
use src\plan\domain\services\CatalogoPlanesContables;

/**
 * Alta de un centro aislado con su plan de cuentas y su secretario (Fase 9).
 * El usuario que crea el centro NO queda vinculado: solo el secretario indicado.
 */
final class CrearCentro
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly CentroRepository $centros,
        private readonly CrearEjercicio $crearEjercicio,
        private readonly PobladorCentro $poblador,
        private readonly AsegurarIdentidadCentro $asegurarIdentidad,
        private readonly PartidaLaboresRepository $partidasLabores,
        private readonly PlanContableRepository $planes,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{centro: Centro, ejercicio: Ejercicio, identidad: Identidad}
     */
    public function ejecutar(array $datos): array
    {
        $codigo = trim((string) ($datos['codigo'] ?? ''));
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $tipo = strtolower(trim((string) ($datos['tipo'] ?? 'n')));
        $tipoCierre = (string) ($datos['tipo_cierre'] ?? 'vivienda');
        $verificarEmail = !array_key_exists('verificar_email', $datos) || (bool) $datos['verificar_email'];
        if ($codigo === '' || $nombre === '') {
            throw new InvalidArgumentException(_("Código y nombre del centro son obligatorios"));
        }
        if (!in_array($tipo, ['n', 'sg'], true)) {
            throw new InvalidArgumentException(_("Tipo de centro: n o sg"));
        }
        if (!in_array($tipoCierre, ['vivienda', 'necesidades'], true)) {
            throw new InvalidArgumentException(_("Tipo de cierre: vivienda o necesidades"));
        }
        if ($this->centros->porCodigo($codigo) !== null) {
            throw new InvalidArgumentException(_("Ya existe un centro con ese código"));
        }
        $planCodigo = trim((string) ($datos['plan_contable'] ?? CatalogoPlanesContables::H16N));
        if ($this->planes->idPorCodigo($planCodigo) === null) {
            throw new InvalidArgumentException(_("Plan contable no válido"));
        }
        $this->pdo->beginTransaction();
        try {
            $centro = $this->centros->guardar(
                new Centro(null, $codigo, $nombre, $tipo, $tipoCierre, $planCodigo)
            );
            if ($centro->id === null) {
                throw new InvalidArgumentException(_("No se pudo crear el centro"));
            }
            $this->partidasLabores->sembrarPorDefecto($centro->id);
            $ejercicio = $this->crearEjercicio->ejecutar([
                'centro_id' => $centro->id,
                'etiqueta' => (string) ($datos['etiqueta'] ?? ''),
                'fecha_inicio' => $datos['fecha_inicio'] ?? null,
                'fecha_fin' => $datos['fecha_fin'] ?? null,
                'fecha_corte' => $datos['fecha_corte'] ?? $datos['fecha_inicio'] ?? null,
            ]);
            $this->poblador->ejecutar($centro->id);
            $identidad = $this->asegurarIdentidad->ejecutar(
                $centro->id,
                (string) ($datos['usuario'] ?? ''),
                (string) ($datos['email'] ?? ''),
                (string) ($datos['password'] ?? ''),
                $nombre,
                'admin',
                $verificarEmail,
            );
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'centro' => $centro,
            'ejercicio' => $ejercicio,
            'identidad' => $identidad,
        ];
    }
}
