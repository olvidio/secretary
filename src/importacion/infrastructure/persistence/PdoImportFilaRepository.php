<?php

declare(strict_types=1);

namespace src\importacion\infrastructure\persistence;

use PDO;
use src\importacion\domain\contracts\ImportFilaRepository;
use src\importacion\domain\entity\FilaImportada;

final class PdoImportFilaRepository implements ImportFilaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function contarDeEjercicio(int $ejercicioId): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM import_filas WHERE ejercicio_id = :ej');
        $st->execute([':ej' => $ejercicioId]);

        return (int) $st->fetchColumn();
    }

    public function listarDeEjercicio(int $ejercicioId): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM import_filas WHERE ejercicio_id = :ej ORDER BY hoja, fila'
        );
        $st->execute([':ej' => $ejercicioId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    public function guardar(FilaImportada $fila): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO import_filas (ejercicio_id, hoja, fila, hash_contenido, asiento_id)
             VALUES (:ej, :hoja, :fila, :hash, :asiento)
             ON CONFLICT (ejercicio_id, hoja, fila) DO UPDATE SET
                hash_contenido = excluded.hash_contenido,
                asiento_id = excluded.asiento_id'
        );
        $st->execute([
            ':ej' => $fila->ejercicioId,
            ':hoja' => $fila->hoja,
            ':fila' => $fila->fila,
            ':hash' => $fila->hashContenido,
            ':asiento' => $fila->asientoId,
        ]);
    }

    public function borrarClave(int $ejercicioId, string $hoja, int $fila): void
    {
        $st = $this->pdo->prepare(
            'DELETE FROM import_filas WHERE ejercicio_id = :ej AND hoja = :hoja AND fila = :fila'
        );
        $st->execute([
            ':ej' => $ejercicioId,
            ':hoja' => $hoja,
            ':fila' => $fila,
        ]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): FilaImportada
    {
        return new FilaImportada(
            (int) $row['ejercicio_id'],
            (string) $row['hoja'],
            (int) $row['fila'],
            (string) $row['hash_contenido'],
            isset($row['asiento_id']) ? (int) $row['asiento_id'] : null,
            (int) $row['id'],
        );
    }
}
