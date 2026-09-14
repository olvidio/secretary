<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;

final class ObtenerPreferenciasUsuario
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    /**
     * @return array{
     *     email: string,
     *     alias: ?string,
     *     nombre: string,
     *     layout: string,
     *     idioma: string,
     *     centros: list<array{centro_id:int, codigo:string, nombre:string, rol:string}>,
     *     puede_centro: bool,
     *     puede_persona: bool,
     *     totp_activo: bool,
     *     personas: list<array<string, mixed>>
     * }
     */
    public function ejecutar(int $identidadId): array
    {
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null || $identidad->id === null) {
            throw new InvalidArgumentException('Sesión caducada');
        }
        $centros = [];
        foreach ($this->identidades->centrosDe($identidadId) as $v) {
            $centros[] = [
                'centro_id' => $v->centroId,
                'codigo' => $v->codigo,
                'nombre' => $v->nombre,
                'rol' => $v->rol,
            ];
        }

        return [
            'email' => $identidad->email,
            'alias' => $identidad->alias,
            'nombre' => $identidad->nombre,
            'layout' => $this->identidades->layoutDe($identidadId),
            'idioma' => $this->identidades->idiomaDe($identidadId),
            'centros' => $centros,
            'puede_centro' => $centros !== [],
            'puede_persona' => $this->identidades->personasDe($identidadId) !== [],
            'totp_activo' => $this->identidades->totpConfirmado($identidadId),
            'personas' => $this->identidades->personasVinculoDe($identidadId),
        ];
    }
}
