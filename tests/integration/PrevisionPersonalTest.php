<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\presupuestos\domain\entity\LineaPrevisionPersonal;
use src\presupuestos\infrastructure\persistence\PdoPrevisionPersonalRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class PrevisionPersonalTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_prevision';

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->saltarSiNoHayPgsql();
        try {
            $this->pdo = $this->prepararBaseDeTestVacia(self::DB_NAME);
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base: ' . $e->getMessage());
        }
        (new SchemaInstaller($this->pdo))->install();
    }

    public function testGuardarYListarPorPersonaYEjercicio(): void
    {
        $centroId = (int) $this->pdo->query('SELECT id FROM centros ORDER BY id LIMIT 1')->fetchColumn();
        $ejercicioId = (int) $this->pdo->query('SELECT id FROM ejercicios ORDER BY id LIMIT 1')->fetchColumn();
        self::assertGreaterThan(0, $centroId);
        self::assertGreaterThan(0, $ejercicioId);

        $ins = $this->pdo->prepare(
            'INSERT INTO personas (nombre, apellidos, iniciales, centro_id, activo, orden)
             VALUES (:n, :a, :i, :c, TRUE, 1) RETURNING id'
        );
        $ins->execute([':n' => 'Ana', ':a' => 'Uno', ':i' => 'au', ':c' => $centroId]);
        $ana = (int) $ins->fetchColumn();
        $ins->execute([':n' => 'Bea', ':a' => 'Dos', ':i' => 'bd', ':c' => $centroId]);
        $bea = (int) $ins->fetchColumn();

        $repo = new PdoPrevisionPersonalRepository($this->pdo);
        $repo->reemplazarDePersona($ejercicioId, $ana, [
            new LineaPrevisionPersonal($ejercicioId, $ana, '111', 120000),
            new LineaPrevisionPersonal($ejercicioId, $ana, '24', 45000),
        ]);
        $repo->reemplazarDePersona($ejercicioId, $bea, [
            new LineaPrevisionPersonal($ejercicioId, $bea, '111', 80000),
        ]);

        $deAna = $repo->listarDePersona($ejercicioId, $ana);
        self::assertCount(2, $deAna);
        self::assertSame(120000, $deAna[0]->previstoCents);
        self::assertSame('111', $deAna[0]->conceptoCodigo);

        $todas = $repo->listarDeEjercicio($ejercicioId);
        self::assertCount(3, $todas);

        $repo->reemplazarDePersona($ejercicioId, $ana, [
            new LineaPrevisionPersonal($ejercicioId, $ana, '22', 1000),
        ]);
        self::assertCount(1, $repo->listarDePersona($ejercicioId, $ana));
        self::assertSame('22', $repo->listarDePersona($ejercicioId, $ana)[0]->conceptoCodigo);
    }
}
