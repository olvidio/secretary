<?php

declare(strict_types=1);

namespace src\informes\domain\services;

use src\shared\domain\value_objects\Dinero;

final class CalculadoraE37
{
    /** Códigos que suman en Ingresos (y van a esa columna de la hoja). */
    private const INGRESOS = ['111', '112', '113', '12'];

    /** Gastos que restan del disponible (también se copian a la columna Gastos). */
    private const GASTOS_DISPONIBLE = ['21', '22', '23', '24', '25', '26', '27', '28'];

    /**
     * @return array<string, list<string>>
     */
    public static function mapaImportes(): array
    {
        return [
            'ingresos' => self::INGRESOS,
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
    }

    /**
     * Columnas de la hoja E37 (como el Excel), sin Fecha ni Concepto.
     *
     * @return list<array{clave: string, etiqueta: string, codigo: string}>
     */
    public static function columnasHoja(): array
    {
        return [
            ['clave' => 'ingresos', 'etiqueta' => 'Ingresos', 'codigo' => '111 112 113 12'],
            ['clave' => 'gastos', 'etiqueta' => 'Gastos', 'codigo' => ''],
            ['clave' => 'vivienda', 'etiqueta' => 'Vivienda', 'codigo' => '21'],
            ['clave' => 'ordinarios', 'etiqueta' => 'Orden', 'codigo' => '22'],
            ['clave' => 'ropa', 'etiqueta' => 'Ropa', 'codigo' => '23'],
            ['clave' => 'ca_crt', 'etiqueta' => 'car…', 'codigo' => '24'],
            ['clave' => 'medicos', 'etiqueta' => 'Médicos', 'codigo' => '25'],
            ['clave' => 'coche', 'etiqueta' => 'Coche', 'codigo' => '26'],
            ['clave' => 'estudios', 'etiqueta' => 'Estudios', 'codigo' => '27'],
            ['clave' => 'obl_econ', 'etiqueta' => 'Obel', 'codigo' => '28'],
            ['clave' => 'disponible', 'etiqueta' => 'Disponible', 'codigo' => ''],
            ['clave' => 'ay_fam', 'etiqueta' => 'Ayuda', 'codigo' => '4'],
            ['clave' => 'at_lab', 'etiqueta' => 'At.lab.', 'codigo' => '51 52'],
            ['clave' => 'nec_sede', 'etiqueta' => 'Necesidad', 'codigo' => '6'],
            ['clave' => 'lab_ap', 'etiqueta' => 'Lab.ap.', 'codigo' => '71 79'],
            ['clave' => 'saldo_final', 'etiqueta' => 'saldo F.', 'codigo' => ''],
            ['clave' => 'saldo_cc', 'etiqueta' => 'saldo', 'codigo' => '9'],
        ];
    }

    /**
     * Celdas de un movimiento: el importe en su columna de concepto y, si es
     * gasto 21–28, también en Gastos. Disponible, saldo F. y saldo c/c no van
     * en las filas (solo en el total).
     *
     * @return array<string, Dinero>
     */
    public static function celdasDeMovimiento(string $codigo, Dinero $importe): array
    {
        $out = [];
        $clave = self::claveDeCodigo($codigo);
        if ($clave !== null) {
            $out[$clave] = $importe;
        }
        if (in_array($codigo, self::GASTOS_DISPONIBLE, true)) {
            $out['gastos'] = $importe;
        }

        return $out;
    }

    public static function claveDeCodigo(string $codigo): ?string
    {
        foreach (self::mapaImportes() as $clave => $codigos) {
            if (in_array($codigo, $codigos, true)) {
                return $clave;
            }
        }

        return $codigo === '9' ? 'saldo_cc' : null;
    }

    /**
     * @param array<string, int> $porCodigoMaestro codigo_maestro => cents
     * @return array<string, Dinero>
     */
    public static function totalesDesdeMovimientos(array $porCodigoMaestro, int $saldoCcCents): array
    {
        $tot = [];
        foreach (self::mapaImportes() as $k => $codigos) {
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
