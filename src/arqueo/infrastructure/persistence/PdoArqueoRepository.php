<?php

declare(strict_types=1);

namespace src\arqueo\infrastructure\persistence;

use DateTimeImmutable;
use PDO;
use src\arqueo\domain\contracts\ArqueoRepository;
use src\arqueo\domain\entity\Arqueo;
use src\shared\domain\value_objects\Dinero;
use src\shared\infrastructure\persistence\ConverterDate;

final class PdoArqueoRepository implements ArqueoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ultimo(string $cuenta): ?Arqueo
    {
        $st = $this->pdo->prepare('SELECT * FROM arqueos WHERE cuenta = :c ORDER BY id DESC LIMIT 1');
        $st->execute([':c' => $cuenta]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function ultimoPorFisica(int $cuentaFisicaId): ?Arqueo
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM arqueos WHERE cuenta_fisica_id = :f ORDER BY id DESC LIMIT 1'
        );
        $st->execute([':f' => $cuentaFisicaId]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function guardar(Arqueo $arqueo): Arqueo
    {
        $sql = 'INSERT INTO arqueos (cuenta, fecha, desglose_json, total_dinero, total_vales, total,
                ejercicio_id, cuenta_fisica_id, total_cents)
             VALUES (:c, :f, :d, :td, :tv, :t, :ej, :cf, :tc)';
        $params = [
            ':c' => $arqueo->cuenta,
            ':f' => (new ConverterDate('date', $arqueo->fecha))->toPg(),
            ':d' => json_encode($arqueo->desglose, JSON_UNESCAPED_UNICODE),
            ':td' => $arqueo->totalDinero->toString(),
            ':tv' => $arqueo->totalVales->toString(),
            ':t' => $arqueo->total->toString(),
            ':ej' => $arqueo->ejercicioId,
            ':cf' => $arqueo->cuentaFisicaId,
            ':tc' => $arqueo->total->toCents(),
        ];
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $st = $this->pdo->prepare($sql . ' RETURNING id');
            $st->execute($params);
            $id = (int) $st->fetchColumn();
            $st = $this->pdo->prepare('SELECT * FROM arqueos WHERE id = :id');
            $st->execute([':id' => $id]);
            $row = $st->fetch();
            if (!is_array($row)) {
                throw new \RuntimeException('No se pudo guardar el arqueo');
            }

            return $this->hydrate($row);
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $saved = $arqueo->cuentaFisicaId !== null
            ? $this->ultimoPorFisica($arqueo->cuentaFisicaId)
            : $this->ultimo($arqueo->cuenta);
        if ($saved === null) {
            throw new \RuntimeException('No se pudo guardar el arqueo');
        }

        return $saved;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Arqueo
    {
        $fecha = (new ConverterDate('date', $row['fecha']))->fromPg() ?? new DateTimeImmutable('1970-01-01');
        $desglose = json_decode((string) $row['desglose_json'], true);
        if (!is_array($desglose)) {
            $desglose = [];
        }

        return new Arqueo(
            (int) $row['id'],
            (string) $row['cuenta'],
            $fecha,
            $desglose,
            new Dinero((string) ($row['total_dinero'] ?: '0.00')),
            new Dinero((string) ($row['total_vales'] ?: '0.00')),
            new Dinero((string) ($row['total'] ?: '0.00')),
            isset($row['cuenta_fisica_id']) ? (int) $row['cuenta_fisica_id'] : null,
            isset($row['ejercicio_id']) ? (int) $row['ejercicio_id'] : null,
        );
    }
}
