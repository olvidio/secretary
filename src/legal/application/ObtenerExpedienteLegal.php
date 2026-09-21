<?php

declare(strict_types=1);

namespace src\legal\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\legal\domain\contracts\AceptacionLegalRepository;
use src\legal\domain\services\CatalogoDocumentosLegales;
use src\legal\domain\services\DatosOperador;
use src\legal\domain\services\EtiquetasCanalLegal;

final class ObtenerExpedienteLegal
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly AceptacionLegalRepository $aceptaciones,
        private readonly CatalogoDocumentosLegales $documentos,
        private readonly DatosOperador $operador,
    ) {
    }

    /**
     * @return array{
     *     generado: string,
     *     operador: array{nombre: string, email: string, direccion: string},
     *     usuario: array{
     *         id: int,
     *         email: string,
     *         alias: ?string,
     *         nombre: string,
     *         es_admin: bool,
     *         email_verificado_at: ?string
     *     },
     *     aceptaciones: list<array<string, mixed>>,
     *     documentos: array<string, array{
     *         tipo: string,
     *         version: string,
     *         idioma: string,
     *         hash: string,
     *         texto: string
     *     }>
     * }
     */
    public function ejecutar(int $identidadId): array
    {
        if ($identidadId <= 0) {
            throw new InvalidArgumentException(_('Usuario no válido'));
        }
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null) {
            throw new InvalidArgumentException(_('Usuario no encontrado'));
        }
        $filas = $this->aceptaciones->porIdentidad($identidadId, $identidad->email);
        $aceptaciones = [];
        $clavesDoc = [];
        foreach ($filas as $fila) {
            $aceptaciones[] = [
                ...$fila,
                'canal_etiqueta' => EtiquetasCanalLegal::etiqueta($fila['canal']),
            ];
            $idioma = $fila['idioma'] === 'ca' ? 'ca' : 'es';
            $clavesDoc['condiciones.' . $fila['condiciones_version'] . '.' . $idioma] = [
                'condiciones',
                $fila['condiciones_version'],
                $idioma,
            ];
            $clavesDoc['privacidad.' . $fila['privacidad_version'] . '.' . $idioma] = [
                'privacidad',
                $fila['privacidad_version'],
                $idioma,
            ];
        }
        $documentos = [];
        foreach ($clavesDoc as $clave => [$tipo, $version, $idioma]) {
            if (isset($documentos[$clave])) {
                continue;
            }
            $doc = $this->documentos->documento($tipo, $version, $idioma);
            $documentos[$clave] = [
                'tipo' => $doc->tipo,
                'version' => $doc->version,
                'idioma' => $doc->idioma,
                'hash' => $doc->hashSha256,
                'texto' => $doc->texto,
            ];
        }

        return [
            'generado' => (new DateTimeImmutable())->format('c'),
            'operador' => $this->operador->toArray(),
            'usuario' => [
                'id' => (int) $identidad->id,
                'email' => $identidad->email,
                'alias' => $identidad->alias,
                'nombre' => $identidad->nombre,
                'es_admin' => $identidad->esAdmin,
                'email_verificado_at' => $identidad->emailVerificadoAt?->format('c'),
            ],
            'aceptaciones' => $aceptaciones,
            'documentos' => $documentos,
        ];
    }
}
