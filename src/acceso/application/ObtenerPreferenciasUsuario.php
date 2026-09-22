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
     *     puede_cambiar_tipo: bool,
     *     puede_elegir_persona_activa: bool,
     *     totp_activo: bool,
     *     personas: list<array<string, mixed>>,
     *     email_pendiente: ?string
     * }
     */
    public function ejecutar(int $identidadId): array
    {
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null || $identidad->id === null) {
            throw new InvalidArgumentException(_("Sesión caducada"));
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
            'puede_persona' => ($personas = $this->identidades->personasDe($identidadId)) !== [],
            'puede_cambiar_tipo' => $centros !== [] && $personas !== [],
            'totp_activo' => $this->identidades->totpConfirmado($identidadId),
            'personas' => ($vinculos = $this->identidades->personasVinculoDe($identidadId)),
            'puede_elegir_persona_activa' => count($vinculos) > 1,
            'email_pendiente' => $this->identidades->emailPendienteDe($identidadId),
        ];
    }
}
