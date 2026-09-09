<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Cuenta;
use src\personal\domain\services\CatalogoMaestroPersonal;

final class CrearSubcuentaPersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly CuentaRepository $cuentas,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function ejecutar(array $datos): Cuenta
    {
        $ctx = $this->ambito->ejecutar();
        $maestro = trim((string) ($datos['codigo_maestro'] ?? ''));
        if (!CatalogoMaestroPersonal::existe($maestro)) {
            throw new InvalidArgumentException('El código maestro no existe en el plan principal');
        }
        $padre = $this->cuentas->buscar($ctx->centroId, $ctx->personaId, 'X', $maestro);
        if ($padre === null || $padre->id === null) {
            throw new InvalidArgumentException('No hay cuenta padre de ese código maestro');
        }
        $slug = strtolower(trim((string) ($datos['codigo'] ?? '')));
        $slug = preg_replace('/[^a-z0-9]/', '', $slug) ?? '';
        if ($slug === '' || strlen($slug) > 20) {
            throw new InvalidArgumentException('La etiqueta de la subcuenta debe ser alfanumérica (máx. 20)');
        }
        $codigo = $maestro . '.' . $slug;
        if ($this->cuentas->buscar($ctx->centroId, $ctx->personaId, 'X', $codigo) !== null) {
            throw new InvalidArgumentException('Esa subcuenta ya existe');
        }
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            $nombre = $codigo;
        }

        return $this->cuentas->guardar(new Cuenta(
            null,
            $ctx->centroId,
            $ctx->personaId,
            null,
            $padre->id,
            'X',
            $codigo,
            $nombre,
            'Subcuenta de ' . $padre->nombre,
            $padre->tipo,
            $padre->naturaleza,
            $maestro,
            true,
            $padre->orden,
        ));
    }
}
