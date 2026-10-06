<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;

final class CambiarAmbitoUsuario
{
    public function __construct(
        private readonly ListarAmbitosIdentidad $ambitos,
        private readonly CambiarTipoUsuario $cambiarTipo,
        private readonly CambiarCentroUsuario $cambiarCentro,
    ) {
    }

    /**
     * @return array{
     *     nivel: string,
     *     centros: list<array{centro_id:int, codigo:string, nombre:string, rol:string}>,
     *     persona_id: ?int,
     *     centro_id: ?int,
     *     siguiente: string
     * }
     */
    public function ejecutar(int $identidadId, string $valor): array
    {
        $valor = trim($valor);
        $lista = $this->ambitos->ejecutar($identidadId);
        $permitidos = array_column($lista['opciones'], 'valor');
        if (!in_array($valor, $permitidos, true)) {
            throw new InvalidArgumentException(_("Ámbito no permitido"));
        }

        if ($valor === 'persona') {
            $cambio = $this->cambiarTipo->ejecutar($identidadId, 'persona');

            return [
                'nivel' => $cambio['nivel'],
                'centros' => $cambio['centros'],
                'persona_id' => $cambio['persona_id'],
                'centro_id' => null,
                'siguiente' => $cambio['siguiente'],
            ];
        }

        if (!str_starts_with($valor, 'centro:')) {
            throw new InvalidArgumentException(_("Ámbito no permitido"));
        }
        $centroId = (int) substr($valor, strlen('centro:'));
        if ($centroId <= 0) {
            throw new InvalidArgumentException(_("Ámbito no permitido"));
        }
        $cambio = $this->cambiarTipo->ejecutar($identidadId, 'centro');
        $this->cambiarCentro->ejecutar($identidadId, $centroId);

        return [
            'nivel' => $cambio['nivel'],
            'centros' => $cambio['centros'],
            'persona_id' => null,
            'centro_id' => $centroId,
            'siguiente' => '/',
        ];
    }
}
