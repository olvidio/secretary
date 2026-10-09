<?php

declare(strict_types=1);

namespace src\mensajes\infrastructure\http;

use src\mensajes\application\ContarMensajesNoLeidos;
use src\mensajes\application\ListarMensajes;
use src\mensajes\application\MarcarMensajesLeidos;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class MensajeController
{
    public function __construct(
        private readonly ListarMensajes $listar,
        private readonly ContarMensajesNoLeidos $contar,
        private readonly MarcarMensajesLeidos $marcar,
    ) {
    }

    public function listar(Request $request, array $vars = []): Response
    {
        $id = $this->identidadId();
        if ($id === null) {
            return ContestarJson::error(_('Sesión caducada'), 401);
        }

        return ContestarJson::ok(['mensajes' => $this->listar->ejecutar($id)]);
    }

    public function contador(Request $request, array $vars = []): Response
    {
        $id = $this->identidadId();
        if ($id === null) {
            return ContestarJson::error(_('Sesión caducada'), 401);
        }

        return ContestarJson::ok(['pendientes' => $this->contar->ejecutar($id)]);
    }

    public function leidos(Request $request, array $vars = []): Response
    {
        $id = $this->identidadId();
        if ($id === null) {
            return ContestarJson::error(_('Sesión caducada'), 401);
        }
        $ids = $request->input('ids', []);
        if (!is_array($ids)) {
            $ids = [];
        }
        $this->marcar->ejecutar($id, array_map(static fn ($v): int => (int) $v, $ids));

        return ContestarJson::ok();
    }

    private function identidadId(): ?int
    {
        $id = isset($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : 0;

        return $id > 0 ? $id : null;
    }
}
