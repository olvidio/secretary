<?php

declare(strict_types=1);

namespace src\shared\infrastructure;

use DI\Container;
use DI\ContainerBuilder;
use FastRoute\Dispatcher;
use frontend\shared\view\View;
use PDO;
use src\acceso\application\AutorizarPeticion;
use src\acceso\infrastructure\http\ProteccionCsrf;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;
use function FastRoute\simpleDispatcher;

final class Kernel
{
    public function __construct(private readonly Container $container)
    {
    }

    public static function boot(): self
    {
        $root = dirname(__DIR__, 3);
        $envFile = $root . '/.env';
        if (is_readable($envFile)) {
            $dotenv = \Dotenv\Dotenv::createUnsafeImmutable($root);
            $dotenv->safeLoad();
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
        ProteccionCsrf::asegurarToken();
        $builder = new ContainerBuilder();
        $builder->addDefinitions($root . '/src/shared/config/dependencies.php');
        $builder->addDefinitions([
            View::class => static fn () => new View($root . '/frontend'),
        ]);
        $container = $builder->build();

        return new self($container);
    }

    public function handle(?Request $request = null): Response
    {
        $request ??= Request::fromGlobals();
        $dispatcher = simpleDispatcher(function ($r) {
            $root = dirname(__DIR__, 3);
            foreach ([$root . '/src/shared/config/routes.php', $root . '/frontend/shared/config/routes.php'] as $file) {
                $def = require $file;
                $def($r);
            }
        });
        $routeInfo = $dispatcher->dispatch($request->method, $request->path);
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                return new Response('No encontrado', 404);
            case Dispatcher::METHOD_NOT_ALLOWED:
                return new Response('Método no permitido', 405);
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                if (is_array($handler) && isset($handler[2]) && is_array($handler[2])) {
                    $vars = $handler[2] + $vars;
                    $handler = [$handler[0], $handler[1]];
                }
                $response = $this->authorize($request, $handler);
                if ($response !== null) {
                    return $response;
                }
                [$class, $method] = $handler;
                $controller = $this->container->get($class);
                return $controller->{$method}($request, $vars);
        }

        return new Response('Error', 500);
    }

    /**
     * @param array{0:class-string,1:string} $handler
     */
    private function authorize(Request $request, array $handler): ?Response
    {
        $autorizar = $this->container->get(AutorizarPeticion::class);
        $identidadId = isset($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : null;
        $pendingId = isset($_SESSION['pending_identidad_id']) ? (int) $_SESSION['pending_identidad_id'] : null;
        $centroId = !empty($_SESSION['centro_id']) ? (int) $_SESSION['centro_id'] : null;
        $personaId = !empty($_SESSION['persona_id']) ? (int) $_SESSION['persona_id'] : null;
        $decision = $autorizar->ejecutar(
            $handler[0],
            $handler[1],
            $request->method,
            ProteccionCsrf::valido($request),
            $identidadId,
            $pendingId,
            $centroId,
            (string) ($_SESSION['nivel'] ?? ''),
            str_starts_with($request->path, '/api/'),
            $personaId,
        );
        if ($decision->permitido) {
            return null;
        }
        if ($decision->redirect !== null) {
            return Response::redirect($decision->redirect);
        }
        if (str_starts_with($request->path, '/api/')) {
            return Response::json(['ok' => false, 'error' => $decision->error ?? 'No autenticado'], $decision->status);
        }
        if ($decision->error === 'Token CSRF inválido') {
            $_SESSION['login_error'] = 'La sesión ha caducado o el navegador no guardó la cookie. '
                . 'Recargue la página e inténtelo de nuevo.';
            ProteccionCsrf::renovarToken();

            return Response::redirect($this->destinoTrasCsrfInvalido($request->path));
        }

        return new Response($decision->error ?? 'No autorizado', $decision->status);
    }

    private function destinoTrasCsrfInvalido(string $path): string
    {
        return match ($path) {
            '/totp-activar', '/api/totp/confirmar' => '/totp-activar',
            '/totp-verificar', '/api/totp/verificar' => '/totp-verificar',
            '/elegir-centro', '/api/centros/elegir' => '/elegir-centro',
            default => '/login',
        };
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function pdo(): PDO
    {
        return $this->container->get(PDO::class);
    }
}
