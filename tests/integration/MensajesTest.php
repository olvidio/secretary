<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\mensajes\infrastructure\persistence\PdoMensajeRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class MensajesTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_mensajes';

    private PDO $pdo;
    private PdoMensajeRepository $mensajes;

    protected function setUp(): void
    {
        $this->saltarSiNoHayPgsql();
        try {
            $this->pdo = $this->prepararBaseDeTestVacia(self::DB_NAME);
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base: ' . $e->getMessage());
        }
        (new SchemaInstaller($this->pdo))->install();
        $this->mensajes = new PdoMensajeRepository($this->pdo);
    }

    public function testDescartarOcultaHastaQueCambiaElAviso(): void
    {
        $this->pdo->exec("INSERT INTO identidades (email, alias, password_hash, nombre)
            VALUES ('m@test.local', 'm', 'x', 'M')");
        $identidadId = (int) $this->pdo->lastInsertId('identidades_id_seq');
        $payload = '{"quien":"ab","lineas":[{"codigo":"71","etiqueta":"X","cents":100}]}';
        $this->mensajes->upsert($identidadId, 'destinos:1:2', 'destinos_7', $payload);
        $lista = $this->mensajes->listarAbiertos($identidadId);
        self::assertCount(1, $lista);
        $id = $lista[0]['id'];
        self::assertTrue($this->mensajes->descartar($identidadId, $id));
        self::assertSame([], $this->mensajes->listarAbiertos($identidadId));
        $this->mensajes->upsert($identidadId, 'destinos:1:2', 'destinos_7', $payload);
        self::assertSame([], $this->mensajes->listarAbiertos($identidadId));
        $payload2 = '{"quien":"ab","lineas":[{"codigo":"71","etiqueta":"X","cents":200}]}';
        $this->mensajes->upsert($identidadId, 'destinos:1:2', 'destinos_7', $payload2);
        self::assertCount(1, $this->mensajes->listarAbiertos($identidadId));
    }
}
