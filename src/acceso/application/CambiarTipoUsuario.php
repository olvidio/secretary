<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;

final class CambiarTipoUsuario
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    /**
     * @return array{
     *     nivel: string,
     *     centros: list<array{centro_id:int, codigo:string, nombre:string, rol:string}>,
     *     persona_id: ?int,
     *     siguiente: string
     * }
     */
    public function ejecutar(int $identidadId, string $tipo): array
    {
        $tipo = strtolower(trim($tipo));
        if (!in_array($tipo, ['centro', 'persona'], true)) {
            throw new InvalidArgumentException('Tipo no válido (centro o persona)');
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
        $personas = $this->identidades->personasDe($identidadId);

        if ($tipo === 'centro') {
            if ($centros === []) {
                throw new InvalidArgumentException('Esta cuenta no es secretario de ningún centro');
            }
            if (!$this->identidades->totpConfirmado($identidadId)) {
                throw new InvalidArgumentException('Debe confirmar el segundo factor para entrar como secretario');
            }

            return [
                'nivel' => 'centro',
                'centros' => $centros,
                'persona_id' => null,
                'siguiente' => '/',
            ];
        }
        if ($personas === []) {
            throw new InvalidArgumentException(
                'Esta cuenta no está vinculada a una persona. El libro personal se crea al poner el correo en Nombres.'
            );
        }

        $personaId = count($personas) === 1 ? $personas[0] : null;

        return [
            'nivel' => 'persona',
            'centros' => $centros,
            'persona_id' => $personaId,
            'siguiente' => count($personas) > 1 ? '/elegir-persona' : '/yo',
        ];
    }
}
