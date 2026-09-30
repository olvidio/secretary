<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use PDO;
use src\acceso\domain\contracts\IdentidadRepository;
use src\shared\domain\contracts\EnviadorCorreo;

/** Borra una cuenta que no tiene libro personal ni vínculo con ninguna entidad. */
final class EliminarCuentaSinVinculos
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly EnviadorCorreo $correo,
        private readonly PDO $pdo,
    ) {
    }

    public function ejecutar(int $identidadId, bool $confirmar): void
    {
        if (!$confirmar) {
            throw new InvalidArgumentException(_("Hay que confirmar el borrado del usuario"));
        }
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null) {
            throw new InvalidArgumentException(_("Usuario no encontrado"));
        }
        if ($identidad->esAdmin) {
            throw new InvalidArgumentException(_("No se puede eliminar al administrador de plataforma"));
        }
        if ($this->identidades->esCuentaPersonal($identidadId) || $this->identidades->esCuentaSecretarioCentro($identidadId)) {
            throw new InvalidArgumentException(_("Esta cuenta tiene libro o entidad; use el borrado que corresponde."));
        }
        if ($this->identidades->personasDe($identidadId) !== [] || $this->identidades->centrosDe($identidadId) !== []) {
            throw new InvalidArgumentException(_("Esta cuenta tiene libro o entidad; use el borrado que corresponde."));
        }

        $this->pdo->prepare(
            'DELETE FROM remesa_solicitudes_detalle WHERE solicitada_por = :id'
        )->execute([':id' => $identidadId]);
        $this->pdo->prepare(
            'UPDATE solicitudes_vinculo_centro SET resolved_by = NULL WHERE resolved_by = :id'
        )->execute([':id' => $identidadId]);

        $email = $identidad->email;
        $nombre = $identidad->nombre !== '' ? $identidad->nombre : ($identidad->alias ?? $email);
        $this->identidades->eliminar($identidadId);
        $this->avisar($email, $nombre);
    }

    private function avisar(string $email, string $nombre): void
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return;
        }
        $nombre = trim($nombre) !== '' ? trim($nombre) : $email;
        $this->correo->enviar(
            $email,
            _('Su cuenta en Secretario ha sido cancelada'),
            sprintf(
                _("Hola %s,\n\nLe informamos de que su cuenta en Secretario ha sido dada de baja por el administrador de la plataforma. No tenía libro personal ni entidad vinculada.\n\nYa no podrá entrar con este alias.\n\nSi cree que se trata de un error, contacte con el administrador del servicio.\n"),
                $nombre,
            ),
        );
    }
}
