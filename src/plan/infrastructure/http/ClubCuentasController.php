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
use src\plan\domain\services\CatalogoPlanesContables;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

/** Cuentas propias de una associació y plantillas de plan (ámbito club). */
final class ClubCuentasController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ResolverAmbitoActual $ambito,
        private readonly CentroRepository $centros,
        private readonly PlanContableRepository $planes,
        private readonly PlanConceptoRepository $conceptos,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        try {
            $centro = $this->exigirClub();

            return ContestarJson::ok([
                'cuentas' => $this->cuentasDe((int) $centro->id),
                'plantillas' => $this->plantillas(),
                'plan' => $centro->planContableCodigo,
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
            $this->upsert((int) $centro->id, $codigo, $nombre, $naturaleza, true);

            return ContestarJson::ok(['cuentas' => $this->cuentasDe((int) $centro->id)]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function guardarPlantilla(Request $request, array $vars = []): Response
    {
        try {
            $centro = $this->exigirClub();
            $nombre = trim((string) ($request->json()['nombre'] ?? ''));
            if ($nombre === '') {
                throw new InvalidArgumentException(_('La plantilla necesita un nombre'));
            }
            $codigo = $this->codigoLibre($nombre);
            $plan = $this->planes->guardar(null, $codigo, $nombre);
            $filas = [];
            $orden = 10;
            foreach ($this->cuentasDe((int) $centro->id) as $cuenta) {
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
            $this->conceptos->reemplazar($plan['id'], $filas);

            return ContestarJson::ok(['plantillas' => $this->plantillas(), 'plan' => $plan]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function aplicar(Request $request, array $vars = []): Response
    {
        try {
            $centro = $this->exigirClub();
            $codigo = trim((string) ($request->json()['codigo'] ?? ''));
            $planId = $this->planes->idPorCodigo($codigo);
            if ($planId === null || !CatalogoPlanesContables::esClub($codigo)) {
                throw new InvalidArgumentException(_('Elija una plantilla de associació'));
            }
            $this->centros->guardar(new Centro(
                $centro->id,
                $centro->codigo,
                $centro->nombre,
                $centro->tipo,
                $centro->tipoCierre,
                $codigo,
                $centro->activo,
            ));
            foreach ($this->conceptos->listar($planId, 'G') as $c) {
                $imputable = !str_starts_with($c->naturaleza, 'grupo');
                $nat = match ($c->naturaleza) {
                    'ingreso', 'grupo-ingreso' => 'ingreso',
                    'disponible' => 'disponible',
                    default => 'gasto',
                };
                $this->upsert((int) $centro->id, $c->codigo, $c->nombre, $nat, $imputable);
            }

            return ContestarJson::ok([
                'cuentas' => $this->cuentasDe((int) $centro->id),
                'plan' => $codigo,
                'plantillas' => $this->plantillas(),
            ]);
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

    /** @return list<array{codigo:string,nombre:string}> */
    private function plantillas(): array
    {
        $out = [];
        foreach ($this->planes->listar() as $plan) {
            if (CatalogoPlanesContables::esClub($plan['codigo'])) {
                $out[] = ['codigo' => $plan['codigo'], 'nombre' => $plan['nombre']];
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function cuentasDe(int $centroId): array
    {
        $st = $this->pdo->prepare(
            "SELECT codigo, nombre, tipo, imputable, orden FROM cuentas
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
                'naturaleza_plan' => $tipo === 'ingreso' ? 'ingreso' : ($tipo === 'patrimonio' ? 'disponible' : 'gasto'),
            ];
        }
        usort($out, static fn (array $a, array $b): int => self::cmpCodigo($a['codigo'], $b['codigo']));

        return $out;
    }

    /** 61.6, 61.7, 61.10: cada tramo numérico, y el grupo antes que sus hijas. */
    private static function cmpCodigo(string $a, string $b): int
    {
        $pa = explode('.', $a);
        $pb = explode('.', $b);
        $n = max(count($pa), count($pb));
        for ($i = 0; $i < $n; $i++) {
            $xa = $pa[$i] ?? '';
            $xb = $pb[$i] ?? '';
            if ($xa === $xb) {
                continue;
            }
            if (ctype_digit($xa) && ctype_digit($xb)) {
                return (int) $xa <=> (int) $xb;
            }

            return strnatcasecmp($xa, $xb);
        }

        return 0;
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
