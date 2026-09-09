<?php

declare(strict_types=1);

namespace src\arqueo\application;

use DateTimeImmutable;
use src\ambito\application\ResolverAmbitoActual;
use src\arqueo\domain\contracts\ArqueoRepository;
use src\arqueo\domain\entity\Arqueo;
use src\shared\domain\value_objects\Dinero;

final class GuardarArqueo
{
    public function __construct(
        private readonly ArqueoRepository $repo,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    /**
     * @param array<string, mixed> $desglose
     */
    public function ejecutar(
        string $cuenta,
        string $fecha,
        array $desglose,
        ?int $cuentaFisicaId = null,
    ): Arqueo {
        $calc = self::calcular($desglose);
        $contexto = $this->ambito->ejecutar();

        return $this->repo->guardar(new Arqueo(
            null,
            strtoupper($cuenta),
            new DateTimeImmutable($fecha),
            $desglose,
            $calc['dinero'],
            $calc['vales'],
            $calc['total'],
            $cuentaFisicaId,
            $contexto->ejercicioId,
        ));
    }

    /**
     * @param array<string, mixed> $desglose
     * @return array{dinero:Dinero,vales:Dinero,total:Dinero}
     */
    public static function calcular(array $desglose): array
    {
        $dinero = Dinero::zero();
        foreach (['billetes' => [500, 200, 100, 50, 20, 10, 5], 'monedas' => [2, 1, 0.5, 0.2, 0.1, 0.05, 0.02, 0.01]] as $grupo => $valores) {
            $cantidades = $desglose[$grupo] ?? [];
            foreach ($valores as $i => $valor) {
                $n = (float) ($cantidades[$i] ?? 0);
                if ($n === 0.0) {
                    continue;
                }
                $dinero = $dinero->add(Dinero::fromInput((string) ($n * $valor)));
            }
        }
        $vales = Dinero::zero();
        foreach (['vales', 'cheques'] as $grupo) {
            foreach ($desglose[$grupo] ?? [] as $importe) {
                if ($importe === '' || $importe === null) {
                    continue;
                }
                $vales = $vales->add(Dinero::fromInput((string) $importe));
            }
        }

        return [
            'dinero' => $dinero,
            'vales' => $vales,
            'total' => $dinero->add($vales),
        ];
    }
}
