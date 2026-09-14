<?php

declare(strict_types=1);

namespace src\ayuda\application;

use DateTimeImmutable;
use RuntimeException;
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

/**
 * Contesta una pregunta del usuario con el manual como única fuente.
 *
 * Orden: respuesta ya guardada, después el modelo y, si no hay modelo o falla,
 * los apartados del manual que encajan con la pregunta.
 */
final class ResponderPreguntaAyuda
{
    public function __construct(
        private readonly RepositorioDocumentacion $documentacion,
        private readonly RegistroConsultasAyuda $registro,
        private readonly ConstructorPromptAyuda $constructor,
        private readonly InterpreteRespuestaIA $interprete,
        private readonly BuscadorDocumentacion $buscador,
        private readonly ?ProveedorRespuestaIA $proveedor = null,
        private readonly int $limiteDiario = 30,
    ) {
    }

    public function ejecutar(string $texto, ?int $identidadId = null, string $idioma = 'es'): RespuestaAyuda
    {
        $pregunta = new PreguntaAyuda($texto);
        $documentos = $this->documentacion->todos();
        if ($documentos === []) {
            throw new RuntimeException('Todavía no hay manual que consultar');
        }
        $guardada = $this->registro->buscar($pregunta->huella($this->documentacion->version()));
        if ($guardada !== null) {
            return $guardada->comoCache();
        }
        if ($this->proveedor === null) {
            return $this->desdeElManual($documentos, $pregunta);
        }
        $this->comprobarLimite($identidadId);
        try {
            $crudo = $this->proveedor->responder(
                $this->constructor->instruccion($documentos, $idioma),
                $pregunta->texto,
            );
        } catch (RuntimeException $e) {
            $fallback = $this->desdeElManual($documentos, $pregunta);
            return new RespuestaAyuda(
                $fallback->texto . "\n(" . $e->getMessage() . ')',
                $fallback->fuentes,
                OrigenRespuesta::Busqueda,
            );
        }
        $respuesta = $this->interprete->interpretar($crudo, $this->claves($documentos));
        $this->registro->guardar(
            $identidadId,
            $pregunta,
            $pregunta->huella($this->documentacion->version()),
            $respuesta,
        );

        return $respuesta;
    }

    private function comprobarLimite(?int $identidadId): void
    {
        if ($this->limiteDiario <= 0) {
            return;
        }
        $desde = new DateTimeImmutable('-1 day');
        if ($this->registro->consultasAlModeloDesde($identidadId, $desde) >= $this->limiteDiario) {
            throw new RuntimeException(
                'Ha agotado las ' . $this->limiteDiario . ' consultas de ayuda del día. '
                . 'Vuelva a intentarlo mañana.',
            );
        }
    }

    /**
     * @param list<DocumentoAyuda> $documentos
     */
    private function desdeElManual(array $documentos, PreguntaAyuda $pregunta): RespuestaAyuda
    {
        $encontrados = $this->buscador->buscar($documentos, $pregunta);
        if ($encontrados === []) {
            return RespuestaAyuda::sinRespuesta(OrigenRespuesta::Busqueda);
        }
        $texto = 'No he podido preguntar a la IA en este momento. '
            . 'Estos apartados del manual hablan de lo que pregunta:';
        foreach ($encontrados as $documento) {
            $texto .= "\n- " . $documento->titulo;
        }

        return new RespuestaAyuda(
            $texto,
            $this->claves($encontrados),
            OrigenRespuesta::Busqueda,
        );
    }

    /**
     * @param list<DocumentoAyuda> $documentos
     * @return list<string>
     */
    private function claves(array $documentos): array
    {
        return array_map(static fn (DocumentoAyuda $d): string => $d->clave, $documentos);
    }
}
