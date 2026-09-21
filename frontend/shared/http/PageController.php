<?php

declare(strict_types=1);

namespace frontend\shared\http;

use frontend\shared\config\CatalogoMenus;
use frontend\shared\view\View;
use src\acceso\application\ConfirmarEmailRegistro;
use src\acceso\application\PrepararTotp;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\value_objects\IdiomaUsuario;
use src\acceso\domain\value_objects\LayoutPantalla;
use src\acceso\infrastructure\http\ProteccionCsrf;
use src\ambito\domain\contracts\CentroRepository;
use src\legal\domain\services\CatalogoDocumentosLegales;
use src\legal\domain\services\DatosOperador;
use src\legal\infrastructure\http\HuellaAceptacionHttp;
use src\legal\infrastructure\markdown\RenderizadorMarkdownLegal;
use src\personal\domain\services\CatalogoBancosCsv;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;
use src\shared\infrastructure\VersiónDespliegue;

final class PageController
{
    public function __construct(
        private readonly View $view,
        private readonly PrepararTotp $prepararTotp,
        private readonly IdentidadRepository $identidades,
        private readonly CentroRepository $centros,
        private readonly ConfirmarEmailRegistro $confirmarEmail,
        private readonly CatalogoDocumentosLegales $documentos,
        private readonly DatosOperador $operador,
        private readonly VersiónDespliegue $versión,
    ) {
    }

    public function login(Request $request, array $vars = []): Response
    {
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);
        $usuario = (string) ($_SESSION['login_usuario'] ?? $request->query('usuario', '') ?? '');
        unset($_SESSION['login_usuario']);

