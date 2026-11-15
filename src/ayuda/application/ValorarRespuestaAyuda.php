<?php

declare(strict_types=1);

namespace src\ayuda\application;

use src\ayuda\domain\contracts\RegistroConsultasAyuda;
use src\ayuda\domain\contracts\RepositorioDocumentacion;
use src\ayuda\domain\value_objects\AmbitoManual;
use src\ayuda\domain\value_objects\PreguntaAyuda;

/**
 * Apunta si la respuesta de la ayuda le ha servido al usuario. Un «no» deja la
 * pregunta en la lista de repaso del manual y la respuesta deja de reutilizarse.
 */
final class ValorarRespuestaAyuda
{
    public const MAXIMO_COMENTARIO = 500;

    public function __construct(
        private readonly RepositorioDocumentacion $documentacion,
        private readonly RegistroConsultasAyuda $registro,
    ) {
    }

    /** Devuelve false si la respuesta valorada ya no existe (p. ej. el manual cambió entretanto). */
    public function ejecutar(
        string $texto,
        bool $util,
        string $comentario = '',
        ?int $identidadId = null,
        ?AmbitoManual $ambito = null,
        string $preguntaAnterior = '',
    ): bool {
        $pregunta = new PreguntaAyuda($texto);
        if (trim($preguntaAnterior) !== '') {
            $pregunta = $pregunta->comoSeguimientoDe(new PreguntaAyuda($preguntaAnterior));
        }
        $ambito ??= AmbitoManual::centroN();
        // Misma huella que ResponderPreguntaAyuda: manual vigente + ámbito.
        $version = $this->documentacion->version() . '|' . $ambito->codigo;
        $comentario = trim((string) preg_replace('/\s+/u', ' ', $comentario));

        return $this->registro->valorar(
            $pregunta->huella($version),
            $identidadId,
            $util,
            $util ? '' : mb_substr($comentario, 0, self::MAXIMO_COMENTARIO),
        );
    }
}
