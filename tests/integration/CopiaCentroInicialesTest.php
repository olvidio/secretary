<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\application\AsegurarCuentaCorrientePersona;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCopiaCentro;
use src\ambito\infrastructure\persistence\PdoCuentaFisicaRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\apuntes\application\CrearApunte;
use src\apuntes\application\ListarApuntes;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\cierre\application\GenerarApertura;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\personas\domain\entity\Persona;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;
use Tests\support\ConceptosCentro;

/** La copia de centro debe conservar de quién es cada apunte, también en ficheros antiguos. */
final class CopiaCentroInicialesTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_copia_centro';

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

    public function testRestaurarUnaCopiaAntiguaRecuperaLasIniciales(): void
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $ambito = new ResolverAmbitoActual($config, new PdoCentroRepository($this->pdo), $ejercicios);
        $centroId = $ambito->ejecutar()->centroId;
        $personas->guardar(new Persona(null, 'José R.', 'JRM', 'jrm', null, null, null, null, null, 1, $centroId));
        $persona = $personas->porInicialesDeCentro($centroId, 'jrm');
        self::assertNotNull($persona);
        (new AsegurarCuentaCorrientePersona($cuentas))->ejecutar($persona);

        $crear = new CrearApunte(
            $asientos,
            ConceptosCentro::resolver($this->pdo),
            $personas,
            $config,
            $cuentas,
            new PdoCuentaFisicaRepository($this->pdo),
            new TraductorApuntesAAsientos(),
            new ProyectorAsientoAFilaExcel(),
            $ambito,
            $ejercicios,
            new GenerarApertura($ejercicios, $asientos, $cuentas),
        );
        $y = $ejercicios->porId($ambito->ejecutar()->ejercicioId)?->fechaInicio->format('Y');
        self::assertNotNull($y);
        $crear->ejecutar([
            'fecha' => $y . '-03-15',
            'cuenta' => 'P',
            'origen' => 'A',
            'iniciales' => 'jrm',
            'concepto_codigo' => '22',
            'observaciones' => 'comida',
            'cantidad' => '12.50',
        ]);

        $copias = new PdoCopiaCentro($this->pdo, $asientos);
        $datos = $copias->exportar($centroId);
        self::assertSame('jrm', $datos['asientos'][0]['iniciales']);

        foreach ($datos['asientos'] as &$fila) {
            unset($fila['iniciales']);
        }
        unset($fila);

        $copias->restaurar($centroId, $datos);

        $lista = (new ListarApuntes(
            $asientos,
            $cuentas,
            $personas,
            new ProyectorAsientoAFilaExcel(),
            $ambito,
        ))->ejecutar(['iniciales' => 'jrm']);
        self::assertCount(1, $lista);
        self::assertSame('jrm', $lista[0]['iniciales']);
        self::assertSame('comida', $lista[0]['observaciones']);

        $personaId = $this->pdo->query(
            'SELECT persona_id FROM asientos WHERE anulado_at IS NULL AND libro = \'P\' LIMIT 1'
        )->fetchColumn();
        self::assertSame($persona->id, (int) $personaId);
    }

    public function testLaCopiaTraePresupuestoYPrevision(): void
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $ambito = new ResolverAmbitoActual($config, new PdoCentroRepository($this->pdo), $ejercicios);
        $centroId = $ambito->ejecutar()->centroId;
        $ejercicioId = $ambito->ejecutar()->ejercicioId;
        $inicio = $ejercicios->porId($ejercicioId)?->fechaInicio->format('Y-m-d');
        self::assertNotNull($inicio);
        $personas->guardar(new Persona(null, 'José R.', 'JRM', 'jrm', null, null, null, null, null, 1, $centroId));
        $persona = $personas->porInicialesDeCentro($centroId, 'jrm');
        self::assertNotNull($persona);

        $this->pdo->prepare(
            'INSERT INTO presupuesto_lineas (ejercicio_id, cuenta, concepto_codigo, previsto)
             VALUES (:e, \'P\', \'111\', \'100.00\')'
        )->execute([':e' => $ejercicioId]);
        $this->pdo->prepare(
            'INSERT INTO prevision_personal_lineas (ejercicio_id, persona_id, concepto_codigo, previsto_cents)
             VALUES (:e, :p, \'22\', 5000)'
        )->execute([':e' => $ejercicioId, ':p' => $persona->id]);

        $copias = new PdoCopiaCentro($this->pdo, $asientos);
        $datos = $copias->exportar($centroId);
        self::assertSame($inicio, $datos['presupuestos'][0]['ejercicio']);
        self::assertSame('111', $datos['presupuestos'][0]['concepto']);
        self::assertSame('jrm', $datos['previsiones'][0]['iniciales']);
        self::assertSame(5000, $datos['previsiones'][0]['previsto_cents']);

        $antigua = $datos;
        unset($antigua['presupuestos'], $antigua['previsiones'], $antigua['presupuestos_sg']);
        $this->pdo->prepare(
            'UPDATE presupuesto_lineas SET previsto = \'1.00\' WHERE ejercicio_id = :e'
        )->execute([':e' => $ejercicioId]);
        $copias->restaurar($centroId, $antigua);
        $previsto = $this->pdo->query(
            'SELECT previsto FROM presupuesto_lineas WHERE concepto_codigo = \'111\''
        )->fetchColumn();
        self::assertSame('1.00', (string) $previsto);

        $copias->restaurar($centroId, $datos);
        $previsto = $this->pdo->query(
            'SELECT previsto FROM presupuesto_lineas WHERE concepto_codigo = \'111\''
        )->fetchColumn();
        $cents = $this->pdo->query(
            'SELECT previsto_cents FROM prevision_personal_lineas WHERE concepto_codigo = \'22\''
        )->fetchColumn();
        self::assertSame('100.00', (string) $previsto);
        self::assertSame(5000, (int) $cents);
    }

    public function testLaCopiaTraeNombresPlantillasRemesasYArqueos(): void
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $ambito = new ResolverAmbitoActual($config, new PdoCentroRepository($this->pdo), $ejercicios);
        $centroId = $ambito->ejecutar()->centroId;
        $ejercicioId = $ambito->ejecutar()->ejercicioId;
        $inicio = $ejercicios->porId($ejercicioId)?->fechaInicio->format('Y-m-d');
        self::assertNotNull($inicio);
        $personas->guardar(new Persona(null, 'José R.', 'JRM', 'jrm', null, null, null, null, null, 1, $centroId));
        $persona = $personas->porInicialesDeCentro($centroId, 'jrm');
        self::assertNotNull($persona);
        $this->pdo->prepare('UPDATE personas SET email = :e, remanente_cents = 1500 WHERE id = :id')
            ->execute([':e' => 'jrm@ejemplo.test', ':id' => $persona->id]);
        $plantilla = $this->pdo->prepare(
            'INSERT INTO plantillas_apunte (centro_id, cuenta, nombre, activa, orden)
             VALUES (:c, \'P\', \'Club copia\', TRUE, 1) RETURNING id'
        );
        $plantilla->execute([':c' => $centroId]);
        $plantillaId = (int) $plantilla->fetchColumn();
        $this->pdo->prepare(
            'INSERT INTO plantilla_lineas_apunte (plantilla_id, orden, origen, concepto_codigo, observaciones, cantidad, cuenta)
             VALUES (:p, 1, \'A\', \'22\', \'cuota\', 10, \'P\')'
        )->execute([':p' => $plantillaId]);
        $this->pdo->prepare(
            'INSERT INTO remesas (persona_id, centro_id, ejercicio_id, anio, mes, version, estado, hash_contenido, nota)
             VALUES (:p, :c, :e, 2026, 3, 1, \'aceptada\', \'hash-copia\', \'marzo\')'
        )->execute([':p' => $persona->id, ':c' => $centroId, ':e' => $ejercicioId]);
        $this->pdo->prepare(
            'INSERT INTO arqueos (cuenta, fecha, desglose_json, total, ejercicio_id)
             VALUES (\'P\', :f, \'[]\', \'12.00\', :e)'
        )->execute([':f' => $inicio, ':e' => $ejercicioId]);
        $this->pdo->prepare(
            'INSERT INTO personal_cierre_mes (persona_id, anio, mes, fecha_cierre)
             VALUES (:p, 2026, 3, :f)'
        )->execute([':p' => $persona->id, ':f' => $inicio]);

        $copias = new PdoCopiaCentro($this->pdo, $asientos);
        $datos = $copias->exportar($centroId);
        $ficha = null;
        foreach ($datos['personas'] as $personaExportada) {
            if ($personaExportada['iniciales'] === 'jrm') {
                $ficha = $personaExportada;
            }
        }
        self::assertIsArray($ficha);
        self::assertSame('jrm@ejemplo.test', $ficha['email']);
        $nombres = array_column($datos['plantillas'], 'nombre');
        self::assertContains('Club copia', $nombres);
        self::assertNotEmpty($datos['remesas']);
        self::assertNotEmpty($datos['arqueos']);
        self::assertNotSame('', (string) ($datos['centro']['plan_contable'] ?? ''));
        self::assertSame($inicio, $datos['cierres_mes'][0]['fecha_cierre'] ?? null);

        $this->pdo->prepare('UPDATE personas SET email = \'otro@ejemplo.test\' WHERE id = :id')
            ->execute([':id' => $persona->id]);
        $this->pdo->prepare('DELETE FROM plantillas_apunte WHERE centro_id = :c')->execute([':c' => $centroId]);
        $this->pdo->prepare('DELETE FROM remesas WHERE centro_id = :c')->execute([':c' => $centroId]);
        $this->pdo->prepare('DELETE FROM arqueos WHERE ejercicio_id = :e')->execute([':e' => $ejercicioId]);

        $copias->restaurar($centroId, $datos);

        $email = $this->pdo->query(
            'SELECT email FROM personas WHERE lower(iniciales) = \'jrm\''
        )->fetchColumn();
        $plantillas = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM plantillas_apunte WHERE nombre = \'Club copia\''
        )->fetchColumn();
        $remesas = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM remesas WHERE hash_contenido = \'hash-copia\''
        )->fetchColumn();
        $arqueos = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM arqueos WHERE total = \'12.00\''
        )->fetchColumn();
        $cierres = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM personal_cierre_mes WHERE anio = 2026 AND mes = 3'
        )->fetchColumn();
        self::assertSame('jrm@ejemplo.test', (string) $email);
        self::assertSame(1, $plantillas);
        self::assertSame(1, $remesas);
        self::assertSame(1, $arqueos);
        self::assertSame(1, $cierres);
    }
}
