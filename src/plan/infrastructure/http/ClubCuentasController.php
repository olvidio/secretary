<?php

declare(strict_types=1);

namespace src\plan\infrastructure\http;

use InvalidArgumentException;
use PDO;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\entity\Centro;
use src\ambito\infrastructure\persistence\AmbitoSeeder;
use src\plan\domain\contracts\PlanConceptoRepository;
use src\plan\domain\contracts\PlanContableRepository;
use src\plan\domain\services\CatalogoConceptosClub;
use src\plan\domain\services\CatalogoPlanesContables;
use src\plan\domain\services\CuentasPropiasClub;
use src\plan\infrastructure\persistence\PdoCuentasClub;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

/** Cuentas propias de una associació y modelos de plan de cuentas (ámbito club). */
final class ClubCuentasController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ResolverAmbitoActual $ambito,
        private readonly CentroRepository $centros,
        private readonly PlanContableRepository $planes,
        private readonly PlanConceptoRepository $conceptos,
        private readonly PdoCuentasClub $cuentasClub,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        try {
            $centro = $this->exigirClub();

            return ContestarJson::ok([
                'cuentas' => $this->cuentasDe((int) $centro->id),
                'modelos' => $this->modelos(),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function crear(Request $request, array $vars = []): Response
    {
        try {
            $centro = $this->exigirClub();
            $datos = $request->json();
            $codigo = trim((string) ($datos['codigo'] ?? ''));
            $nombre = trim((string) ($datos['nombre'] ?? ''));
            $naturaleza = (string) ($datos['naturaleza'] ?? 'gasto');
            if ($codigo === '' || $nombre === '') {
                throw new InvalidArgumentException(_('Código y nombre son obligatorios'));
            }
            if (preg_match('/^[A-Za-z0-9._-]{1,16}$/', $codigo) !== 1) {
                throw new InvalidArgumentException(_('Código de cuenta no válido'));
            }
            if (!in_array($naturaleza, ['ingreso', 'gasto'], true)) {
                throw new InvalidArgumentException(_('La naturaleza es ingreso o gasto'));
            }
            $oficial = $this->oficialValido($datos['codigo_oficial'] ?? null);
            foreach ($this->cuentasDe((int) $centro->id) as $existente) {
                if ($existente['codigo'] === $codigo) {
                    throw new InvalidArgumentException(sprintf(_('Ya existe una cuenta con el código %s'), $codigo));
                }
            }
            $grupo = !empty($datos['grupo']);
            $this->upsert((int) $centro->id, $codigo, $nombre, $naturaleza, !$grupo);
            if ($oficial !== null && !$grupo) {
                $this->ponerOficial((int) $centro->id, $codigo, $oficial);
            }

            return ContestarJson::ok(['cuentas' => $this->cuentasDe((int) $centro->id)]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    /**
     * Modifica una cuenta: código, nombre, naturaleza y código oficial. Si cambia el
     * código y no viene confirmado, no hace nada y devuelve el aviso para preguntar.
     */
    public function modificar(Request $request, array $vars = []): Response
    {
        try {
            $centro = $this->exigirClub();
            $datos = $request->json();
            $actual = trim((string) ($datos['codigo'] ?? ''));
            $naturaleza = isset($datos['naturaleza']) ? (string) $datos['naturaleza'] : null;
            if ($naturaleza !== null && !in_array($naturaleza, ['ingreso', 'gasto'], true)) {
                $naturaleza = null;
            }
            $resultado = $this->cuentasClub->modificar((int) $centro->id, $actual, [
                'codigo' => trim((string) ($datos['nuevo_codigo'] ?? $actual)),
                'nombre' => trim((string) ($datos['nombre'] ?? '')),
                'naturaleza' => $naturaleza,
                'codigo_oficial' => $this->oficialValido($datos['codigo_oficial'] ?? null),
            ], !empty($datos['confirmado']));

            return ContestarJson::ok([
                'pendiente' => $resultado['pendiente'],
                'aviso' => $resultado['aviso'],
                'cuentas' => $this->cuentasDe((int) $centro->id),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    /** Guarda las cuentas del centro como modelo, para copiarlas a otra associació. */
    public function guardarModelo(Request $request, array $vars = []): Response
    {
        try {
            $centro = $this->exigirClub();
            $nombre = trim((string) ($request->json()['nombre'] ?? ''));
            if ($nombre === '') {
                throw new InvalidArgumentException(_('El modelo necesita un nombre'));
            }
            $filas = [];
            $orden = 10;
            foreach ($this->cuentasDe((int) $centro->id) as $cuenta) {
                if ($cuenta['fija'] && $cuenta['codigo'] !== 'APERTURA') {
                    continue;
                }
                $filas[] = [
                    'codigo' => $cuenta['codigo'],
                    'cuenta' => 'G',
                    'nombre' => $cuenta['nombre'],
                    'descripcion' => $cuenta['nombre'],
                    'naturaleza' => $cuenta['imputable']
                        ? $cuenta['naturaleza_plan']
                        : ($cuenta['tipo'] === 'ingreso' ? 'grupo-ingreso' : 'grupo-gasto'),
                    'orden' => $orden,
                ];
                $orden += 10;
            }
            if ($filas === []) {
                throw new InvalidArgumentException(_('No hay cuentas que guardar'));
            }
            $plan = $this->planes->guardar(null, $this->codigoLibre($nombre), $nombre);
            $this->pdo->prepare('UPDATE planes_contables SET modelo_de = :b WHERE id = :id')
                ->execute([':b' => CatalogoPlanesContables::CLUB, ':id' => $plan['id']]);
            $this->conceptos->reemplazar($plan['id'], $filas);

            return ContestarJson::ok(['modelos' => $this->modelos()]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    /**
     * Copia al centro las cuentas del modelo que le falten. Las que ya tiene no se tocan,
     * y el centro no queda ligado al modelo: después se puede borrar.
     */
    public function aplicarModelo(Request $request, array $vars = []): Response
    {
        try {
            $centro = $this->exigirClub();
            $codigo = trim((string) ($request->json()['codigo'] ?? ''));
            $modelo = $this->modelo($codigo);
            if ($modelo === null) {
                throw new InvalidArgumentException(_('Elija un modelo de cuentas'));
            }
            $existentes = array_column($this->cuentasDe((int) $centro->id), 'codigo');
            $anadidas = 0;
            foreach ($this->conceptos->listar($modelo['id'], 'G') as $c) {
                if (in_array($c->codigo, $existentes, true)) {
                    continue;
                }
                $nat = match ($c->naturaleza) {
                    'ingreso', 'grupo-ingreso' => 'ingreso',
                    'disponible' => 'disponible',
                    default => 'gasto',
                };
                $this->upsert((int) $centro->id, $c->codigo, $c->nombre, $nat, !str_starts_with($c->naturaleza, 'grupo'));
                $this->proponerOficial((int) $centro->id, $c->codigo);
                $anadidas++;
            }

            return ContestarJson::ok([
                'cuentas' => $this->cuentasDe((int) $centro->id),
                'modelos' => $this->modelos(),
                'anadidas' => $anadidas,
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    /** Borra un modelo guardado. El modelo de base (el plan Club) no: es el plan del centro. */
    public function borrarModelo(Request $request, array $vars = []): Response
    {
        try {
            $this->exigirClub();
            $modelo = $this->modelo(trim((string) ($request->json()['codigo'] ?? '')));
            if ($modelo === null || !$modelo['borrable']) {
                throw new InvalidArgumentException(_('Ese modelo no se puede eliminar'));
            }
            $this->planes->borrar($modelo['id']);

            return ContestarJson::ok(['modelos' => $this->modelos()]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    private function exigirClub(): Centro
    {
        $centro = $this->centros->porId($this->ambito->ejecutar()->centroId);
        if ($centro === null || $centro->id === null || !CatalogoPlanesContables::esClub($centro->planContableCodigo)) {
            throw new InvalidArgumentException(_('Las cuentas propias son de una associació'));
        }

        return $centro;
    }

    /**
     * Modelos de cuentas para una associació: el de base (el plan Club) y los guardados.
     *
     * @return list<array{id:int,codigo:string,nombre:string,borrable:bool}>
     */
    private function modelos(): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, codigo, nombre, modelo_de FROM planes_contables
             WHERE codigo = :club OR modelo_de = :club
             ORDER BY (modelo_de IS NOT NULL), nombre'
        );
        $st->execute([':club' => CatalogoPlanesContables::CLUB]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'codigo' => (string) $row['codigo'],
                'nombre' => (string) $row['nombre'],
                'borrable' => $row['modelo_de'] !== null,
            ];
        }

        return $out;
    }

    /** @return array{id:int,codigo:string,nombre:string,borrable:bool}|null */
    private function modelo(string $codigo): ?array
    {
        foreach ($this->modelos() as $modelo) {
            if ($modelo['codigo'] === $codigo) {
                return $modelo;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function cuentasDe(int $centroId): array
    {
        $st = $this->pdo->prepare(
            "SELECT codigo, nombre, tipo, imputable, orden, codigo_oficial FROM cuentas
             WHERE centro_id = :c AND libro = 'G' AND persona_id IS NULL
               AND tipo IN ('ingreso', 'gasto', 'patrimonio') AND activo = TRUE
             ORDER BY orden, codigo"
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $tipo = (string) $row['tipo'];
            $out[] = [
                'codigo' => (string) $row['codigo'],
                'nombre' => (string) $row['nombre'],
                'tipo' => $tipo,
                'imputable' => (bool) $row['imputable'],
                'codigo_oficial' => (string) ($row['codigo_oficial'] ?? ''),
                'fija' => PdoCuentasClub::esFija((string) $row['codigo']),
                'naturaleza_plan' => $tipo === 'ingreso' ? 'ingreso' : ($tipo === 'patrimonio' ? 'disponible' : 'gasto'),
            ];
        }
        usort($out, static fn (array $a, array $b): int => CuentasPropiasClub::cmpCodigo($a['codigo'], $b['codigo']));

        return $out;
    }

    private function upsert(int $centroId, string $codigo, string $nombre, string $naturaleza, bool $imputable): void
    {
        [$tipo, $nat] = match ($naturaleza) {
            'ingreso' => ['ingreso', 'acreedora'],
            'disponible' => ['patrimonio', 'acreedora'],
            default => ['gasto', 'deudora'],
        };
        AmbitoSeeder::upsertCuenta($this->pdo, [
            'centro_id' => $centroId,
            'persona_id' => null,
            'cuenta_fisica_id' => null,
            'padre_id' => null,
            'libro' => 'G',
            'codigo' => $codigo,
            'nombre' => $nombre,
            'descripcion' => $nombre,
            'tipo' => $tipo,
            'naturaleza' => $nat,
            'codigo_maestro' => $codigo,
            'imputable' => $imputable,
            'orden' => 200,
        ]);
    }

    /** Cuenta del plan oficial: de dos a cuatro cifras, o vacío para quitarla. */
    private function oficialValido(mixed $valor): ?string
    {
        $oficial = trim((string) ($valor ?? ''));
        if ($oficial === '') {
            return null;
        }
        if (preg_match('/^[0-9]{2,4}$/', $oficial) !== 1) {
            throw new InvalidArgumentException(_('El código oficial son de dos a cuatro cifras, por ejemplo 640'));
        }

        return $oficial;
    }

    private function ponerOficial(int $centroId, string $codigo, ?string $oficial): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE cuentas SET codigo_oficial = :o
             WHERE centro_id = :c AND libro = 'G' AND persona_id IS NULL AND codigo = :cod
               AND tipo IN ('ingreso', 'gasto', 'patrimonio')"
        );
        $st->execute([':o' => $oficial, ':c' => $centroId, ':cod' => $codigo]);

        return $st->rowCount() > 0;
    }

    /** Al copiar un modelo: propone el código oficial conocido sin pisar el que haya. */
    private function proponerOficial(int $centroId, string $codigo): void
    {
        $oficial = CatalogoConceptosClub::codigoOficial($codigo);
        if ($oficial === null) {
            return;
        }
        $st = $this->pdo->prepare(
            "UPDATE cuentas SET codigo_oficial = :o
             WHERE centro_id = :c AND libro = 'G' AND persona_id IS NULL AND codigo = :cod
               AND codigo_oficial IS NULL"
        );
        $st->execute([':o' => $oficial, ':c' => $centroId, ':cod' => $codigo]);
    }

    private function codigoLibre(string $nombre): string
    {
        $base = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $nombre));
        $base = substr($base !== '' ? $base : 'plan', 0, 12);
        if (strlen($base) < 2) {
            $base = 'pl' . $base;
        }
        $codigo = $base;
        $n = 2;
        while ($this->planes->idPorCodigo($codigo) !== null) {
            $sufijo = (string) $n;
            $codigo = substr($base, 0, 16 - strlen($sufijo)) . $sufijo;
            $n++;
        }

        return $codigo;
    }
}
