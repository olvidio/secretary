<?php

declare(strict_types=1);

namespace src\acceso\infrastructure\http;

use src\acceso\application\ConfirmarTotp;
use src\acceso\application\IniciarSesion;
use src\acceso\application\NotificarRegistroUsuario;
use src\acceso\application\PrepararTotp;
use src\acceso\application\ReenviarCorreoVerificacion;
use src\acceso\application\RegistrarCentro;
use src\acceso\application\RegistrarUsuario;
use src\acceso\application\ResolverPersonaActiva;
use src\acceso\application\ResultadoLogin;
use src\acceso\application\VerificarSegundoFactor;
use src\acceso\domain\contracts\IdentidadRepository;
use src\legal\application\LecturaAceptacion;
use src\legal\application\RegistrarAceptacion;
use src\legal\domain\services\CatalogoDocumentosLegales;
use src\legal\infrastructure\http\HuellaAceptacionHttp;
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
        private readonly RegistrarUsuario $registrar,
        private readonly RegistrarCentro $registrarCentro,
        private readonly NotificarRegistroUsuario $notificarRegistro,
        private readonly ReenviarCorreoVerificacion $reenviarVerificacion,
        private readonly ResolverPersonaActiva $resolverPersona,
        private readonly RegistrarAceptacion $registrarAceptacion,
        private readonly CatalogoDocumentosLegales $documentos,
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
        if ($res->desconocido()) {
            return $this->invitarRegistro($request, $user, $res->mensaje);
        }
        if (!$res->ok()) {
            return $this->falloLogin($request, $res->mensaje, $user);
        }
        $this->limpiarSesionParcial();
        session_regenerate_id(true);
        ProteccionCsrf::asegurarToken();
        if ($res->estado === 'pendiente_elegir_cuenta') {
            $_SESSION['login_cuentas_elegibles'] = $res->cuentas;

            return $this->exitoLogin($request, '/elegir-cuenta');
        }
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
            return $this->falloLogin($request, _("Sesión caducada"));
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
            $personaId = $this->resolverPersona->ejecutar($id, null)['persona_id'];
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
            return $this->falloLogin($request, _("Sesión caducada"));
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

    public function elegirCuenta(Request $request, array $vars = []): Response
    {
        $cuentas = $_SESSION['login_cuentas_elegibles'] ?? null;
        if (!is_array($cuentas) || $cuentas === []) {
            return Response::redirect('/login');
        }
        $identidadId = (int) $request->input('identidad_id', 0);
        $permitida = false;
        foreach ($cuentas as $c) {
            if ((int) ($c['identidad_id'] ?? 0) === $identidadId) {
                $permitida = true;
                break;
            }
        }
        if (!$permitida) {
            if ($this->esJson($request)) {
                return ContestarJson::error(_("Cuenta no permitida"), 403);
            }
            $_SESSION['login_error'] = _("Cuenta no permitida");

            return Response::redirect('/elegir-cuenta');
        }
        unset($_SESSION['login_cuentas_elegibles']);
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null) {
            return Response::redirect('/login');
        }
        $res = $this->iniciar->continuarConIdentidad($identidad);
        if (!$res->ok()) {
            return $this->falloLogin($request, $res->mensaje);
        }
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
                return ContestarJson::error(_("Centro no permitido"), 403);
            }
            $_SESSION['login_error'] = _("Centro no permitido");

            return Response::redirect('/elegir-centro');
        }
        $_SESSION['centro_id'] = $centroId;

        return $this->exitoLogin($request, '/');
    }

    public function elegirPersona(Request $request, array $vars = []): Response
    {
        $id = isset($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : null;
        if ($id === null) {
            return Response::redirect('/login');
        }
        $personaId = (int) $request->input('persona_id', 0);
        $ok = false;
        foreach ($this->personasVinculoDeIdentidad($id) as $p) {
            if ($p['persona_id'] === $personaId) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            if ($this->esJson($request)) {
                return ContestarJson::error(_("Persona no permitida"), 403);
            }
            $_SESSION['login_error'] = _("Persona no permitida");

            return Response::redirect('/elegir-persona');
        }
        $_SESSION['persona_id'] = $personaId;

        return $this->exitoLogin($request, '/yo');
    }

    public function registro(Request $request, array $vars = []): Response
    {
        $tipoCuenta = strtolower(trim((string) $request->input('tipo_cuenta', 'persona')));
        $alias = trim((string) $request->input('usuario', $request->input('alias', '')));
        $email = trim((string) $request->input('email', ''));
        $nombre = trim((string) $request->input('nombre', ''));
        $pass = (string) $request->input('password', '');
        $confirm = (string) $request->input('password_confirm', $request->input('password2', ''));
        $codigoCentro = trim((string) $request->input('codigo_centro', ''));
        $nombreCentro = trim((string) $request->input('nombre_centro', ''));
        $tipoCentro = strtolower(trim((string) $request->input('centro_tipo', 'n')));
        $acepto = LecturaAceptacion::marcada($request->input('acepto_condiciones', false));
        $previo = [
            'tipo_cuenta' => $tipoCuenta,
            'usuario' => $alias,
            'email' => $email,
            'nombre' => $nombre,
            'codigo_centro' => $codigoCentro,
            'nombre_centro' => $nombreCentro,
            'centro_tipo' => $tipoCentro,
        ];
        try {
            if ($tipoCuenta === 'centro') {
                $alta = $this->registrarCentro->ejecutar(
                    $codigoCentro,
                    $nombreCentro,
                    $tipoCentro,
                    $alias,
                    $email,
                    $pass,
                    $confirm,
                    $nombre,
                    $acepto,
                );
            } else {
                $alta = $this->registrar->ejecutar($alias, $email, $pass, $confirm, $nombre, $acepto);
            }
            $idioma = (string) ($_SESSION['idioma'] ?? 'es');
            $this->registrarAceptacion->ejecutar(
                (int) $alta['identidad']->id,
                'formulario_registro',
                $this->documentos->textoCasillaRegistro($idioma),
                HuellaAceptacionHttp::desde(
                    $request,
                    $idioma,
                    strtolower(trim($email)),
                    strtolower(trim($alias)),
                ),
            );
            if ($alta['enviar_correo']) {
                $this->notificarRegistro->ejecutar((int) $alta['identidad']->id, $alta['token_verificacion']);
            }
        } catch (\InvalidArgumentException $e) {
            return $this->falloRegistro($request, $e->getMessage(), $previo);
        } catch (\Throwable $e) {
            return $this->falloRegistro(
                $request,
                _('No se pudo enviar el correo de confirmación. Compruebe la configuración de correo o póngase en contacto con el administrador.'),
                $previo,
            );
        }
        if ($this->esJson($request)) {
            if (!$alta['enviar_correo']) {
                return ContestarJson::ok([
                    'siguiente' => '/login',
                    'email' => strtolower(trim($email)),
                    'mensaje' => _('Cuenta creada. Ya puede entrar.'),
                ]);
            }

            return ContestarJson::ok([
                'siguiente' => '/registro-enviado',
                'email' => strtolower(trim($email)),
            ]);
        }
        if (!$alta['enviar_correo']) {
            $_SESSION['login_ok'] = _('Cuenta creada. Ya puede entrar.');
            $_SESSION['login_usuario'] = strtolower(trim($alias));

            return Response::redirect('/login');
        }
        $_SESSION['registro_email'] = strtolower(trim($email));

        return Response::redirect('/registro-enviado');
    }

    public function reenviarVerificacion(Request $request, array $vars = []): Response
    {
        $email = trim((string) $request->input('email', ''));
        try {
            $this->reenviarVerificacion->ejecutar($email);
        } catch (\InvalidArgumentException $e) {
            if ($this->esJson($request)) {
                return ContestarJson::error($e->getMessage(), 400);
            }
            $_SESSION['login_error'] = $e->getMessage();

            return Response::redirect('/registro-enviado');
        }
        if ($this->esJson($request)) {
            return ContestarJson::ok(['mensaje' => _('Si la cuenta existe y no está confirmada, le hemos enviado un nuevo correo.')]);
        }
        $_SESSION['registro_email'] = strtolower(trim($email));
        $_SESSION['registro_ok'] = _('Si la cuenta existe y no está confirmada, le hemos enviado un nuevo correo.');

        return Response::redirect('/registro-enviado');
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
        if ($res->identidadId !== null) {
            $_SESSION['layout'] = $this->identidades->layoutDe($res->identidadId);
            $_SESSION['idioma'] = $this->identidades->idiomaDe($res->identidadId);
        }
        if ($res->nivel === 'centro' && count($res->centros) === 1) {
            $_SESSION['centro_id'] = $res->centros[0]['centro_id'];
        } else {
            unset($_SESSION['centro_id']);
        }
        if ($res->nivel === 'persona') {
            $_SESSION['personas_vinculo'] = $this->personasVinculoDeIdentidad($res->identidadId ?? 0);
            if ($res->personaId !== null) {
                $_SESSION['persona_id'] = $res->personaId;
            } else {
                unset($_SESSION['persona_id']);
            }
        } else {
            unset($_SESSION['persona_id'], $_SESSION['personas_vinculo']);
        }
        ProteccionCsrf::asegurarToken();
    }

    private function siguienteTrasAuth(ResultadoLogin $res): string
    {
        if ($res->nivel === 'admin') {
            return '/admin';
        }
        if ($res->nivel === 'persona') {
            $vinculos = $this->personasVinculoDeIdentidad($res->identidadId ?? 0);
            if (count($vinculos) > 1 && ($res->personaId === null || empty($_SESSION['persona_id']))) {
                return '/elegir-persona';
            }

            return '/yo';
        }
        if ($res->nivel === 'centro' && count($res->centros) > 1 && empty($_SESSION['centro_id'])) {
            return '/elegir-centro';
        }

        return '/';
    }

    /** @return list<array<string, mixed>> */
    private function personasVinculoDeIdentidad(int $identidadId): array
    {
        if ($identidadId <= 0) {
            return [];
        }

        return $this->identidades->personasVinculoDe($identidadId);
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
            $_SESSION['personas_vinculo'],
            $_SESSION['pending_persona_id'],
            $_SESSION['nivel'],
            $_SESSION['centros'],
            $_SESSION['layout'],
            $_SESSION['idioma'],
            $_SESSION['recovery_codes'],
        );
    }

    private function falloLogin(Request $request, string $mensaje, string $usuario = ''): Response
    {
        if ($this->esJson($request)) {
            return ContestarJson::error($mensaje, 401);
        }
        $_SESSION['login_error'] = $mensaje;
        if ($usuario !== '') {
            $_SESSION['login_usuario'] = $usuario;
        }

        return Response::redirect('/login');
    }

    private function invitarRegistro(Request $request, string $usuario, string $mensaje): Response
    {
        if ($this->esJson($request)) {
            return Response::json([
                'ok' => false,
                'error' => $mensaje,
                'siguiente' => '/registro',
                'usuario' => $usuario,
            ], 401);
        }
        $_SESSION['login_error'] = $mensaje;
        $qs = $usuario !== '' ? '?usuario=' . rawurlencode($usuario) : '';

        return Response::redirect('/registro' . $qs);
    }

    /** @param array<string, mixed> $previo */
    private function falloRegistro(Request $request, string $mensaje, array $previo): Response
    {
        if ($this->esJson($request)) {
            return ContestarJson::error($mensaje, 400);
        }
        $_SESSION['login_error'] = $mensaje;
        $_SESSION['registro'] = $previo;

        return Response::redirect('/registro');
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
