<?php

declare(strict_types=1);

namespace src\ambito\application;

use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaFisicaRepository;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\CuentaFisica;

final class DesactivarCuentaFisica
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly CuentaFisicaRepository $fisicas,
        private readonly CuentaRepository $cuentas,
    ) {
    }

    public function ejecutar(int $cuentaFisicaId): CuentaFisica
    {
        $contexto = $this->ambito->ejecutar();
        $fisica = $this->fisicas->porId($cuentaFisicaId);
        if ($fisica === null || $fisica->centroId !== $contexto->centroId) {
            throw new InvalidArgumentException('Cuenta física no encontrada');
        }
        if (!$fisica->activo) {
            throw new InvalidArgumentException('La cuenta física ya está desactivada');
        }

        $activasDelTipo = $this->fisicas->contarActivasPorTipo($contexto->centroId, $fisica->tipo);
        if ($activasDelTipo <= 1) {
            $etiqueta = $fisica->tipo === 'caja' ? 'caja' : 'banco';
            throw new InvalidArgumentException(
                sprintf('No se puede desactivar la única %s activa del centro', $etiqueta)
            );
        }

        $desactivada = new CuentaFisica(
            $fisica->id,
            $fisica->centroId,
            $fisica->tipo,
            $fisica->nombre,
            $fisica->iban,
            $fisica->orden,
            false,
        );
        $this->cuentas->desactivarPorCuentaFisicaId($cuentaFisicaId);

        return $this->fisicas->guardar($desactivada);
    }
}
