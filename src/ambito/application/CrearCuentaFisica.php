<?php

declare(strict_types=1);

namespace src\ambito\application;

use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaFisicaRepository;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Cuenta;
use src\ambito\domain\entity\CuentaFisica;

final class CrearCuentaFisica
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly CuentaFisicaRepository $fisicas,
        private readonly CuentaRepository $cuentas,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{fisica: CuentaFisica, cuentas: list<Cuenta>}
     */
    public function ejecutar(array $datos): array
    {
        $tipo = strtolower(trim((string) ($datos['tipo'] ?? '')));
        if (!in_array($tipo, ['caja', 'banco'], true)) {
            throw new InvalidArgumentException('El tipo debe ser caja o banco');
        }
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio');
        }
        $iban = isset($datos['iban']) && trim((string) $datos['iban']) !== ''
            ? trim((string) $datos['iban'])
            : null;

        $contexto = $this->ambito->ejecutar();
        $centroId = $contexto->centroId;
        $orden = $this->fisicas->maxOrdenPorTipo($centroId, $tipo) + 1;

        $fisica = $this->fisicas->guardar(
            new CuentaFisica(null, $centroId, $tipo, $nombre, $iban, $orden, true)
        );
        if ($fisica->id === null) {
            throw new InvalidArgumentException('No se pudo crear la cuenta física');
        }

        $maestro = strtoupper($tipo === 'caja' ? 'CAJA' : 'BANCO');
        $cuentasCreadas = [];
        foreach (['P', 'G'] as $libro) {
            $codigo = sprintf('%s.%d/%s', $maestro, $orden, $libro);
            $cuenta = new Cuenta(
                null,
                $centroId,
                null,
                $fisica->id,
                null,
                $libro,
                $codigo,
                sprintf('%s · %s', $nombre, $libro),
                sprintf('Cuenta de mayor de %s en el libro %s (D10)', $nombre, $libro),
                'tesoreria',
                'deudora',
                $maestro,
                true,
                $orden,
                true,
            );
            $cuentasCreadas[] = $this->cuentas->guardar($cuenta);
        }

        return ['fisica' => $fisica, 'cuentas' => $cuentasCreadas];
    }
}
