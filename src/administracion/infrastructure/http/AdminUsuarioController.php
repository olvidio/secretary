<?php

declare(strict_types=1);

namespace src\administracion\infrastructure\http;

use InvalidArgumentException;
use src\administracion\application\AdminDesvincularCentroUsuario;
use src\administracion\application\AdminDesvincularPersonaUsuario;
use src\administracion\application\EliminarUsuario;
use src\administracion\application\AdminReiniciarTotpUsuario;
use src\administracion\application\FusionarIdentidadesLegacy;
use src\administracion\application\ListarIdentidadesDuplicadasPorEmail;
use src\administracion\application\ListarUsuariosAdmin;
use src\administracion\application\ReactivarCuentaCentro;
use src\administracion\application\ResumenEliminacionUsuario;
use src\administracion\application\ResumenFusionIdentidadesLegacy;
use src\acceso\domain\contracts\IdentidadRepository;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class AdminUsuarioController
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly ListarUsuariosAdmin $listar,
        private readonly EliminarUsuario $eliminar,
        private readonly ResumenEliminacionUsuario $resumenBorrado,
        private readonly ReactivarCuentaCentro $reactivarCentro,
        private readonly AdminDesvincularCentroUsuario $desvincularCentro,
        private readonly AdminDesvincularPersonaUsuario $desvincularPersona,
        private readonly ListarIdentidadesDuplicadasPorEmail $duplicadosCorreo,
        private readonly ResumenFusionIdentidadesLegacy $resumenFusion,
        private readonly FusionarIdentidadesLegacy $fusionarLegacy,
        private readonly AdminReiniciarTotpUsuario $reiniciarTotp,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok([
            'usuarios' => $this->listar->ejecutar(),
            'duplicados_correo' => $this->duplicadosCorreo->ejecutar(),
        ]);
    }

    public function listDuplicadosCorreo(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok(['grupos' => $this->duplicadosCorreo->ejecutar()]);
    }

    public function previewFusionLegacy(Request $request, array $vars = []): Response
    {
        try {
            $email = trim((string) $request->input('email', ''));
            $principalId = (int) $request->input('identidad_principal_id', 0);

            return ContestarJson::ok($this->resumenFusion->ejecutar($email, $principalId));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function fusionarLegacy(Request $request, array $vars = []): Response
    {
        $datos = $request->json();
        $raw = $datos['confirmar'] ?? false;
        $confirmar = $raw === true || $raw === 'true' || $raw === '1' || $raw === 1;
        $operadorId = isset($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : 0;
        try {
            $resultado = $this->fusionarLegacy->ejecutar(
                (string) ($datos['email'] ?? ''),
                (int) ($datos['identidad_principal_id'] ?? 0),
                $operadorId,
                $confirmar,
            );

            return ContestarJson::ok([
                ...$resultado,
                'usuarios' => $this->listar->ejecutar(),
                'duplicados_correo' => $this->duplicadosCorreo->ejecutar(),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
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

            return ContestarJson::ok(['usuarios' => $this->listar->ejecutar()]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function reiniciarTotp(Request $request, array $vars = []): Response
    {
        $datos = $request->json();
        $raw = $datos['confirmar'] ?? false;
        $confirmar = $raw === true || $raw === 'true' || $raw === '1' || $raw === 1;
        $operadorId = isset($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : 0;
        try {
            $this->reiniciarTotp->ejecutar((int) ($vars['id'] ?? 0), $operadorId, $confirmar);

            return ContestarJson::ok(['usuarios' => $this->listar->ejecutar()]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function desvincularCentroVinculo(Request $request, array $vars = []): Response
    {
        $operadorId = isset($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : 0;
        try {
            $this->desvincularCentro->ejecutar(
                (int) ($vars['id'] ?? 0),
                (int) ($vars['centroId'] ?? 0),
                $operadorId,
            );

            return ContestarJson::ok(['usuarios' => $this->listar->ejecutar()]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function desvincularPersonaVinculo(Request $request, array $vars = []): Response
    {
        $operadorId = isset($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : 0;
        try {
            $this->desvincularPersona->ejecutar(
                (int) ($vars['id'] ?? 0),
                (int) ($vars['personaId'] ?? 0),
                $operadorId,
            );

            return ContestarJson::ok(['usuarios' => $this->listar->ejecutar()]);
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

            return ContestarJson::ok(['usuarios' => $this->listar->ejecutar()]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
