<?php

declare(strict_types=1);

namespace src\acceso\infrastructure\http;

use InvalidArgumentException;
use src\acceso\application\CambiarCentroUsuario;
use src\acceso\application\CambiarPersonaUsuario;
use src\acceso\application\CambiarPasswordUsuario;
use src\acceso\application\CambiarTipoUsuario;
use src\acceso\application\ConfirmarTotp;
use src\acceso\application\GuardarEmailUsuario;
use src\acceso\application\GuardarIdiomaUsuario;
use src\acceso\application\GuardarLayoutUsuario;
use src\acceso\application\ObtenerPreferenciasUsuario;
use src\acceso\application\PrepararTotp;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class PreferenciaController
{
    public function __construct(
        private readonly ObtenerPreferenciasUsuario $obtener,
        private readonly GuardarLayoutUsuario $guardarLayout,
        private readonly GuardarEmailUsuario $guardarEmail,
        private readonly GuardarIdiomaUsuario $guardarIdioma,
        private readonly CambiarCentroUsuario $cambiarCentro,
        private readonly CambiarPersonaUsuario $cambiarPersona,
        private readonly CambiarTipoUsuario $cambiarTipo,
        private readonly CambiarPasswordUsuario $cambiarPassword,
        private readonly PrepararTotp $prepararTotp,
        private readonly ConfirmarTotp $confirmarTotp,
    ) {
    }

    public function get(Request $request, array $vars = []): Response
    {
        $id = $this->identidadId();
        if ($id === null) {
            return ContestarJson::error(_("Sesión caducada"), 401);
        }
        try {
            $datos = $this->obtener->ejecutar($id);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 401);
        }
        $datos['nivel'] = (string) ($_SESSION['nivel'] ?? '');
        $datos['centro_id'] = !empty($_SESSION['centro_id']) ? (int) $_SESSION['centro_id'] : null;
        $datos['persona_id'] = !empty($_SESSION['persona_id']) ? (int) $_SESSION['persona_id'] : null;

        return ContestarJson::ok($datos);
    }

    public function guardarLayout(Request $request, array $vars = []): Response
    {
        $id = $this->identidadId();
        if ($id === null) {
            return ContestarJson::error(_("Sesión caducada"), 401);
        }
        try {
            $layout = $this->guardarLayout->ejecutar($id, (string) $request->input('layout', ''));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
        $_SESSION['layout'] = $layout;

        return ContestarJson::ok(['layout' => $layout]);
    }

    public function guardarMail(Request $request, array $vars = []): Response
    {
        $id = $this->identidadId();
        if ($id === null) {
            return ContestarJson::error(_("Sesión caducada"), 401);
        }
        try {
            $resultado = $this->guardarEmail->ejecutar($id, (string) $request->input('email', ''));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }

        return ContestarJson::ok([
            'email' => $resultado['email'],
            'pendiente_confirmacion' => $resultado['pendiente_confirmacion'],
            'mensaje' => $resultado['pendiente_confirmacion']
                ? _('Le hemos enviado un correo al nuevo buzón. Ábralo y pulse el enlace para confirmar el cambio.')
                : _('Correo actualizado.'),
        ]);
    }

    public function guardarIdioma(Request $request, array $vars = []): Response
    {
        $id = $this->identidadId();
        if ($id === null) {
            return ContestarJson::error(_("Sesión caducada"), 401);
        }
        try {
            $idioma = $this->guardarIdioma->ejecutar($id, (string) $request->input('idioma', ''));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
        $_SESSION['idioma'] = $idioma;

        return ContestarJson::ok(['idioma' => $idioma]);
    }

    public function guardarCentro(Request $request, array $vars = []): Response
    {
        $id = $this->identidadId();
        if ($id === null) {
            return ContestarJson::error(_("Sesión caducada"), 401);
        }
        try {
            $centroId = $this->cambiarCentro->ejecutar($id, (int) $request->input('centro_id', 0));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 403);
        }
        $_SESSION['centro_id'] = $centroId;
        $siguiente = (($_SESSION['nivel'] ?? '') === 'centro') ? '/' : '/cuenta/centro';

        return ContestarJson::ok(['centro_id' => $centroId, 'siguiente' => $siguiente]);
    }

    public function guardarPersona(Request $request, array $vars = []): Response
    {
        $id = $this->identidadId();
        if ($id === null) {
            return ContestarJson::error(_("Sesión caducada"), 401);
        }
        try {
            $prefs = $this->obtener->ejecutar($id);
            if (!$prefs['puede_elegir_persona_activa']) {
                return ContestarJson::error(_("No hay varios vínculos de persona entre los que elegir."), 403);
            }
            $personaId = $this->cambiarPersona->ejecutar($id, (int) $request->input('persona_id', 0));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 403);
        }
        $_SESSION['persona_id'] = $personaId;
        $siguiente = (($_SESSION['nivel'] ?? '') === 'persona') ? '/yo' : '/cuenta/persona';

        return ContestarJson::ok(['persona_id' => $personaId, 'siguiente' => $siguiente]);
    }

    public function guardarPassword(Request $request, array $vars = []): Response
    {
        $id = $this->identidadId();
        if ($id === null) {
            return ContestarJson::error(_("Sesión caducada"), 401);
        }
        try {
            $this->cambiarPassword->ejecutar(
                $id,
                (string) $request->input('password_actual', $request->input('actual', '')),
                (string) $request->input('password', $request->input('nueva', '')),
                (string) $request->input('password_confirm', $request->input('confirmacion', '')),
            );
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }

        return ContestarJson::ok([]);
    }

    public function totpPreparar(Request $request, array $vars = []): Response
    {
        $id = $this->identidadId();
        if ($id === null) {
            return ContestarJson::error(_("Sesión caducada"), 401);
        }
        try {
            $datos = $this->prepararTotp->ejecutar($id);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }

        return ContestarJson::ok($datos);
    }

    public function totpConfirmar(Request $request, array $vars = []): Response
    {
        $id = $this->identidadId();
        if ($id === null) {
            return ContestarJson::error(_("Sesión caducada"), 401);
        }
        $codigo = trim((string) $request->input('codigo', ''));
        try {
            $codigos = $this->confirmarTotp->ejecutar($id, $codigo);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }

        return ContestarJson::ok(['codigos' => $codigos]);
    }

    public function guardarTipo(Request $request, array $vars = []): Response
    {
        $id = $this->identidadId();
        if ($id === null) {
            return ContestarJson::error(_("Sesión caducada"), 401);
        }
        try {
            $prefs = $this->obtener->ejecutar($id);
            if (!$prefs['puede_cambiar_tipo']) {
                return ContestarJson::error(_("Esta cuenta no puede cambiar de tipo. Use otra cuenta o cierre sesión."), 403);
            }
            $cambio = $this->cambiarTipo->ejecutar($id, (string) $request->input('tipo', ''));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
        $_SESSION['nivel'] = $cambio['nivel'];
        $_SESSION['centros'] = $cambio['centros'];
        if ($cambio['nivel'] === 'centro') {
            if (empty($_SESSION['centro_id']) && $cambio['centros'] !== []) {
                $_SESSION['centro_id'] = $cambio['centros'][0]['centro_id'];
            }
            unset($_SESSION['persona_id']);
        } else {
            if ($cambio['persona_id'] !== null) {
                $_SESSION['persona_id'] = $cambio['persona_id'];
            } else {
                unset($_SESSION['persona_id']);
            }
        }

        return ContestarJson::ok([
            'nivel' => $cambio['nivel'],
            'siguiente' => $cambio['siguiente'],
        ]);
    }

    private function identidadId(): ?int
    {
        $id = isset($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : 0;

        return $id > 0 ? $id : null;
    }
}
