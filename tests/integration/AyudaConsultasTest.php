<?php

declare(strict_types=1);

namespace Tests\integration;

use DateTimeImmutable;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ayuda\domain\value_objects\EstadoRepasoAyuda;
use src\ayuda\domain\value_objects\OrigenRespuesta;
use src\ayuda\domain\value_objects\PreguntaAyuda;
use src\ayuda\domain\value_objects\PreguntaAyudaPendiente;
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

    public function testListarPendientesAgrupaPorPregunta(): void
    {
        $sin = RespuestaAyuda::sinRespuesta(OrigenRespuesta::Ia);
        $this->registro->guardar(
            null,
            new PreguntaAyuda('¿Dónde está X?'),
            'huella-p1',
            $sin,
            'centro-n',
        );
        $this->registro->guardar(
            null,
            new PreguntaAyuda('¿Dónde está X?'),
            'huella-p2',
            $sin,
            'centro-n',
        );

        $lista = $this->registro->listarPendientes();
        self::assertCount(1, $lista);
        self::assertSame('¿Dónde está X?', $lista[0]->pregunta);
        self::assertSame(2, $lista[0]->veces);
        self::assertSame(['centro-n'], $lista[0]->ambitos);
    }

    public function testListarPendientesIncluyeConsultante(): void
    {
        $sin = RespuestaAyuda::sinRespuesta(OrigenRespuesta::Ia);
        $this->pdo->exec(
            "INSERT INTO identidades (email, alias, password_hash, nombre)
             VALUES ('ayuda@test.local', 'ayudante', 'x', 'Ana Ayuda')"
        );
        $id = (int) $this->pdo->lastInsertId('identidades_id_seq');
        $this->registro->guardar(
            $id,
            new PreguntaAyuda('¿Quién soy?'),
            'huella-q',
            $sin,
            'persona',
        );
        $lista = $this->registro->listarPendientes();
        self::assertCount(1, $lista);
        self::assertCount(1, $lista[0]->consultantes);
        self::assertSame('ayudante · ayuda@test.local', $lista[0]->consultantes[0]->etiqueta());
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

    public function testUnNoApuntaLaPreguntaYDejaDeReutilizarLaRespuesta(): void
    {
        $this->pdo->exec(
            "INSERT INTO identidades (email, alias, password_hash, nombre)
             VALUES ('voto@test.local', 'votante', 'x', 'Vera Voto')"
        );
        $id = (int) $this->pdo->lastInsertId('identidades_id_seq');
        $this->registro->guardar(
            null,
            new PreguntaAyuda('¿Cómo cierro el mes?'),
            'huella-v1',
            new RespuestaAyuda('En Cierre.', ['cierre'], OrigenRespuesta::Ia),
            'centro-n',
        );
        self::assertSame([], $this->registro->listarPendientes());

        self::assertTrue($this->registro->valorar('huella-v1', $id, false, 'Quería saber qué pasa con el arqueo'));

        self::assertNull($this->registro->buscar('huella-v1'));
        $lista = $this->registro->listarPendientes();
        self::assertCount(1, $lista);
        self::assertSame(PreguntaAyudaPendiente::NO_SIRVIO, $lista[0]->motivo);
        self::assertSame(1, $lista[0]->votosNegativos);
        self::assertSame('En Cierre.', $lista[0]->ultimaRespuesta);
        self::assertSame('Quería saber qué pasa con el arqueo', $lista[0]->comentarios[0]->texto);
        self::assertSame('votante · voto@test.local', $lista[0]->comentarios[0]->quien);
    }

    public function testUnSiNoApuntaNadaYCambiarDeOpinionSustituyeElVoto(): void
    {
        $this->pdo->exec(
            "INSERT INTO identidades (email, alias, password_hash, nombre)
             VALUES ('voto2@test.local', 'votante2', 'x', 'Vera Voto')"
        );
        $id = (int) $this->pdo->lastInsertId('identidades_id_seq');
        $this->registro->guardar(
            null,
            new PreguntaAyuda('¿Cómo cierro el mes?'),
            'huella-v2',
            new RespuestaAyuda('En Cierre.', ['cierre'], OrigenRespuesta::Ia),
        );

        $this->registro->valorar('huella-v2', $id, false, 'no');
        $this->registro->valorar('huella-v2', $id, true, '');

        self::assertSame([], $this->registro->listarPendientes());
        self::assertNotNull($this->registro->buscar('huella-v2'));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM ayuda_valoraciones')->fetchColumn());
    }

    public function testValorarUnaRespuestaQueNoExisteNoHaceNada(): void
    {
        self::assertFalse($this->registro->valorar('huella-inexistente', null, false, ''));
    }

    public function testLasRepasadasDejanDeSalirYUnaPreguntaNuevaVuelveAEntrar(): void
    {
        $sin = RespuestaAyuda::sinRespuesta(OrigenRespuesta::Ia);
        $pregunta = new PreguntaAyuda('¿Dónde está Y?');
        $this->registro->guardar(null, $pregunta, 'huella-r1', $sin);
        $this->registro->guardar(null, $pregunta, 'huella-r1', $sin);

        self::assertSame(2, $this->registro->marcarRepasada('¿Dónde está Y?', EstadoRepasoAyuda::Cubierta));
        self::assertSame([], $this->registro->listarPendientes());

        $this->registro->guardar(null, $pregunta, 'huella-r2', $sin);
        $lista = $this->registro->listarPendientes();
        self::assertCount(1, $lista);
        self::assertSame(1, $lista[0]->veces);
    }

    public function testUnNoReabreUnaPreguntaYaRepasada(): void
    {
        $this->registro->guardar(
            null,
            new PreguntaAyuda('¿Cómo cierro el mes?'),
            'huella-v3',
            new RespuestaAyuda('En Cierre.', ['cierre'], OrigenRespuesta::Ia),
        );
        $this->registro->valorar('huella-v3', null, false, '');
        $this->registro->marcarRepasada('¿Cómo cierro el mes?', EstadoRepasoAyuda::Descartada);
        self::assertSame([], $this->registro->listarPendientes());

        $this->registro->valorar('huella-v3', null, false, 'sigue sin servir');

        self::assertCount(1, $this->registro->listarPendientes());
    }

    public function testLaPeticionDeAclaracionSeReutilizaComoTalYNoQuedaPendiente(): void
    {
        $this->registro->guardar(
            null,
            new PreguntaAyuda('en arqueo sale una diferencia de 8000'),
            'huella-a1',
            new RespuestaAyuda('¿Es la caja o el banco?', ['arqueo'], OrigenRespuesta::Ia, true, true),
        );

        $guardada = $this->registro->buscar('huella-a1');
        self::assertNotNull($guardada);
        self::assertTrue($guardada->aclaracion);
        self::assertSame([], $this->registro->listarPendientes());
    }
}
