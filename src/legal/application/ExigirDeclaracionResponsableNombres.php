<?php

declare(strict_types=1);

namespace src\legal\application;

use InvalidArgumentException;
use src\legal\domain\services\CatalogoDocumentosLegales;
use src\legal\domain\value_objects\HuellaAceptacion;

final class ExigirDeclaracionResponsableNombres
{
    public function __construct(
        private readonly RegistrarAceptacion $registrar,
        private readonly CatalogoDocumentosLegales $catalogo,
    ) {
    }

    public function comprobar(bool $aceptada): void
    {
        if (!$aceptada) {
            throw new InvalidArgumentException(
                _('Debe aceptar que el centro es responsable de los datos de las personas que da de alta'),
            );
        }
    }

    public function ejecutar(bool $aceptada, ?int $identidadId, string $canal, HuellaAceptacion $huella): void
    {
        $this->comprobar($aceptada);
        $idioma = $huella->idioma === 'ca' ? 'ca' : 'es';
        $this->registrar->ejecutar(
            $identidadId,
            $canal,
            $this->catalogo->textoCasillaNombres($idioma),
            $huella,
        );
    }
}
