<?php

declare(strict_types=1);

namespace src\arqueo\application;

use InvalidArgumentException;
use src\apuntes\application\ListarApuntes;
use src\arqueo\domain\services\DetectarCapuchinos;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\shared\domain\value_objects\Dinero;

final class BuscarCapuchinos
{
    public function __construct(
        private readonly ListarApuntes $listarApuntes,
        private readonly DetectarCapuchinos $detectar,
        private readonly ConfiguracionRepository $config,
    ) {
    }

    /**
     * @return array{
     *     aplicable: bool,
     *     diferencia: string,
     *     diferencia_es: string,
     *     apuntes: list<array<string, mixed>>
     * }
     */
    public function ejecutar(string $diferencia, ?string $hasta = null): array
    {
        $dif = Dinero::fromInput($diferencia);
        $cents = abs($dif->toCents());
        if (!$this->detectar->diferenciaCandidata($cents)) {
            return [
                'aplicable' => false,
                'diferencia' => $dif->toString(),
                'diferencia_es' => $dif->formatEs(),
                'apuntes' => [],
            ];
        }

        $hasta = $hasta !== null && $hasta !== ''
            ? $hasta
            : $this->config->get()->fechaCierre->format('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            throw new InvalidArgumentException('Fecha no válida');
        }

        $hallados = [];
        foreach ($this->listarApuntes->ejecutar(['origen' => 'C', 'hasta' => $hasta]) as $apunte) {
            $importe = Dinero::fromInput((string) $apunte['cantidad']);
            $alts = $this->detectar->alternativasQueExplican($importe->toCents(), $cents);
            if ($alts === []) {
                continue;
            }
            $apunte['alternativas'] = array_map(
                static function (int $c): array {
                    $d = Dinero::fromCents($c);

                    return [
                        'cantidad' => $d->toString(),
                        'cantidad_es' => $d->formatEs(),
                    ];
                },
                $alts,
            );
            $hallados[] = $apunte;
        }

        return [
            'aplicable' => true,
            'diferencia' => $dif->toString(),
            'diferencia_es' => $dif->formatEs(),
            'apuntes' => $hallados,
        ];
    }
}
