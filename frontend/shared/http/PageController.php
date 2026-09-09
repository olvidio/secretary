<?php

declare(strict_types=1);

namespace frontend\shared\http;

use frontend\shared\view\View;
use src\acceso\application\PrepararTotp;
use src\acceso\infrastructure\http\ProteccionCsrf;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class PageController
{
    public function __construct(
        private readonly View $view,
        private readonly PrepararTotp $prepararTotp,
    ) {
    }

    public function login(Request $request, array $vars = []): Response
    {
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        return Response::html($this->view->standalone('login/view/login.php', [
            'error' => $error,
            'csrf' => ProteccionCsrf::asegurarToken(),
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
            'csrf' => ProteccionCsrf::asegurarToken(),
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
            'csrf' => ProteccionCsrf::asegurarToken(),
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
            'csrf' => ProteccionCsrf::asegurarToken(),
            'centros' => $centros,
        ]));
    }

    public function page(Request $request, array $vars): Response
    {
        $view = (string) ($vars['view'] ?? 'shared/view/home.php');
        $nav = (string) ($vars['nav'] ?? '');

        return Response::html($this->view->page($view, [
            'usuario' => $_SESSION['usuario'] ?? '',
            'nav' => $nav,
            'csrf' => ProteccionCsrf::asegurarToken(),
            'cuentaEntrada' => $vars['cuenta'] ?? null,
            'cuentaInforme' => $vars['informe'] ?? null,
            'cuentaArqueo' => $vars['arqueo'] ?? null,
            'cuentaPresupuesto' => $vars['presupuesto'] ?? null,
        ]));
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

    public function yoRemesas(Request $request, array $vars = []): Response
    {
        return $this->paginaYo('personal/view/remesas.php', 'yo-remesas');
    }

    private function paginaYo(string $view, string $nav): Response
    {
        return Response::html($this->view->pageYo($view, [
            'usuario' => $_SESSION['usuario'] ?? '',
            'nav' => $nav,
            'csrf' => ProteccionCsrf::asegurarToken(),
        ]));
    }

    private function siguienteHome(): string
    {
        if (($_SESSION['nivel'] ?? '') === 'persona') {
            return '/yo';
        }
        $centros = $_SESSION['centros'] ?? [];
        if (is_array($centros) && count($centros) > 1 && empty($_SESSION['centro_id'])) {
            return '/elegir-centro';
        }

        return '/';
    }
}
