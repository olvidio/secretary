<?php

declare(strict_types=1);

namespace src\ambito\application;

use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaFisicaRepository;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\CuentaFisica;

final class EditarCuentaFisica
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly CuentaFisicaRepository $fisicas,
        private readonly CuentaRepository $cuentas,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(int $id, array $datos): CuentaFisica
    {
        $contexto = $this->ambito->ejecutar();
        $fisica = $this->fisicas->porId($id);
        if ($fisica === null || $fisica->centroId !== $contexto->centroId) {
            throw new InvalidArgumentException(_('Cuenta física no encontrada'));
        }
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException(_('El nombre es obligatorio'));
        }
        $iban = isset($datos['iban']) && trim((string) $datos['iban']) !== ''
            ? trim((string) $datos['iban'])
            : null;
        $guardada = $this->fisicas->guardar(new CuentaFisica(
            $fisica->id,
            $fisica->centroId,
            $fisica->tipo,
            $nombre,
            $iban,
            $fisica->orden,
            $fisica->activo,
        ));
        $this->cuentas->renombrarTesoreriaDeFisica($id, $nombre);

        return $guardada;
    }
}