        return Response::html($this->view->standalone('login/view/login.php', [
            'error' => $error,
            'csrf' => ProteccionCsrf::renovarToken(),
            'usuario' => $usuario,
        ]));
    }

    public function registro(Request $request, array $vars = []): Response
    {
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);
        $previo = $_SESSION['registro'] ?? [];
        unset($_SESSION['registro']);
        if (!is_array($previo)) {
            $previo = [];
        }
        $identificador = trim((string) ($request->query('usuario', '') ?? ''));
        $usuario = (string) ($previo['usuario'] ?? '');
        $email = (string) ($previo['email'] ?? '');
        $nombre = (string) ($previo['nombre'] ?? '');
        if ($usuario === '' && $email === '' && $identificador !== '') {
            if (filter_var($identificador, FILTER_VALIDATE_EMAIL) !== false) {
                $email = strtolower($identificador);
                $local = strtolower((string) strstr($identificador, '@', true));
                if (preg_match('/^[a-z][a-z0-9._-]{1,31}$/', $local) === 1) {
                    $usuario = $local;
                }
            } else {
                $usuario = strtolower($identificador);
            }
        }
        return Response::html($this->view->standalone('login/view/registro.php', [
            'error' => $error,
            'csrf' => ProteccionCsrf::renovarToken(),
            'usuario' => $usuario,
            'email' => $email,
            'nombre' => $nombre,
            'tipoCuenta' => (string) ($previo['tipo_cuenta'] ?? 'persona'),
            'codigoCentro' => (string) ($previo['codigo_centro'] ?? ''),
            'nombreCentro' => (string) ($previo['nombre_centro'] ?? ''),
            'centroTipo' => (string) ($previo['centro_tipo'] ?? 'n'),
            'textoAceptacion' => $this->documentos->textoCasillaRegistro($this->idiomaUsuario()),
            'versionCondiciones' => $this->documentos->vigente('condiciones')->version,
            'versionPrivacidad' => $this->documentos->vigente('privacidad')->version,
        ]));
    }

    public function registroEnviado(Request $request, array $vars = []): Response
    {
        $email = (string) ($_SESSION['registro_email'] ?? '');
        unset($_SESSION['registro_email']);
        $ok = $_SESSION['registro_ok'] ?? null;
        unset($_SESSION['registro_ok']);
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        return Response::html($this->view->standalone('login/view/registro_enviado.php', [
            'email' => $email,
            'ok' => $ok,
            'error' => $error,
            'csrf' => ProteccionCsrf::renovarToken(),
        ]));
    }

    public function confirmarEmail(Request $request, array $vars = []): Response
    {
        $token = trim((string) ($request->query('token', '') ?? ''));
        if ($token !== '') {
            try {
                $this->confirmarEmail->ejecutar(
                    $token,
                    null,
                    HuellaAceptacionHttp::desde($request, $this->idiomaUsuario()),
                );
                $ok = true;
                $mensaje = _('Su correo está confirmado. Ya puede entrar.');
            } catch (\InvalidArgumentException $e) {
                $ok = false;
                $mensaje = $e->getMessage();
            }

            return Response::html($this->view->standalone('login/view/confirmar_email.php', [
                'ok' => $ok,
                'mensaje' => $mensaje,
            ]));
        }

        return Response::redirect('/login');
    }

    public function documentoLegal(Request $request, array $vars = []): Response
    {
        $tipo = (string) ($vars['tipo'] ?? '');
        $idioma = $this->idiomaUsuario();
        $doc = $this->documentos->vigente($tipo, $idioma);
        $texto = strtr($doc->texto, [
            '{{OPERADOR_NOMBRE}}' => $this->operador->nombre,
            '{{OPERADOR_EMAIL}}' => $this->operador->email,
            '{{OPERADOR_DIRECCION}}' => $this->operador->direccion,
        ]);

        return Response::html($this->view->standalone('legal/view/documento.php', [
            'titulo' => $tipo === 'privacidad' ? _('Política de privacidad') : _('Condiciones de uso'),
            'version' => $doc->version,
            'cuerpoHtml' => RenderizadorMarkdownLegal::aHtml($texto),
            'operador' => $this->operador,
        ]));
    }

    public function licencia(Request $request, array $vars = []): Response
    {
        $ruta = dirname(__DIR__, 3) . '/LICENSE';
        if (!is_readable($ruta)) {
            return Response::html($this->view->standalone('legal/view/licencia.php', [
                'texto' => _('No se encuentra el fichero LICENSE.'),
            ]), 500);
        }

        return Response::html($this->view->standalone('legal/view/licencia.php', [
            'texto' => (string) file_get_contents($ruta),
        ]));
    }

    public function changelog(Request $request, array $vars = []): Response
    {
        $texto = $this->versión->textoChangelog();
        if ($texto === null) {
            return Response::html($this->view->standalone('legal/view/changelog.php', [
                'versionApp' => $this->versión->etiqueta(),
                'cuerpoHtml' => '<p>' . htmlspecialchars(_('No hay changelog disponible.'), ENT_QUOTES) . '</p>',
            ]), 404);
        }

        return Response::html($this->view->standalone('legal/view/changelog.php', [
            'versionApp' => $this->versión->etiqueta(),
            'cuerpoHtml' => RenderizadorMarkdownLegal::aHtml($texto),
        ]));
    }

    public function totpActivar(Request $request, array $vars = []): Response
    {
        $id = isset($_SESSION['pending_identidad_id']) ? (int) $_SESSION['pending_identidad_id'] : null;
        if ($id === null) {
            return Response::redirect('/login');
        }
        if (empty($_SESSION['totp_secreto'])) {
            try {
                $datos = $this->prepararTotp->ejecutar($id);
                $_SESSION['totp_secreto'] = $datos['secreto'];
                $_SESSION['totp_uri'] = $datos['uri'];
            } catch (\InvalidArgumentException $e) {
                $_SESSION['login_error'] = $e->getMessage();

                return Response::redirect('/totp-verificar');
            }
        }
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        return Response::html($this->view->standalone('login/view/totp_activar.php', [
            'error' => $error,
            'csrf' => ProteccionCsrf::renovarToken(),
            'secreto' => (string) ($_SESSION['totp_secreto'] ?? ''),
            'uri' => (string) ($_SESSION['totp_uri'] ?? ''),
        ]));
    }

    public function totpVerificar(Request $request, array $vars = []): Response
    {
        if (empty($_SESSION['pending_identidad_id']) && empty($_SESSION['identidad_id'])) {
            return Response::redirect('/login');
        }
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        return Response::html($this->view->standalone('login/view/totp_verificar.php', [
            'error' => $error,
            'csrf' => ProteccionCsrf::renovarToken(),
        ]));
    }

    public function totpCodigos(Request $request, array $vars = []): Response
    {
        $codigos = $_SESSION['recovery_codes'] ?? [];
        if (!is_array($codigos) || $codigos === []) {
            return Response::redirect('/');
        }

        return Response::html($this->view->standalone('login/view/totp_codigos.php', [
            'csrf' => ProteccionCsrf::asegurarToken(),
            'codigos' => $codigos,
            'siguiente' => $this->siguienteHome(),
        ]));
    }

    public function elegirCentro(Request $request, array $vars = []): Response
    {
        if (empty($_SESSION['identidad_id'])) {
            return Response::redirect('/login');
        }
        $centros = $_SESSION['centros'] ?? $_SESSION['pending_centros'] ?? [];
        if (!is_array($centros) || $centros === []) {
            return Response::redirect('/');
        }
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        return Response::html($this->view->standalone('login/view/elegir_centro.php', [
            'error' => $error,
            'csrf' => ProteccionCsrf::renovarToken(),
            'centros' => $centros,
        ]));
    }

    public function elegirPersona(Request $request, array $vars = []): Response
    {
        if (empty($_SESSION['identidad_id'])) {
            return Response::redirect('/login');
        }
        $id = (int) $_SESSION['identidad_id'];
        $personas = $_SESSION['personas_vinculo'] ?? null;
        if (!is_array($personas) || $personas === []) {
            $personas = $this->identidades->personasVinculoDe($id);
            $_SESSION['personas_vinculo'] = $personas;
        }
        if ($personas === []) {
            return Response::redirect('/yo/centros');
        }
        if (count($personas) === 1) {
            $_SESSION['persona_id'] = (int) $personas[0]['persona_id'];

            return Response::redirect('/yo');
        }
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        return Response::html($this->view->standalone('login/view/elegir_persona.php', [
            'error' => $error,
            'csrf' => ProteccionCsrf::renovarToken(),
            'personas' => $personas,
        ]));
    }

    public function page(Request $request, array $vars): Response
    {
        $view = (string) ($vars['view'] ?? 'shared/view/home.php');
        $nav = (string) ($vars['nav'] ?? '');

        $layout = $this->layoutUsuario();
        $grupoActivo = str_starts_with($nav, 'cuenta-')
            ? ''
            : CatalogoMenus::grupoDe($layout, $nav);

        return Response::html($this->view->page($view, [
            'usuario' => $_SESSION['usuario'] ?? '',
            'centroNombre' => self::nombreCentroSesion(),
            'nav' => $nav,
            'csrf' => ProteccionCsrf::asegurarToken(),
            'layout' => $layout,
            'idioma' => $this->idiomaUsuario(),
            'menuGrupos' => CatalogoMenus::grupos($layout),
            'menuGrupoActivo' => $grupoActivo,
            'cuentaEntrada' => $vars['cuenta'] ?? null,
            'cuentaInforme' => $vars['informe'] ?? null,
            'cuentaArqueo' => $vars['arqueo'] ?? null,
            'cuentaPresupuesto' => $vars['presupuesto'] ?? null,
            'textoAsumoNombres' => $this->documentos->textoCasillaNombres($this->idiomaUsuario()),
        ]));
    }

    public function cuenta(Request $request, array $vars): Response
    {
        if (($_SESSION['nivel'] ?? '') === 'persona') {
            return $this->paginaYo(
                (string) ($vars['view'] ?? 'acceso/view/cuenta_mail.php'),
                (string) ($vars['nav'] ?? 'cuenta-mail'),
            );
        }

        return $this->page($request, $vars);
    }

    public function yo(Request $request, array $vars = []): Response
    {
        return $this->paginaYo('personal/view/home.php', 'yo');
    }

    public function yoMovimientos(Request $request, array $vars = []): Response
    {
        return $this->paginaYo('personal/view/movimientos.php', 'yo-movimientos');
    }

    public function yoCategorias(Request $request, array $vars = []): Response
    {
        return $this->paginaYo('personal/view/categorias.php', 'yo-categorias');
    }

    public function yoBanco(Request $request, array $vars = []): Response
    {
        return $this->paginaYo('personal/view/banco.php', 'yo-banco', [
            'bancos' => CatalogoBancosCsv::todos(),
        ]);
    }

    public function yoRemesas(Request $request, array $vars = []): Response
    {
        if (!$this->mostrarRemesasPersona()) {
            return Response::redirect('/yo/centros');
        }

        return $this->paginaYo('personal/view/remesas.php', 'yo-remesas');
    }

    public function yoCierre(Request $request, array $vars = []): Response
    {
        return $this->paginaYo('personal/view/cierre.php', 'yo-cierre');
    }

    public function yoCentros(Request $request, array $vars = []): Response
    {
        return $this->paginaYo('personal/view/centros.php', 'yo-centros');
    }

    public function admin(Request $request, array $vars = []): Response
    {
        return Response::redirect('/admin/planes');
    }

    public function adminPlanes(Request $request, array $vars = []): Response
    {
        return $this->paginaAdmin('admin/view/planes.php', 'admin-planes');
    }

    public function adminCentros(Request $request, array $vars = []): Response
    {
        return $this->paginaAdmin('admin/view/centros.php', 'admin-centros');
    }

    public function adminUsuarios(Request $request, array $vars = []): Response
    {
        return $this->paginaAdmin('admin/view/usuarios.php', 'admin-usuarios');
    }

    public function adminLegal(Request $request, array $vars = []): Response
    {
        return $this->paginaAdmin('admin/view/legal.php', 'admin-legal');
    }

    public function yoAyuda(Request $request, array $vars = []): Response
    {
        return $this->paginaYo('ayuda/view/ayuda.php', 'yo-ayuda');
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function paginaYo(string $view, string $nav, array $extra = []): Response
    {
        return Response::html($this->view->pageYo($view, array_merge([
            'usuario' => $_SESSION['usuario'] ?? '',
            'nav' => $nav,
            'csrf' => ProteccionCsrf::asegurarToken(),
            'idioma' => $this->idiomaUsuario(),
            'mostrarRemesas' => $this->mostrarRemesasPersona(),
        ], $extra)));
    }

    private function paginaAdmin(string $view, string $nav): Response
    {
        return Response::html($this->view->pageAdmin($view, [
            'usuario' => $_SESSION['usuario'] ?? '',
            'nav' => $nav,
            'csrf' => ProteccionCsrf::asegurarToken(),
            'idioma' => $this->idiomaUsuario(),
            'menuItems' => CatalogoMenus::itemsAdmin(),
        ]));
    }

    private function mostrarRemesasPersona(): bool
    {
        $identidadId = isset($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : 0;
        if ($identidadId <= 0) {
            return false;
        }

        return $this->identidades->tienePersonaEnAlgunCentro($identidadId);
    }

    private function layoutUsuario(): string
    {
        $enSesion = $_SESSION['layout'] ?? null;
        if (is_string($enSesion) && $enSesion !== '') {
            try {
                return (new LayoutPantalla($enSesion))->valor;
            } catch (\InvalidArgumentException) {
            }
        }
        $id = isset($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : 0;
        if ($id <= 0) {
            return LayoutPantalla::EXCEL;
        }
        try {
            $layout = (new LayoutPantalla($this->identidades->layoutDe($id)))->valor;
        } catch (\InvalidArgumentException) {
            $layout = LayoutPantalla::EXCEL;
        }
        $_SESSION['layout'] = $layout;

        return $layout;
    }

    private function idiomaUsuario(): string
    {
        $enSesion = $_SESSION['idioma'] ?? null;
        if (is_string($enSesion) && $enSesion !== '') {
            try {
                return (new IdiomaUsuario($enSesion))->valor;
            } catch (\InvalidArgumentException) {
            }
        }
        $id = isset($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : 0;
        if ($id <= 0) {
            return IdiomaUsuario::ES;
        }
        try {
            $idioma = (new IdiomaUsuario($this->identidades->idiomaDe($id)))->valor;
        } catch (\InvalidArgumentException) {
            $idioma = IdiomaUsuario::ES;
        }
        $_SESSION['idioma'] = $idioma;

        return $idioma;
    }

    private function siguienteHome(): string
    {
        if (($_SESSION['nivel'] ?? '') === 'persona') {
            $personas = $_SESSION['personas_vinculo'] ?? [];
            if (is_array($personas) && count($personas) > 1 && empty($_SESSION['persona_id'])) {
                return '/elegir-persona';
            }

            return '/yo';
        }
        $centros = $_SESSION['centros'] ?? [];
        if (is_array($centros) && count($centros) > 1 && empty($_SESSION['centro_id'])) {
            return '/elegir-centro';
        }

        return '/';
    }

    private static function nombreCentroSesion(): string
    {
        $id = isset($_SESSION['centro_id']) ? (int) $_SESSION['centro_id'] : 0;
        $centros = $_SESSION['centros'] ?? [];
        if ($id <= 0 || !is_array($centros)) {
            return '';
        }
        foreach ($centros as $c) {
            if (!is_array($c)) {
                continue;
            }
            if ((int) ($c['centro_id'] ?? 0) === $id) {
                $nombre = (string) ($c['nombre'] ?? $c['codigo'] ?? '');

                return $nombre;
            }
        }

        return '';
    }
}
