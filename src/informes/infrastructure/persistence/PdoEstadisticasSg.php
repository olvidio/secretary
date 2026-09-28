<?php

declare(strict_types=1);

namespace src\informes\infrastructure\persistence;

use PDO;
use src\informes\domain\contracts\EstadisticasSg;

final class PdoEstadisticasSg implements EstadisticasSg
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function aportacionesOrdinarias(int $centroId, int $ejercicioId, string $desde, string $hasta): array
    {
        $numS = $this->pdo->prepare(
            "SELECT COUNT(*) FROM persona_sg s
             INNER JOIN personas p ON p.id = s.persona_id
             WHERE p.centro_id = :c AND s.clase = 's'"
        );
        $numS->execute([':c' => $centroId]);

        $aport = $this->pdo->prepare(
            "SELECT COUNT(*) FROM movimientos m
             INNER JOIN cuentas c ON c.id = m.cuenta_id
             INNER JOIN asientos a ON a.id = m.asiento_id
             WHERE c.centro_id = :c AND c.libro = 'G' AND c.codigo = '11'
               AND a.ejercicio_id = :ej AND a.anulado_at IS NULL
               AND a.fecha >= :desde AND a.fecha <= :hasta
               AND (m.haber - m.debe) <> 0"
        );
        $aport->execute([':c' => $centroId, ':ej' => $ejercicioId, ':desde' => $desde, ':hasta' => $hasta]);

        $sin = $this->pdo->prepare(
            "SELECT COUNT(*) FROM persona_sg s
             INNER JOIN personas p ON p.id = s.persona_id
             WHERE p.centro_id = :c AND s.clase = 's'
               AND NOT EXISTS (
                 SELECT 1 FROM asientos a
                 INNER JOIN movimientos m ON m.asiento_id = a.id
                 INNER JOIN cuentas cu ON cu.id = m.cuenta_id
                 WHERE a.persona_id = p.id
                   AND cu.codigo = '11' AND cu.libro = 'G'
                   AND a.ejercicio_id = :ej AND a.anulado_at IS NULL
                   AND a.fecha >= :desde AND a.fecha <= :hasta
                   AND (m.haber - m.debe) <> 0
               )"
        );
        $sin->execute([':c' => $centroId, ':ej' => $ejercicioId, ':desde' => $desde, ':hasta' => $hasta]);

        return [
            'num_s' => (int) $numS->fetchColumn(),
            'aportaciones' => (int) $aport->fetchColumn(),
            'sin_aportacion' => (int) $sin->fetchColumn(),
        ];
    }
}
