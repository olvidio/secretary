<?php

declare(strict_types=1);

namespace src\acceso\infrastructure\http;

use src\acceso\application\ConfirmarTotp;
use src\acceso\application\IniciarSesion;
use src\acceso\application\PrepararTotp;
use src\acceso\application\ResultadoLogin;
use src\acceso\application\VerificarSegundoFactor;
use src\acceso\domain\contracts\IdentidadRepository;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class AuthController
{
    public function __construct(
        private readonly IniciarSesion $iniciar,
        private readonly PrepararTotp $prepararTotp,
        private readonly ConfirmarTotp $confirmarTotp,
        private readonly VerificarSegundoFactor $verificarTotp,
        private readonly IdentidadRepository $identidades,
    ) {
    }

    public function csrf(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok(['csrf' => ProteccionCsrf::asegurarToken()]);
    }

    public function login(Request $request, array $vars = []): Response
    {
        $user = trim((string) $request->input('usuario', $request->input('email', '')));
        $pass = (string) $request->input('password', '');
        $res = $this->iniciar->ejecutar($user, $pass);
        if (!$res->ok()) {
            return $this->falloLogin($request, $res->mensaje);
        }
        $this->limpiarSesionParcial();
        session_regenerate_id(true);
        ProteccionCsrf::asegurarToken();
        $_SESSION['pending_identidad_id'] = $res->identidadId;
        $_SESSION['usuario'] = $res->nombre !== '' ? $res->nombre : $res->email;
        $_SESSION['pending_centros'] = $res->centros;
        $_SESSION['pending_nivel'] = $res->nivel;
        $_SESSION['pending_email'] = $res->email;
        $_SESSION['pending_persona_id'] = $res->personaId;
        if ($res->estado === 'autenticado') {
            $this->completarSesion($res);

            return $this->exitoLogin($request, $this->siguienteTrasAuth($res));
        }
        if ($res->estado === 'pendiente_activar') {
            return $this->exitoLogin($request, '/totp-activar');
        }

        return $this->exitoLogin($request, '/totp-verificar');
    }

    public function totpActivar(Request $request, array $vars = []): Response
    {
        $id = $this->pendingId();
        if ($id === null) {
            return Response::redirect('/login');
        }
        try {
            $datos = $this->prepararTotp->ejecutar($id);
        } catch (\InvalidArgumentException $e) {
            return $this->falloLogin($request, $e->getMessage());
        }
        $_SESSION['totp_secreto'] = $datos['secreto'];
        $_SESSION['totp_uri'] = $datos['uri'];

        return $this->esJson($request)
            ? ContestarJson::ok($datos)
            : Response::redirect('/totp-activar');
    }

    public function totpConfirmar(Request $request, array $vars = []): Response
    {
        $id = $this->pendingId();
        if ($id === null) {
            return $this->falloLogin($request, 'Sesión caducada');
        }
        $codigo = trim((string) $request->input('codigo', ''));
        try {
            $codigos = $this->confirmarTotp->ejecutar($id, $codigo);
        } catch (\InvalidArgumentException $e) {
            if ($this->esJson($request)) {
                return ContestarJson::error($e->getMessage(), 400);
            }
            $_SESSION['login_error'] = $e->getMessage();

            return Response::redirect('/totp-activar');
        }
        unset($_SESSION['totp_secreto'], $_SESSION['totp_uri']);
        $_SESSION['recovery_codes'] = $codigos;
        $nivel = (string) ($_SESSION['pending_nivel'] ?? 'centro');
        $personaId = isset($_SESSION['pending_persona_id']) ? (int) $_SESSION['pending_persona_id'] : null;
        if ($nivel === 'persona' && ($personaId === null || $personaId === 0)) {
            $personas = $this->identidades->personasDe($id);
            $personaId = $personas[0] ?? null;
        }
        $this->completarSesion(new ResultadoLogin(
            'autenticado',
            '',
            $id,
            (string) ($_SESSION['usuario'] ?? ''),
            (string) ($_SESSION['pending_email'] ?? ''),
            $nivel,
            $this->centrosDeIdentidad($id),
            $personaId,
        ));

        return $this->esJson($request)
            ? ContestarJson::ok(['codigos' => $codigos, 'siguiente' => '/totp-codigos'])
            : Response::redirect('/totp-codigos');
    }

    public function totpVerificar(Request $request, array $vars = []): Response
    {
        $id = $this->pendingId();
        if ($id === null) {
            return $this->falloLogin($request, 'Sesión caducada');
        }
        $codigo = trim((string) $request->input('codigo', ''));
        $res = $this->verificarTotp->ejecutar($id, $codigo);
        if ($res->estado !== 'autenticado') {
            if ($this->esJson($request)) {
                return ContestarJson::error($res->mensaje, 401);
            }
            $_SESSION['login_error'] = $res->mensaje;

            return Response::redirect('/totp-verificar');
        }
        $this->completarSesion($res);

        return $this->exitoLogin($request, $this->siguienteTrasAuth($res));
    }

    public function elegirCentro(Request $request, array $vars = []): Response
    {
        $id = isset($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : null;
        if ($id === null) {
            return Response::redirect('/login');
        }
        $centroId = (int) $request->input('centro_id', 0);
        $ok = false;
        foreach ($this->centrosDeIdentidad($id) as $c) {
            if ($c['centro_id'] === $centroId) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            if ($this->esJson($request)) {
                return ContestarJson::error('Centro no permitido', 403);
            }
            $_SESSION['login_error'] = 'Centro no permitido';

            return Response::redirect('/elegir-centro');
        }
        $_SESSION['centro_id'] = $centroId;

        return $this->exitoLogin($request, '/');
    }

    public function logout(Request $request, array $vars = []): Response
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        return Response::redirect('/login');
    }

    private function pendingId(): ?int
    {
        if (!empty($_SESSION['pending_identidad_id'])) {
            return (int) $_SESSION['pending_identidad_id'];
        }
        if (!empty($_SESSION['identidad_id'])) {
            return (int) $_SESSION['identidad_id'];
        }

        return null;
    }

    private function completarSesion(ResultadoLogin $res): void
    {
        session_regenerate_id(true);
        unset($_SESSION['pending_identidad_id']);
        $_SESSION['identidad_id'] = $res->identidadId;
        $_SESSION['usuario'] = $res->nombre !== '' ? $res->nombre : $res->email;
        $_SESSION['nivel'] = $res->nivel;
        $_SESSION['centros'] = $res->centros;
        if ($res->nivel === 'centro' && count($res->centros) === 1) {
            $_SESSION['centro_id'] = $res->centros[0]['centro_id'];
        } else {
            unset($_SESSION['centro_id']);
        }
        if ($res->nivel === 'persona' && $res->personaId !== null) {
            $_SESSION['persona_id'] = $res->personaId;
        } else {
            unset($_SESSION['persona_id']);
        }
        ProteccionCsrf::asegurarToken();
    }

    private function siguienteTrasAuth(ResultadoLogin $res): string
    {
        if ($res->nivel === 'persona') {
            return '/yo';
        }
        if ($res->nivel === 'centro' && count($res->centros) > 1 && empty($_SESSION['centro_id'])) {
            return '/elegir-centro';
        }

        return '/';
    }

    /** @return list<array{centro_id:int, codigo:string, nombre:string, rol:string}> */
    private function centrosDeIdentidad(int $identidadId): array
    {
        $out = [];
        foreach ($this->identidades->centrosDe($identidadId) as $v) {
            $out[] = [
                'centro_id' => $v->centroId,
                'codigo' => $v->codigo,
                'nombre' => $v->nombre,
                'rol' => $v->rol,
            ];
        }

        return $out;
    }

    private function limpiarSesionParcial(): void
    {
        unset(
            $_SESSION['identidad_id'],
            $_SESSION['pending_identidad_id'],
            $_SESSION['centro_id'],
            $_SESSION['persona_id'],
            $_SESSION['pending_persona_id'],
            $_SESSION['nivel'],
            $_SESSION['centros'],
            $_SESSION['recovery_codes'],
        );
    }

    private function falloLogin(Request $request, string $mensaje): Response
    {
        if ($this->esJson($request)) {
            return ContestarJson::error($mensaje, 401);
        }
        $_SESSION['login_error'] = $mensaje;

        return Response::redirect('/login');
    }

    private function exitoLogin(Request $request, string $destino): Response
    {
        if ($this->esJson($request)) {
            return ContestarJson::ok([
                'usuario' => $_SESSION['usuario'] ?? '',
                'siguiente' => $destino,
            ]);
        }

        return Response::redirect($destino);
    }

    private function esJson(Request $request): bool
    {
        return str_starts_with($request->path, '/api/')
            || str_contains($request->header('content-type') ?? '', 'application/json');
    }
}
