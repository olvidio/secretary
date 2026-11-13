<?php

declare(strict_types=1);

namespace Tests\integration;

use InvalidArgumentException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\CambiarAmbitoUsuario;
use src\acceso\application\CambiarCentroUsuario;
use src\acceso\application\CambiarTipoUsuario;
use src\acceso\application\CrearEntidadPropia;
use src\acceso\application\EliminarEntidadPropia;
use src\administracion\application\EliminarCentro;
use src\ambito\application\VaciarDatosCentro;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\acceso\application\ListarAmbitosIdentidad;
use src\acceso\domain\value_objects\RolCentro;
use src\ambito\domain\services\TipoEntidad;
use src\plan\domain\services\CatalogoPlanesContables;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;
use Tests\Soporte\ServiciosAcceso;

/** Una cuenta ya existente crea otra entidad desde Ámbito y queda como quien la lleva. */
final class CrearEntidadPropiaTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_entidad_propia';

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

    public function testCuentaPersonalCreaAsociacionYQuedaComoAdministradora(): void
    {
        $identidades = ServiciosAcceso::identidades($this->pdo);
        $alta = ServiciosAcceso::registrarUsuario($this->pdo)
            ->ejecutar('marta', 'marta@test.local', 'secreto1', 'secreto1', 'Marta', true);
        $id = (int) $alta['identidad']->id;
        self::assertSame([], $identidades->centrosDe($id));

        $resultado = $this->servicio()->ejecutar($id, 'club', ' AMICS ', ' Amics del barri ');

        $centro = $resultado['centro'];
        self::assertSame('AMICS', $centro->codigo);
        self::assertSame('Amics del barri', $centro->nombre);
        self::assertSame(TipoEntidad::ASOCIACION, $centro->tipo);
        self::assertSame(CatalogoPlanesContables::CLUB, $centro->planContableCodigo);
        self::assertFalse($resultado['totp_confirmado']);
        self::assertSame(RolCentro::ADMIN, $identidades->rolEnCentro($id, (int) $centro->id));
        // No se ha creado otra identidad con el mismo correo.
        self::assertCount(1, $identidades->listarPorEmail('marta@test.local'));

        $ambitos = (new ListarAmbitosIdentidad($identidades))->ejecutar($id);
        self::assertContains('centro:' . $centro->id, array_column($ambitos['opciones'], 'valor'));
        self::assertContains('persona', array_column($ambitos['opciones'], 'valor'));

        // Sin segundo factor todavía no se entra como quien lleva la entidad.
        $cambiar = new CambiarAmbitoUsuario(
            new ListarAmbitosIdentidad($identidades),
            new CambiarTipoUsuario($identidades),
            new CambiarCentroUsuario($identidades),
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('segundo factor');
        $cambiar->ejecutar($id, 'centro:' . $centro->id);
    }

    public function testUnaMismaCuentaPuedeLlevarVariasEntidades(): void
    {
        $identidades = ServiciosAcceso::identidades($this->pdo);
        $alta = ServiciosAcceso::registrarUsuario($this->pdo)
            ->ejecutar('pere', 'pere@test.local', 'secreto1', 'secreto1', 'Pere', true);
        $id = (int) $alta['identidad']->id;

        $servicio = $this->servicio();
        $servicio->ejecutar($id, 'fundacion', 'FUND', 'Fundació');
        $servicio->ejecutar($id, 'centro-sg', 'SG1', 'Centro sg');
        $servicio->ejecutar($id, 'centro', 'N1', 'Centro n');

        $tipos = [];
        foreach ($identidades->centrosDe($id) as $v) {
            $tipos[$v->codigo] = $v->rol;
        }
        self::assertSame(['FUND', 'N1', 'SG1'], $this->ordenadas(array_keys($tipos)));
        self::assertSame([RolCentro::ADMIN], array_values(array_unique($tipos)));
    }

    public function testRechazaSiglaRepetidaYTipoDesconocido(): void
    {
        $alta = ServiciosAcceso::registrarUsuario($this->pdo)
            ->ejecutar('joan', 'joan@test.local', 'secreto1', 'secreto1', 'Joan', true);
        $id = (int) $alta['identidad']->id;
        $servicio = $this->servicio();
        $servicio->ejecutar($id, 'club', 'REPE', 'Primera');

        try {
            $servicio->ejecutar($id, 'club', 'REPE', 'Segunda');
            self::fail('Debía rechazar la sigla repetida');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('Ya existe', $e->getMessage());
        }
        try {
            $servicio->ejecutar($id, 'persona', 'OTRA', 'Otra');
            self::fail('Debía rechazar el tipo');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('tipo de entidad', $e->getMessage());
        }
        self::assertCount(1, ServiciosAcceso::identidades($this->pdo)->centrosDe($id));
    }

    public function testQuienAdministraLaEntidadLaEliminaEscribiendoLaSigla(): void
    {
        $identidades = ServiciosAcceso::identidades($this->pdo);
        $alta = ServiciosAcceso::registrarUsuario($this->pdo)
            ->ejecutar('laia', 'laia@test.local', 'secreto1', 'secreto1', 'Laia', true);
        $id = (int) $alta['identidad']->id;
        $centroId = (int) $this->servicio()->ejecutar($id, 'club', 'BORRAR', 'Per esborrar')['centro']->id;
        $otraId = (int) $this->servicio()->ejecutar($id, 'club', 'QUEDA', 'Es queda')['centro']->id;
        $eliminar = $this->eliminar();

        try {
            $eliminar->ejecutar($id, $centroId, 'OTRA');
            self::fail('Debía exigir la sigla');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('sigla', $e->getMessage());
        }
        $eliminar->ejecutar($id, $centroId, ' borrar ');

        $centros = new PdoCentroRepository($this->pdo);
        self::assertNull($centros->porId($centroId));
        self::assertNotNull($centros->porId($otraId));
        self::assertSame([$otraId], array_map(static fn ($v) => $v->centroId, $identidades->centrosDe($id)));
        // La cuenta y su libro personal siguen.
        self::assertNotNull($identidades->porId($id));
        self::assertNotSame([], $identidades->personasDe($id));
    }

    public function testNiConsultaNiAjenosNiElLibroPersonalSePuedenEliminar(): void
    {
        $identidades = ServiciosAcceso::identidades($this->pdo);
        $registrar = ServiciosAcceso::registrarUsuario($this->pdo);
        $duena = (int) $registrar->ejecutar('nuria', 'nuria@test.local', 'secreto1', 'secreto1', 'Núria', true)['identidad']->id;
        $consulta = (int) $registrar->ejecutar('oriol', 'oriol@test.local', 'secreto1', 'secreto1', 'Oriol', true)['identidad']->id;
        $ajena = (int) $registrar->ejecutar('pau', 'pau@test.local', 'secreto1', 'secreto1', 'Pau', true)['identidad']->id;
        $centroId = (int) $this->servicio()->ejecutar($duena, 'club', 'MEVA', 'La meva')['centro']->id;
        $identidades->vincularCentro($consulta, $centroId, RolCentro::CONSULTA);
        $eliminar = $this->eliminar();
        $centros = new PdoCentroRepository($this->pdo);

        foreach ([$consulta, $ajena] as $quien) {
            try {
                $eliminar->ejecutar($quien, $centroId, 'MEVA');
                self::fail('No debía poder eliminarla');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('administra', $e->getMessage());
            }
        }
        self::assertNotNull($centros->porId($centroId));

        // El libro personal es una fila de centros tipo p: no se borra por aquí.
        $libro = null;
        foreach ($centros->listar() as $c) {
            if ($c->tipo === TipoEntidad::PERSONAL) {
                $libro = $c;
                break;
            }
        }
        self::assertNotNull($libro);
        $this->expectException(InvalidArgumentException::class);
        $eliminar->ejecutar($duena, (int) $libro->id, $libro->codigo);
    }

    private function eliminar(): EliminarEntidadPropia
    {
        $ejercicios = new PdoEjercicioRepository($this->pdo);

        return new EliminarEntidadPropia(
            new EliminarCentro(
                $this->pdo,
                new PdoCentroRepository($this->pdo),
                $ejercicios,
                new VaciarDatosCentro($this->pdo, $ejercicios, new PdoAsientoRepository($this->pdo)),
            ),
            new PdoCentroRepository($this->pdo),
            ServiciosAcceso::identidades($this->pdo),
        );
    }

    private function servicio(): CrearEntidadPropia
    {
        return new CrearEntidadPropia(
            ServiciosAcceso::crearCentro($this->pdo),
            ServiciosAcceso::identidades($this->pdo),
        );
    }

    /**
     * @param list<string> $valores
     * @return list<string>
     */
    private function ordenadas(array $valores): array
    {
        sort($valores);

        return $valores;
    }
}
