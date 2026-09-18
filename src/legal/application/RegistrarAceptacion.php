<?php

declare(strict_types=1);

namespace src\legal\application;

use DateTimeImmutable;
use src\legal\domain\contracts\AceptacionLegalRepository;
use src\legal\domain\entity\AceptacionLegal;
use src\legal\domain\services\CatalogoDocumentosLegales;
use src\legal\domain\services\DatosOperador;
use src\legal\domain\value_objects\HuellaAceptacion;

final class RegistrarAceptacion
{
    public function __construct(
        private readonly AceptacionLegalRepository $aceptaciones,
        private readonly CatalogoDocumentosLegales $catalogo,
        private readonly DatosOperador $operador,
    ) {
    }

    public function ejecutar(
        ?int $identidadId,
        string $canal,
        string $textoCasilla,
        HuellaAceptacion $huella,
        ?DateTimeImmutable $momento = null,
    ): void {
        $idioma = $huella->idioma === 'ca' ? 'ca' : 'es';
        $cond = $this->catalogo->vigente('condiciones', $idioma);
        $priv = $this->catalogo->vigente('privacidad', $idioma);
        $extra = $huella->extra ?? [];
        $extra['operador'] = $this->operador->toArray();
        $huellaConOperador = new HuellaAceptacion(
            $huella->ip,
            $huella->userAgent,
            $idioma,
            $huella->email,
            $huella->alias,
            $huella->centroId,
            $huella->personaId,
            $huella->tokenHash,
            $extra,
        );
        $this->aceptaciones->registrar(new AceptacionLegal(
            $identidadId,
            $canal,
            $cond->version,
            $cond->hashSha256,
            $priv->version,
            $priv->hashSha256,
            $textoCasilla,
            $huellaConOperador,
            $momento ?? new DateTimeImmutable(),
        ));
    }
}
