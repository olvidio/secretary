<?php

declare(strict_types=1);

namespace src\ambito\infrastructure\persistence;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use src\ambito\domain\contracts\CuentaFisicaRepository;
use src\ambito\domain\entity\CuentaFisica;

final class PdoCuentaFisicaRepository implements CuentaFisicaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listarDeCentro(int $centroId): array
    {
        $st = $this->pdo->prepare('SELECT * FROM cuentas_fisicas WHERE centro_id = :c ORDER BY orden, id');
        $st->execute([':c' => $centroId]);

        return array_map($this->hydrate(...), $st->fetchAll());
    }

    public function listarActivasDeCentro(int $centroId, ?string $tipo = null): array
    {
        $sql = 'SELECT * FROM cuentas_fisicas WHERE centro_id = :c AND activo = TRUE';
        $params = [':c' => $centroId];
        if ($tipo !== null) {
            $sql .= ' AND tipo = :t';
            $params[':t'] = $tipo;
        }
        $sql .= ' ORDER BY orden, id';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return array_map($this->hydrate(...), $st->fetchAll());
    }

    public function porId(int $id): ?CuentaFisica
    {
        $st = $this->pdo->prepare('SELECT * FROM cuentas_fisicas WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function maxOrdenPorTipo(int $centroId, string $tipo): int
    {
        $st = $this->pdo->prepare(
            'SELECT COALESCE(MAX(orden), 0) FROM cuentas_fisicas WHERE centro_id = :c AND tipo = :t'
        );
        $st->execute([':c' => $centroId, ':t' => $tipo]);

        return (int) $st->fetchColumn();
    }

    public function contarActivasPorTipo(int $centroId, string $tipo): int
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM cuentas_fisicas WHERE centro_id = :c AND tipo = :t AND activo = TRUE'
        );
        $st->execute([':c' => $centroId, ':t' => $tipo]);

        return (int) $st->fetchColumn();
    }

    public function guardar(CuentaFisica $cuentaFisica): CuentaFisica
    {
        try {
            if ($cuentaFisica->id === null) {
                $st = $this->pdo->prepare(
                    'INSERT INTO cuentas_fisicas (centro_id, tipo, nombre, iban, orden, activo)
                     VALUES (:c, :t, :n, :i, :o, :a) RETURNING id'
                );
                $st->execute([
                    ':c' => $cuentaFisica->centroId,
                    ':t' => $cuentaFisica->tipo,
                    ':n' => $cuentaFisica->nombre,
                    ':i' => $cuentaFisica->iban,
                    ':o' => $cuentaFisica->orden,
                    ':a' => (int) $cuentaFisica->activo,
                ]);
                $id = (int) $st->fetchColumn();
            } else {
                $st = $this->pdo->prepare(
                    'UPDATE cuentas_fisicas SET tipo = :t, nombre = :n, iban = :i, orden = :o, activo = :a
                     WHERE id = :id'
                );
                $st->execute([
                    ':t' => $cuentaFisica->tipo,
                    ':n' => $cuentaFisica->nombre,
                    ':i' => $cuentaFisica->iban,
                    ':o' => $cuentaFisica->orden,
                    ':a' => (int) $cuentaFisica->activo,
                    ':id' => $cuentaFisica->id,
                ]);
                $id = $cuentaFisica->id;
            }
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), '23505')
                || str_contains($e->getMessage(), 'cuentas_fisicas_centro_id_nombre')) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Ya existe una cuenta física con el nombre «%s» en este centro',
                        $cuentaFisica->nombre
                    ),
                    0,
                    $e,
                );
            }
            throw $e;
        }
        $st = $this->pdo->prepare('SELECT * FROM cuentas_fisicas WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Cuenta física no encontrada tras guardar');
        }

        return $this->hydrate($row);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): CuentaFisica
    {
        return new CuentaFisica(
            (int) $row['id'],
            (int) $row['centro_id'],
            (string) $row['tipo'],
            (string) $row['nombre'],
            $row['iban'] !== null ? (string) $row['iban'] : null,
            (int) $row['orden'],
            (bool) $row['activo'],
        );
    }
}
