<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use PDO;
use src\conceptos\domain\services\ReglasConceptoPlan;
use src\plan\domain\contracts\PlanConceptoRepository;

final class GuardarConceptosPlan
{
    public function __construct(
        private readonly PlanConceptoRepository $planConceptos,
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<array<string, mixed>>
     */
    public function ejecutar(int $planId, array $datos): array
    {
        $raw = $datos['conceptos'] ?? [];
        if (!is_array($raw)) {
            throw new InvalidArgumentException(_("Formato de conceptos no válido"));
        }
        $conceptos = $this->normalizar($raw);
        $this->planConceptos->reemplazar($planId, $conceptos);
        $this->sincronizarCuentasCentros($planId, $conceptos);

        return array_map(static fn (array $c): array => [
            'codigo' => $c['codigo'],
            'cuenta' => $c['cuenta'],
            'nombre' => $c['nombre'],
            'descripcion' => $c['descripcion'],
            'naturaleza' => $c['naturaleza'],
            'orden' => $c['orden'],
            'etiqueta' => $c['codigo'] . ' ' . $c['nombre'],
        ], $conceptos);
    }

    /**
     * @param list<mixed> $raw
     * @return list<array{codigo:string,cuenta:string,nombre:string,descripcion:string,naturaleza:string,orden:int}>
     */
    private function normalizar(array $raw): array
    {
        if ($raw === []) {
            throw new InvalidArgumentException(_("Debe haber al menos un concepto"));
        }
        $out = [];
        $vistos = [];
        foreach ($raw as $fila) {
            if (!is_array($fila)) {
                throw new InvalidArgumentException(_("Cada concepto debe ser un objeto"));
            }
            $codigo = trim((string) ($fila['codigo'] ?? ''));
            $cuenta = strtoupper(trim((string) ($fila['cuenta'] ?? '')));
            $nombre = trim((string) ($fila['nombre'] ?? ''));
            $descripcion = trim((string) ($fila['descripcion'] ?? ''));
            $naturaleza = strtolower(trim((string) ($fila['naturaleza'] ?? '')));
            $orden = (int) ($fila['orden'] ?? 0);
            if ($codigo === '' || !in_array($cuenta, ['P', 'G'], true)) {
                throw new InvalidArgumentException(_("Código y cuenta (P/G) son obligatorios"));
            }
            if ($nombre === '') {
                throw new InvalidArgumentException(sprintf(_("Nombre obligatorio en %s/%s"), $cuenta, $codigo));
            }
            if (!in_array($naturaleza, ReglasConceptoPlan::naturalezasValidas(), true)) {
                throw new InvalidArgumentException(sprintf(_("Naturaleza no válida en %s"), $codigo));
            }
            $clave = $cuenta . ':' . $codigo;
            if (isset($vistos[$clave])) {
                throw new InvalidArgumentException(sprintf(_("Concepto duplicado: %s"), $clave));
            }
            $vistos[$clave] = true;
            $out[] = [
                'codigo' => $codigo,
                'cuenta' => $cuenta,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'orden' => $orden,
                'naturaleza' => $naturaleza,
            ];
        }
        usort($out, static fn (array $a, array $b): int => [$a['cuenta'], $a['orden'], $a['codigo']]
            <=> [$b['cuenta'], $b['orden'], $b['codigo']]);

        return $out;
    }

    /**
     * @param list<array{codigo:string,cuenta:string,nombre:string,descripcion:string,naturaleza:string,orden:int}> $conceptos
     */
    private function sincronizarCuentasCentros(int $planId, array $conceptos): void
    {
        $st = $this->pdo->prepare('SELECT id FROM centros WHERE plan_contable_id = :p');
        $st->execute([':p' => $planId]);
        $centroIds = array_map(static fn (array $r): int => (int) $r['id'], $st->fetchAll());
        if ($centroIds === []) {
            return;
        }
        $upd = $this->pdo->prepare(
            'UPDATE cuentas SET nombre = :nombre, descripcion = :descripcion, orden = :orden
             WHERE centro_id = :c AND libro = :libro AND codigo = :codigo AND persona_id IS NULL'
        );
        foreach ($centroIds as $centroId) {
            foreach ($conceptos as $c) {
                if (ReglasConceptoPlan::esCapituloVII($c['codigo'], $c['cuenta'])) {
                    continue;
                }
                $upd->execute([
                    ':nombre' => $c['nombre'],
                    ':descripcion' => $c['descripcion'],
                    ':orden' => $c['orden'],
                    ':c' => $centroId,
                    ':libro' => $c['cuenta'],
                    ':codigo' => $c['codigo'],
                ]);
            }
        }
    }
}
