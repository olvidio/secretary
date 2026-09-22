<?php

declare(strict_types=1);

namespace src\administracion\infrastructure\http;

use InvalidArgumentException;
use src\administracion\application\EliminarUsuario;
use src\administracion\application\ReactivarCuentaCentro;
use src\administracion\application\ResumenEliminacionUsuario;
use src\acceso\domain\contracts\IdentidadRepository;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class AdminUsuarioController
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly EliminarUsuario $eliminar,
        private readonly ResumenEliminacionUsuario $resumenBorrado,
        private readonly ReactivarCuentaCentro $reactivarCentro,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok(['usuarios' => $this->identidades->listarTodas()]);
    }

    public function previewDelete(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok($this->resumenBorrado->ejecutar((int) ($vars['id'] ?? 0)));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function reactivate(Request $request, array $vars = []): Response
    {
        try {
            $this->reactivarCentro->ejecutar((int) ($vars['id'] ?? 0));

            return ContestarJson::ok(['usuarios' => $this->identidades->listarTodas()]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function delete(Request $request, array $vars = []): Response
    {
        $datos = $request->json();
        $raw = $datos['confirmar'] ?? false;
        $confirmar = $raw === true || $raw === 'true' || $raw === '1' || $raw === 1;
        $operadorId = isset($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : 0;
        try {
            $this->eliminar->ejecutar((int) ($vars['id'] ?? 0), $operadorId, $confirmar);

            return ContestarJson::ok(['usuarios' => $this->identidades->listarTodas()]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
