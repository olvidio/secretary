<?php

declare(strict_types=1);

namespace src\arqueo\infrastructure\persistence;

use PDO;
use src\asientos\domain\contracts\AsientoRepository;

/** Fecha en la que caja y banco de una associació se dieron por cuadrados. */
final class PdoArqueoCuadrado
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly AsientoRepository $asientos,
    ) {
    }

    /**
     * @return list<array{id:int,nombre:string,tipo:string,saldo_cents:int}>
     */
    public function tesoreria(int $centroId, string $fecha): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, nombre, tipo FROM cuentas_fisicas
             WHERE centro_id = :c AND activo = TRUE AND tipo IN (\'caja\', \'banco\')
             ORDER BY orden, id'
        );
        $st->execute([':c' => $centroId]);
        $saldos = [];
        foreach ($this->asientos->saldosTesoreriaHasta($centroId, $fecha) as $fila) {
            if ($fila['libro'] !== 'G' || $fila['cuenta_fisica_id'] === null) {
                continue;
            }
            $saldos[$fila['cuenta_fisica_id']] = ($saldos[$fila['cuenta_fisica_id']] ?? 0) + $fila['saldo_cents'];
        }
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $id = (int) $row['id'];
            $out[] = [
                'id' => $id,
                'nombre' => (string) $row['nombre'],
                'tipo' => (string) $row['tipo'],
                'saldo_cents' => $saldos[$id] ?? 0,
            ];
        }

        return $out;
    }

    /** @return list<array{fecha:string,saldos:array<string,int>,cuadra:bool,avisos:list<string>}> */
    public function listar(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT fecha::text AS fecha, saldos FROM arqueo_cuadrados
             WHERE centro_id = :c ORDER BY fecha DESC'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $fecha = (string) $row['fecha'];
            $guardados = $this->saldosDe((string) $row['saldos']);
            $avisos = $this->diferencias($centroId, $fecha, $guardados);
            $out[] = [
                'fecha' => $fecha,
                'saldos' => $guardados,
                'cuadra' => $avisos === [],
                'avisos' => $avisos,
            ];
        }

        return $out;
    }

    /** @param array<string, int> $contados */
    public function cuadrar(int $centroId, string $fecha, array $contados): void
    {
        $actual = [];
        foreach ($this->tesoreria($centroId, $fecha) as $fila) {
            $contado = $contados[(string) $fila['id']] ?? null;
            if ($contado === null || (int) $contado !== $fila['saldo_cents']) {
                throw new \InvalidArgumentException(_('El recuento no cuadra con el saldo contable'));
            }
            $actual[(string) $fila['id']] = $fila['saldo_cents'];
        }
        if ($actual === []) {
            throw new \InvalidArgumentException(_('No hay tesorería que arquear'));
        }
        $this->pdo->prepare(
            'INSERT INTO arqueo_cuadrados (centro_id, fecha, saldos)
             VALUES (:c, :f, :s)
             ON CONFLICT (centro_id, fecha) DO UPDATE SET saldos = EXCLUDED.saldos'
        )->execute([
            ':c' => $centroId,
            ':f' => $fecha,
            ':s' => json_encode($actual, JSON_THROW_ON_ERROR),
        ]);
    }

    /** @return list<string> */
    public function avisos(int $centroId, string $fechaAfectada): array
    {
        $st = $this->pdo->prepare(
            'SELECT fecha::text AS fecha, saldos FROM arqueo_cuadrados
             WHERE centro_id = :c AND fecha >= :f ORDER BY fecha'
        );
        $st->execute([':c' => $centroId, ':f' => $fechaAfectada]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            foreach ($this->diferencias($centroId, (string) $row['fecha'], $this->saldosDe((string) $row['saldos'])) as $aviso) {
                $out[] = $aviso;
            }
        }

        return $out;
    }

    /**
     * @param array<string, int> $guardados
     * @return list<string>
     */
    private function diferencias(int $centroId, string $fecha, array $guardados): array
    {
        $actual = [];
        foreach ($this->tesoreria($centroId, $fecha) as $fila) {
            $actual[(string) $fila['id']] = $fila;
        }
        $out = [];
        $fechaEs = substr($fecha, 8, 2) . '/' . substr($fecha, 5, 2) . '/' . substr($fecha, 0, 4);
        foreach ($guardados as $id => $cents) {
            $nombre = $actual[$id]['nombre'] ?? $id;
            $ahora = $actual[$id]['saldo_cents'] ?? 0;
            if ($ahora !== (int) $cents) {
                $out[] = sprintf(_('El arqueo del %s ya no cuadra (%s).'), $fechaEs, $nombre);
            }
        }

        return $out;
    }

    /** @return array<string, int> */
    private function saldosDe(string $json): array
    {
        $datos = json_decode($json, true);
        if (!is_array($datos)) {
            return [];
        }
        $out = [];
        foreach ($datos as $id => $cents) {
            $out[(string) $id] = (int) $cents;
        }

        return $out;
    }
}
