<?php

declare(strict_types=1);

namespace src\disponible\infrastructure\persistence;

use PDO;
use src\disponible\domain\contracts\TramosDesgravacionRepository;
use src\disponible\domain\services\TramosDesgravacion;

final class PdoTramosDesgravacionRepository implements TramosDesgravacionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function deCentro(int $centroId): array
    {
        $st = $this->pdo->prepare('SELECT desgravacion_tramos_json FROM centros WHERE id = :id');
        $st->execute([':id' => $centroId]);
        $raw = $st->fetchColumn();
        if ($raw === false || $raw === null) {
            return TramosDesgravacion::desempaquetar([]);
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return TramosDesgravacion::desempaquetar([]);
        }

        return TramosDesgravacion::desempaquetar($raw);
    }

    public function guardar(int $centroId, array $tramos, int $maximoPct): void
    {
        $pack = TramosDesgravacion::empaquetar($tramos, $maximoPct);
        $st = $this->pdo->prepare(
            'UPDATE centros SET desgravacion_tramos_json = CAST(:j AS jsonb) WHERE id = :id'
        );
        $st->execute([
            ':j' => json_encode($pack, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            ':id' => $centroId,
        ]);
    }
}
