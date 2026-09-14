<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use src\ayuda\application\ResponderPreguntaAyuda;
use src\ayuda\domain\contracts\ProveedorRespuestaIA;
use src\ayuda\domain\contracts\RegistroConsultasAyuda;
use src\ayuda\domain\contracts\RepositorioDocumentacion;
use src\ayuda\domain\entity\DocumentoAyuda;
use src\ayuda\domain\services\BuscadorDocumentacion;
use src\ayuda\domain\services\ConstructorPromptAyuda;
use src\ayuda\domain\services\InterpreteRespuestaIA;
use src\ayuda\domain\value_objects\OrigenRespuesta;
use src\ayuda\domain\value_objects\PreguntaAyuda;
use src\ayuda\domain\value_objects\RespuestaAyuda;

final class ResponderPreguntaAyudaTest extends TestCase
{
    private function caso(
        ?ProveedorRespuestaIA $proveedor,
        ?RegistroConsultasAyuda $registro = null,
        int $limiteDiario = 30,
    ): ResponderPreguntaAyuda {
        return new ResponderPreguntaAyuda(
            new ManualDeAyudaFalso(),
            $registro ?? new RegistroDeAyudaEnMemoria(),
            new ConstructorPromptAyuda(),
            new InterpreteRespuestaIA(),
            new BuscadorDocumentacion(),
            $proveedor,
            $limiteDiario,
        );
    }

    public function testContestaConLaIaYGuardaLaConsulta(): void
    {
        $registro = new RegistroDeAyudaEnMemoria();
        $proveedor = new ProveedorDeAyudaFalso("Con el concepto 41.\nFUENTES: traspasos");

        $respuesta = $this->caso($proveedor, $registro)->ejecutar('¿Cómo paso dinero del banco a la caja?');

        self::assertSame(OrigenRespuesta::Ia, $respuesta->origen);
        self::assertSame(['traspasos'], $respuesta->fuentes);
        self::assertSame('Con el concepto 41.', $respuesta->texto);
        self::assertSame(['¿Cómo paso dinero del banco a la caja?'], $registro->preguntasGuardadas);
    }

    public function testElManualEnteroViajaEnLaInstruccion(): void
    {
        $proveedor = new ProveedorDeAyudaFalso("Se anota en Apuntes.\nFUENTES: apuntes");

        $this->caso($proveedor)->ejecutar('¿Dónde veo los apuntes?');

        self::assertStringContainsString('[clave: apuntes]', $proveedor->ultimaInstruccion);
        self::assertStringContainsString('[clave: traspasos]', $proveedor->ultimaInstruccion);
    }

    public function testReutilizaLaRespuestaGuardadaSinLlamarAlProveedor(): void
    {
        $previa = new RespuestaAyuda('Con el concepto 41.', ['traspasos'], OrigenRespuesta::Ia);
        $registro = new RegistroDeAyudaEnMemoria($previa);
        $proveedor = new ProveedorDeAyudaFalso('no debería llamarse');

        $respuesta = $this->caso($proveedor, $registro)->ejecutar('¿Cómo hago un traspaso?');

        self::assertSame(OrigenRespuesta::Cache, $respuesta->origen);
        self::assertSame(0, $proveedor->llamadas);
    }

    public function testSinProveedorDevuelveLosApartadosDelManual(): void
    {
        $respuesta = $this->caso(null)->ejecutar('¿Cómo hago un traspaso?');

        self::assertSame(OrigenRespuesta::Busqueda, $respuesta->origen);
        self::assertSame(['traspasos'], $respuesta->fuentes);
        self::assertStringContainsString('Traspasos', $respuesta->texto);
    }

    public function testSiElProveedorFallaNoRevientaYCaeEnLaBusqueda(): void
    {
        $respuesta = $this->caso(new ProveedorDeAyudaFalso('', true))
            ->ejecutar('¿Cómo hago un traspaso?');

        self::assertSame(OrigenRespuesta::Busqueda, $respuesta->origen);
        self::assertStringContainsString('proveedor caído', $respuesta->texto);
    }

    public function testElLimiteDiarioCortaLasConsultasAlModelo(): void
    {
        $registro = new RegistroDeAyudaEnMemoria(null, 30);
        $proveedor = new ProveedorDeAyudaFalso('no debería llamarse');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/consultas de ayuda del día/');

        $this->caso($proveedor, $registro, 30)->ejecutar('¿Cómo hago un traspaso?');
    }

    public function testLimiteCeroNoLimita(): void
    {
        $registro = new RegistroDeAyudaEnMemoria(null, 999);
        $proveedor = new ProveedorDeAyudaFalso("Con el concepto 41.\nFUENTES: traspasos");

        $respuesta = $this->caso($proveedor, $registro, 0)->ejecutar('¿Cómo hago un traspaso?');

        self::assertSame(OrigenRespuesta::Ia, $respuesta->origen);
    }

    public function testLaRespuestaSinRespaldoLlegaComoNoResuelta(): void
    {
        $proveedor = new ProveedorDeAyudaFalso('Pulse el botón Recalcular todo.');

        $respuesta = $this->caso($proveedor)->ejecutar('¿Cómo recalculo todo?');

        self::assertFalse($respuesta->resuelta);
        self::assertSame(RespuestaAyuda::NO_ESTA_EN_EL_MANUAL, $respuesta->texto);
    }
}

final class ManualDeAyudaFalso implements RepositorioDocumentacion
{
    /** @return list<DocumentoAyuda> */
    public function todos(): array
    {
        return [
            new DocumentoAyuda('apuntes', 'Apuntes', "# Apuntes\nListado de apuntes del centro."),
            new DocumentoAyuda('traspasos', 'Traspasos', "# Traspasos\nMueve dinero entre banco y caja."),
        ];
    }

    public function version(): string
    {
        return 'v1';
    }
}

final class RegistroDeAyudaEnMemoria implements RegistroConsultasAyuda
{
    /** @var list<string> */
    public array $preguntasGuardadas = [];

    public function __construct(
        private readonly ?RespuestaAyuda $previa = null,
        private readonly int $consultasPrevias = 0,
    ) {
    }

    public function buscar(string $huella): ?RespuestaAyuda
    {
        return $this->previa;
    }

    public function guardar(
        ?int $identidadId,
        PreguntaAyuda $pregunta,
        string $huella,
        RespuestaAyuda $respuesta,
    ): void {
        $this->preguntasGuardadas[] = $pregunta->texto;
    }

    public function consultasAlModeloDesde(?int $identidadId, DateTimeImmutable $desde): int
    {
        return $this->consultasPrevias;
    }
}

final class ProveedorDeAyudaFalso implements ProveedorRespuestaIA
{
    public int $llamadas = 0;
    public string $ultimaInstruccion = '';

    public function __construct(
        private readonly string $respuesta = '',
        private readonly bool $falla = false,
    ) {
    }

    public function responder(string $instruccion, string $pregunta): string
    {
        $this->llamadas++;
        $this->ultimaInstruccion = $instruccion;
        if ($this->falla) {
            throw new RuntimeException('proveedor caído');
        }

        return $this->respuesta;
    }
}
