<?php

declare(strict_types=1);

namespace Tests\integration;

use InvalidArgumentException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\domain\entity\Centro;
use src\ambito\infrastructure\persistence\AmbitoSeeder;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\plan\infrastructure\http\ClubCuentasController;
use src\plan\infrastructure\persistence\PdoCuentasClub;
use src\plan\infrastructure\persistence\PdoPlanConceptoRepository;
use src\plan\infrastructure\persistence\PdoPlanContableRepository;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;
use Tests\support\ConceptosCentro;

/** Una associació modifica sus cuentas: nombre, naturaleza y código (con sus referencias). */
final class ClubCuentasTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_club_cuentas';

    private PDO $pdo;
    private int $centroId;
    private PdoCuentasClub $cuentas;

    protected function setUp(): void
    {
        $this->saltarSiNoHayPgsql();
        try {
            $this->pdo = $this->prepararBaseDeTestVacia(self::DB_NAME);
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base: ' . $e->getMessage());
        }
        (new SchemaInstaller($this->pdo))->install();
        $centro = (new PdoCentroRepository($this->pdo))->guardar(
            new Centro(null, 'CLUBT', 'Club de prueba', 'asociacion', 'vivienda', 'Club')
        );
        self::assertNotNull($centro->id);
        $this->centroId = $centro->id;
        AmbitoSeeder::poblarLibros($this->pdo, $this->centroId);
        $this->cuentas = new PdoCuentasClub($this->pdo);
    }

    public function testCambiarNombreNaturalezaYOficialSinTocarElCodigo(): void
    {
        $r = $this->cuentas->modificar($this->centroId, '90.1', [
            'codigo' => '90.1',
            'nombre' => 'Lloguers',
            'naturaleza' => 'gasto',
            'codigo_oficial' => '752',
        ], false);

        self::assertFalse($r['pendiente']);
        self::assertSame(
            ['Lloguers', 'gasto', 'deudora', '752'],
            $this->fila('90.1', 'nombre, tipo, naturaleza, codigo_oficial'),
        );
        // La plantilla dice «Ingressos / ingreso», pero manda la cuenta del centro.
        $concepto = ConceptosCentro::resolver($this->pdo)->buscar($this->centroId, 'G', '90.1');
        self::assertSame('Lloguers', $concepto?->nombre);
        self::assertSame('gasto', $concepto->naturaleza);
    }

    public function testCambiarElCodigoPideConfirmarYArrastraLasReferencias(): void
    {
        $idAntes = $this->fila('61.3', 'id')[0];
        $this->pdo->prepare("INSERT INTO listados (centro_id, nombre, categorias) VALUES (:c, 'neteja', :cat)")
            ->execute([':c' => $this->centroId, ':cat' => '["61.3","70.2"]']);
        $datos = ['codigo' => '63.1', 'nombre' => 'Neteja', 'naturaleza' => 'gasto', 'codigo_oficial' => '629'];

        $sinConfirmar = $this->cuentas->modificar($this->centroId, '61.3', $datos, false);
        self::assertTrue($sinConfirmar['pendiente']);
        self::assertStringContainsString('61.3 → 63.1', $sinConfirmar['aviso']);
        self::assertSame($idAntes, $this->fila('61.3', 'id')[0], 'sin confirmar no cambia nada');

        $r = $this->cuentas->modificar($this->centroId, '61.3', $datos, true);
        self::assertFalse($r['pendiente']);
        self::assertSame([], $this->fila('61.3', 'id'));
        // Misma fila: los movimientos, que apuntan a su id, la siguen.
        self::assertSame([$idAntes, '63.1'], $this->fila('63.1', 'id, codigo_maestro'));
        $categorias = $this->pdo->query("SELECT categorias FROM listados WHERE nombre = 'neteja'")->fetchColumn();
        self::assertSame(['63.1', '70.2'], json_decode((string) $categorias, true));

        // El catálogo del centro ya no ofrece el código antiguo, y volver a sembrar no lo resucita.
        AmbitoSeeder::poblarLibros($this->pdo, $this->centroId);
        $codigos = array_column(ConceptosCentro::resolver($this->pdo)->listar($this->centroId, 'G'), 'codigo');
        self::assertContains('63.1', $codigos);
        self::assertNotContains('61.3', $codigos);
    }

    public function testCambiarElCodigoDeUnGrupoLlevaASusHijas(): void
    {
        $r = $this->cuentas->modificar($this->centroId, '62', [
            'codigo' => '64', 'nombre' => 'Personal', 'naturaleza' => 'gasto', 'codigo_oficial' => null,
        ], true);

        self::assertFalse($r['pendiente']);
        self::assertSame(['Personal'], $this->fila('64', 'nombre'));
        self::assertSame(['Sous', '640'], $this->fila('64.1', 'nombre, codigo_oficial'));
        self::assertSame(['Gestoria'], $this->fila('64.3', 'nombre'));
        self::assertSame([], $this->fila('62.1', 'id'));
    }

    public function testNoDejaPisarOtraCuentaNiRenombrarLasDelPrograma(): void
    {
        $datos = ['codigo' => '61.2', 'nombre' => 'Neteja', 'naturaleza' => 'gasto', 'codigo_oficial' => null];
        try {
            $this->cuentas->modificar($this->centroId, '61.3', $datos, true);
            self::fail('Debía rechazar un código que ya existe');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('61.2', $e->getMessage());
        }
        self::assertNotSame([], $this->fila('61.3', 'id'));

        $this->expectException(InvalidArgumentException::class);
        $this->cuentas->modificar($this->centroId, 'APERTURA', [
            'codigo' => '12', 'nombre' => 'Saldo inicial', 'naturaleza' => null, 'codigo_oficial' => '120',
        ], true);
    }

    public function testElModeloSoloSirveParaCopiarCuentasYSePuedeEliminar(): void
    {
        $this->pdo->prepare(
            "INSERT INTO ejercicios (centro_id, etiqueta, fecha_inicio, fecha_fin, fecha_corte, estado)
             VALUES (:c, '2026', '2026-01-01', '2026-12-31', '2026-01-01', 'abierto')"
        )->execute([':c' => $this->centroId]);
        $centros = new PdoCentroRepository($this->pdo);
        $planes = new PdoPlanContableRepository($this->pdo);
        $controlador = new ClubCuentasController(
            $this->pdo,
            new ResolverAmbitoActual(
                new PdoConfiguracionRepository($this->pdo),
                $centros,
                new PdoEjercicioRepository($this->pdo),
                $this->centroId,
            ),
            $centros,
            $planes,
            new PdoPlanConceptoRepository($this->pdo),
            $this->cuentas,
        );
        $pedir = static fn (array $body): Request => new Request('POST', '/', [], $body, []);
        $json = static fn ($respuesta): array => json_decode($respuesta->body, true);
        $catalogo = fn (): array => array_column(
            ConceptosCentro::resolver($this->pdo)->listar($this->centroId, 'G'),
            'nombre',
            'codigo',
        );

        // El centro adapta sus cuentas y las guarda como modelo.
        $this->cuentas->modificar($this->centroId, '61.3', [
            'codigo' => '63.1', 'nombre' => 'Neteja local', 'naturaleza' => 'gasto', 'codigo_oficial' => '629',
        ], true);
        $antes = $catalogo();
        $guardado = $json($controlador->guardarModelo($pedir(['nombre' => 'El meu club'])));
        self::assertTrue($guardado['ok']);
        $propio = array_values(array_filter($guardado['modelos'], static fn (array $m): bool => $m['borrable']));
        self::assertCount(1, $propio);
        self::assertSame('El meu club', $propio[0]['nombre']);
        // Un modelo no es un plan que se asigne a un centro, y el centro sigue en el plan Club.
        self::assertNotContains($propio[0]['codigo'], array_column($planes->listar(), 'codigo'));
        self::assertSame('Club', $centros->porId($this->centroId)?->planContableCodigo);

        // Copiar el modelo de base añade lo que falta (61.3) y no toca lo que hay.
        $base = array_values(array_filter($guardado['modelos'], static fn (array $m): bool => !$m['borrable']))[0];
        $aplicado = $json($controlador->aplicarModelo($pedir(['codigo' => $base['codigo']])));
        self::assertSame(1, $aplicado['anadidas']);
        $esperado = $antes + ['61.3' => 'Neteja'];
        $ahora = $catalogo();
        ksort($esperado);
        ksort($ahora);
        self::assertSame($esperado, $ahora);
        self::assertSame('Neteja local', $catalogo()['63.1']);

        // El modelo de base no se elimina; el guardado sí, y el centro ni se entera.
        self::assertFalse($json($controlador->borrarModelo($pedir(['codigo' => $base['codigo']])))['ok']);
        $despues = $catalogo();
        $borrado = $json($controlador->borrarModelo($pedir(['codigo' => $propio[0]['codigo']])));
        self::assertTrue($borrado['ok']);
        self::assertCount(1, $borrado['modelos']);
        // Ni vaciando el modelo de base cambia nada en el centro.
        $this->pdo->exec("DELETE FROM plan_conceptos WHERE plan_contable_id = {$base['id']}");
        AmbitoSeeder::poblarLibros($this->pdo, $this->centroId);
        self::assertSame($despues, $catalogo());
    }

    /** @return list<mixed> */
    private function fila(string $codigo, string $columnas): array
    {
        $st = $this->pdo->prepare(
            "SELECT {$columnas} FROM cuentas
             WHERE centro_id = :c AND libro = 'G' AND persona_id IS NULL AND codigo = :cod"
        );
        $st->execute([':c' => $this->centroId, ':cod' => $codigo]);
        $row = $st->fetch(PDO::FETCH_NUM);

        return is_array($row) ? $row : [];
    }
}
