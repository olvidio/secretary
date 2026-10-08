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
}
