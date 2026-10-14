<?php

declare(strict_types=1);

namespace src\ayuda\application;

use src\ayuda\domain\contracts\RegistroConsultasAyuda;
use src\ayuda\domain\contracts\RepositorioDocumentacion;
use src\ayuda\domain\services\FormateadorPreguntasAyudaPendientes;
use src\ayuda\domain\value_objects\PreguntaAyudaPendiente;

final class ListarPreguntasAyudaPendientes
{
    public function __construct(
        private readonly RegistroConsultasAyuda $registro,
        private readonly RepositorioDocumentacion $documentacion,
        private readonly FormateadorPreguntasAyudaPendientes $formateador,
    ) {
    }

    /** @return list<PreguntaAyudaPendiente> */
    public function ejecutar(): array
    {
        return $this->registro->listarPendientes();
    }

    public function versionManual(): string
    {
        return $this->documentacion->version();
    }

    /** @return array{version_manual: string, total: int, preguntas: list<array<string, mixed>>} */
    public function comoJson(): array
    {
        $pendientes = $this->ejecutar();

        return [
            'version_manual' => $this->versionManual(),
            'total' => count($pendientes),
            'preguntas' => array_map(
                static fn (PreguntaAyudaPendiente $p): array => $p->toArray(),
                $pendientes,
            ),
        ];
    }

    public function comoMarkdown(): string
    {
        return $this->formateador->markdown($this->versionManual(), $this->ejecutar());
    }

    public function exportarJson(): string
    {
        return $this->formateador->json($this->versionManual(), $this->ejecutar());
    }
}
