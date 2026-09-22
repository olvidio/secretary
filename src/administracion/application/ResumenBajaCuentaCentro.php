<?php

declare(strict_types=1);

namespace src\administracion\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\services\PlazoBajaCentro;

final class ResumenBajaCuentaCentro
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $identidadId): array
    {
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null) {
            throw new InvalidArgumentException(_("Usuario no encontrado"));
        }
        if ($identidad->esAdmin) {
            throw new InvalidArgumentException(_("No se puede eliminar al administrador de plataforma"));
        }
        if ($this->identidades->bajaCentroPendiente($identidadId) !== null) {
            return [
                'id' => $identidadId,
                'alias' => $identidad->alias,
                'email' => $identidad->email,
                'nombre' => $identidad->nombre,
                'es_personal' => false,
                'es_secretario' => true,
                'puede_borrar' => false,
                'motivo_bloqueo' => _('Esta cuenta ya está en baja programada. Use Reactivar si procede.'),
                'datos' => null,
            ];
        }
        if (!$this->identidades->esCuentaSecretarioCentro($identidadId)) {
            return [
                'id' => $identidadId,
                'es_secretario' => false,
                'puede_borrar' => false,
                'motivo_bloqueo' => _('No es una cuenta de secretario de centro.'),
                'datos' => null,
            ];
        }

        $centros = $this->identidades->centrosDe($identidadId);
        $centroIds = array_map(static fn ($v) => $v->centroId, $centros);
        $vinculados = $this->identidades->cuentasPersonalesVinculadasACentros($centroIds, $identidadId);
        $dias = PlazoBajaCentro::dias();
        $ejecutar = (new DateTimeImmutable())->modify('+' . $dias . ' days');

        $centrosTxt = array_map(static fn ($v) => $v->nombre . ' (' . $v->codigo . ')', $centros);
        $unicoSecretario = [];
        foreach ($centros as $v) {
            if ($this->identidades->contarSecretariosDeCentro($v->centroId) <= 1) {
                $unicoSecretario[] = $v->nombre;
            }
        }

        $datos = [
            'centros' => count($centros),
            'vinculados_aviso' => count($vinculados),
            'dias_standby' => $dias,
            'purga_aproximada' => $ejecutar->format('Y-m-d'),
            'unico_secretario_en' => $unicoSecretario,
        ];

        $lineas = [
            sprintf(
                _('Se desactivará el acceso de secretario durante %d días; después se borrarán las credenciales.'),
                $dias,
            ),
            _('Los datos contables del centro no se borran.'),
        ];
        if ($centrosTxt !== []) {
            $lineas[] = _('Centros afectados: ') . implode(', ', $centrosTxt);
        }
        if ($vinculados !== []) {
            $lineas[] = sprintf(
                _('Se enviará un correo informativo a %d cuenta(s) personal(es) vinculada(s) a esos centros.'),
                count($vinculados),
            );
        }
        if ($unicoSecretario !== []) {
            $lineas[] = _('Atención: es el único secretario activo en: ') . implode(', ', $unicoSecretario)
                . '. ' . _('Asigne otro secretario antes si el centro debe seguir operando.');
        }

        return [
            'id' => $identidadId,
            'alias' => $identidad->alias,
            'email' => $identidad->email,
            'nombre' => $identidad->nombre,
            'es_personal' => false,
            'es_secretario' => true,
            'puede_borrar' => true,
            'motivo_bloqueo' => null,
            'advertencia_unico_secretario' => $unicoSecretario !== [],
            'datos' => $datos,
            'texto_datos' => implode("\n", $lineas),
            'texto_conservacion' => _(
                'Durante el plazo un administrador de plataforma puede reactivar la cuenta. Los consentimientos legales se conservan.'
            ),
        ];
    }
}
