<?php

declare(strict_types=1);

namespace src\administracion\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\application\NotificarBajaSecretarioAVinculados;
use src\acceso\application\NotificarInicioBajaCentro;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\services\PlazoBajaCentro;

final class ProgramarBajaCuentaCentro
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly ResumenBajaCuentaCentro $resumen,
        private readonly NotificarInicioBajaCentro $notificarSecretario,
        private readonly NotificarBajaSecretarioAVinculados $notificarVinculados,
    ) {
    }

    public function ejecutar(int $identidadId, bool $confirmar): void
    {
        if (!$confirmar) {
            throw new InvalidArgumentException(_("Hay que confirmar la baja"));
        }
        $prev = $this->resumen->ejecutar($identidadId);
        if (!($prev['puede_borrar'] ?? false)) {
            throw new InvalidArgumentException(
                (string) ($prev['motivo_bloqueo'] ?? _('No se puede programar la baja de esta cuenta'))
            );
        }

        $centros = $this->identidades->centrosDe($identidadId);
        if ($centros === []) {
            throw new InvalidArgumentException(_("La cuenta no tiene centros vinculados"));
        }

        $respaldo = [];
        $centroIds = [];
        $nombresCentro = [];
        foreach ($centros as $v) {
            $respaldo[] = ['centro_id' => $v->centroId, 'rol' => $v->rol];
            $centroIds[] = $v->centroId;
            $nombresCentro[] = $v->nombre;
        }

        $ahora = new DateTimeImmutable();
        $ejecutar = $ahora->modify('+' . PlazoBajaCentro::dias() . ' days');
        $vinculados = $this->identidades->cuentasPersonalesVinculadasACentros($centroIds, $identidadId);

        $this->identidades->guardarRespaldoCentrosBaja($identidadId, $respaldo);
        $this->identidades->desvincularTodosCentros($identidadId);
        $this->identidades->programarBajaCentro($identidadId, $ahora, $ejecutar);

        $this->notificarSecretario->ejecutar($identidadId, $ejecutar);
        $this->notificarVinculados->ejecutar($nombresCentro, $vinculados);
    }
}
