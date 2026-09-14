<?php

declare(strict_types=1);

namespace Tests\integration;

use DateTimeImmutable;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ayuda\domain\value_objects\OrigenRespuesta;
use src\ayuda\domain\value_objects\PreguntaAyuda;
use src\ayuda\domain\value_objects\RespuestaAyuda;
use src\ayuda\infrastructure\persistence\PdoRegistroConsultasAyuda;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class AyudaConsultasTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_ayuda';

    private PDO $pdo;
    private PdoRegistroConsultasAyuda $registro;

    protected function setUp(): void
    {
        $this->saltarSiNoHayPgsql();
        try {
            $this->pdo = $this->prepararBaseDeTestVacia(self::DB_NAME);
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base: ' . $e->getMessage());
        }
        (new SchemaInstaller($this->pdo))->install();
        $this->registro = new PdoRegistroConsultasAyuda($this->pdo);
    }

    public function testLaRespuestaResueltaSeReutiliza(): void
    {
        $pregunta = new PreguntaAyuda('¿Cómo hago un traspaso?');
        $respuesta = new RespuestaAyuda('Con el concepto 41.', ['traspasos'], OrigenRespuesta::Ia);

        $this->registro->guardar(null, $pregunta, 'huella-1', $respuesta);
        $guardada = $this->registro->buscar('huella-1');

        self::assertNotNull($guardada);
        self::assertSame('Con el concepto 41.', $guardada->texto);
        self::assertSame(['traspasos'], $guardada->fuentes);
    }

    public function testLaRespuestaSinRespaldoNoSeReutiliza(): void
    {
        $pregunta = new PreguntaAyuda('¿Cómo recalculo todo?');

        $this->registro->guardar(
            null,
            $pregunta,
            'huella-2',
            RespuestaAyuda::sinRespuesta(OrigenRespuesta::Ia),
        );

        self::assertNull($this->registro->buscar('huella-2'));
    }

    /** Las preguntas sin respuesta quedan registradas: son la lista de tareas del manual. */
    public function testLasPreguntasSinRespuestaQuedanRegistradas(): void
    {
        $this->registro->guardar(
            null,
            new PreguntaAyuda('¿Cómo recalculo todo?'),
            'huella-3',
            RespuestaAyuda::sinRespuesta(OrigenRespuesta::Ia),
        );

        $st = $this->pdo->query('SELECT pregunta FROM ayuda_consultas WHERE NOT resuelta');
        self::assertNotFalse($st);
        self::assertSame(['¿Cómo recalculo todo?'], $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testSoloCuentanParaElLimiteLasConsultasAlModelo(): void
    {
        $pregunta = new PreguntaAyuda('¿Cómo hago un traspaso?');
        $this->registro->guardar(
            null,
            $pregunta,
            'huella-4',
            new RespuestaAyuda('Con el concepto 41.', ['traspasos'], OrigenRespuesta::Ia),
        );
        $this->registro->guardar(
            null,
            $pregunta,
            'huella-5',
            new RespuestaAyuda('Mire Traspasos.', ['traspasos'], OrigenRespuesta::Busqueda),
        );

        $desde = new DateTimeImmutable('-1 day');

        self::assertSame(1, $this->registro->consultasAlModeloDesde(null, $desde));
    }

    public function testElLimiteNoMezclaUsuarios(): void
    {
        $pregunta = new PreguntaAyuda('¿Cómo hago un traspaso?');
        $respuesta = new RespuestaAyuda('Con el concepto 41.', ['traspasos'], OrigenRespuesta::Ia);
        $this->registro->guardar(null, $pregunta, 'huella-6', $respuesta);

        $desde = new DateTimeImmutable('-1 day');

        self::assertSame(1, $this->registro->consultasAlModeloDesde(null, $desde));
        self::assertSame(0, $this->registro->consultasAlModeloDesde(12345, $desde));
    }

    public function testNoCuentanLasConsultasAntiguas(): void
    {
        $this->registro->guardar(
            null,
            new PreguntaAyuda('¿Cómo hago un traspaso?'),
            'huella-7',
            new RespuestaAyuda('Con el concepto 41.', ['traspasos'], OrigenRespuesta::Ia),
        );
        $this->pdo->exec("UPDATE ayuda_consultas SET created_at = NOW() - INTERVAL '2 days'");

        self::assertSame(
            0,
            $this->registro->consultasAlModeloDesde(null, new DateTimeImmutable('-1 day')),
        );
    }
}
