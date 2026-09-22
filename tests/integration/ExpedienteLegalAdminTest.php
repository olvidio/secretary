<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\IniciarSesion;
use src\acceso\application\RegistrarUsuario;
use src\acceso\application\ResolverPersonaActiva;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\legal\application\BuscarExpedientesLegales;
use src\legal\application\ObtenerExpedienteLegal;
use src\legal\application\RegistrarAceptacion;
use src\legal\domain\services\CatalogoDocumentosLegales;
use src\legal\domain\services\DatosOperador;
use src\legal\infrastructure\html\GeneradorExpedienteLegalHtml;
use src\legal\infrastructure\persistence\PdoAceptacionLegalRepository;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;
use Tests\Soporte\ServiciosAcceso;

final class ExpedienteLegalAdminTest extends TestCase
{
    use BaseDeDatosAislada;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->saltarSiNoHayPgsql();
        try {
            $this->pdo = $this->prepararBaseDeTestVacia('secretario_test_expediente_legal');
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base: ' . $e->getMessage());
        }
        (new SchemaInstaller($this->pdo))->install();
    }

    public function testBuscarYObtenerExpedienteTrasRegistro(): void
    {
        $identidades = new PdoIdentidadRepository($this->pdo);
        $aceptaciones = new PdoAceptacionLegalRepository($this->pdo);
        $catalogo = CatalogoDocumentosLegales::porDefecto();
        $operador = new DatosOperador('Operador Test', 'op@test.local', 'Calle 1');
        $registrarAceptacion = new RegistrarAceptacion($aceptaciones, $catalogo, $operador);

        $alta = \Tests\Soporte\ServiciosAcceso::registrarUsuario($this->pdo)
            ->ejecutar('legal1', 'legal1@example.test', 'secret1', 'secret1', 'Usuario Legal', true);
        $identidadId = (int) $alta['identidad']->id;

        $registrarAceptacion->ejecutar(
            $identidadId,
            'formulario_registro',
            $catalogo->textoCasillaRegistro('es'),
            new \src\legal\domain\value_objects\HuellaAceptacion(
                '127.0.0.1',
                'PHPUnit',
                'es',
                'legal1@example.test',
                'legal1',
            ),
        );

        ServiciosAcceso::confirmarEmailRegistro($this->pdo, $alta['token_verificacion'], $identidades, $catalogo);

        $buscar = new BuscarExpedientesLegales($aceptaciones);
        $porCorreo = $buscar->ejecutar('legal1@example.test');
        self::assertCount(1, $porCorreo);
        self::assertSame($identidadId, $porCorreo[0]['id']);
        self::assertSame(2, $porCorreo[0]['aceptaciones']);

        $expediente = (new ObtenerExpedienteLegal(
            $identidades,
            $aceptaciones,
            $catalogo,
            $operador,
        ))->ejecutar($identidadId);

        self::assertSame('legal1@example.test', $expediente['usuario']['email']);
        self::assertCount(2, $expediente['aceptaciones']);
        self::assertNotEmpty($expediente['aceptaciones'][0]['canal_etiqueta']);
        self::assertNotEmpty($expediente['documentos']);

        $html = GeneradorExpedienteLegalHtml::documento($expediente);
        self::assertStringContainsString('legal1@example.test', $html);
        self::assertStringContainsString('Registro (formulario)', $html);
        self::assertStringContainsString('Confirmación de correo', $html);
        self::assertStringContainsString('SHA-256', $html);

        $login = \Tests\Soporte\ServiciosAcceso::iniciarSesion($this->pdo, $identidades)
            ->ejecutar('legal1', 'secret1');
        self::assertSame('autenticado', $login->estado);
    }
}
