<?php

declare(strict_types=1);

namespace src\informes\domain\services;

use src\shared\domain\value_objects\Dinero;

final class CalculadoraE37
{
    /**
     * @param array<string, int> $porCodigoMaestro codigo_maestro => cents
     */
    public static function totalesDesdeMovimientos(array $porCodigoMaestro, int $saldoCcCents): array
    {
        $map = [
            'ingresos' => ['111', '112', '113', '12'],
            'vivienda' => ['21'],
            'ordinarios' => ['22'],
            'ropa' => ['23'],
            'ca_crt' => ['24'],
            'medicos' => ['25'],
            'coche' => ['26'],
            'estudios' => ['27'],
            'obl_econ' => ['28'],
            'ay_fam' => ['4'],
            'at_lab' => ['51', '52'],
            'nec_sede' => ['6'],
            'lab_ap' => ['71', '72', '73', '74', '75', '76', '77', '78', '79'],
        ];
        $tot = [];
        foreach ($map as $k => $codigos) {
            $tot[$k] = Dinero::zero();
            foreach ($codigos as $cod) {
                $cents = array_key_exists($cod, $porCodigoMaestro) ? $porCodigoMaestro[$cod] : 0;
                $tot[$k] = $tot[$k]->add(Dinero::fromCents($cents));
            }
        }
        $tot['saldo_cc'] = Dinero::fromCents($saldoCcCents);

        $gastos = $tot['vivienda']->add($tot['ordinarios'])->add($tot['ropa'])->add($tot['ca_crt'])
            ->add($tot['medicos'])->add($tot['coche'])->add($tot['estudios'])->add($tot['obl_econ']);
        $tot['gastos'] = $gastos;
        $tot['disponible'] = $tot['ingresos']->sub($gastos);
        $tot['saldo_final'] = $tot['disponible']
            ->sub($tot['ay_fam'])
            ->sub($tot['at_lab'])
            ->sub($tot['nec_sede'])
            ->sub($tot['lab_ap']);

        return $tot;
    }
}
