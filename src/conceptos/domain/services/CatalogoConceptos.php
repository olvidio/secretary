<?php

declare(strict_types=1);

namespace src\conceptos\domain\services;

final class CatalogoConceptos
{
    /**
     * Plan de cuentas del Secretario v8.
     *
     * @return list<array{codigo:string,cuenta:string,nombre:string,descripcion:string,naturaleza:string,orden:int}>
     */
    public static function todos(): array
    {
        $p = [
            ['111', 'Trabajo', '111 Trabajo: al neto de impuestos', 'ingreso', 10],
            ['112', 'Familia', '112 Familia', 'ingreso', 20],
            ['113', 'Varios', '113 Varios', 'ingreso', 30],
            ['12', 'Extraordinarios', '12 Extraordinarios: de la dl', 'ingreso', 40],
            ['21', 'Vivienda', '21 Vivienda: cantidad para contribuir a los gastos de la casa', 'gasto', 50],
            ['22', 'Ordinarios', '22 Ordinarios: higiene, comida, móvil, transporte, gasolina, aparcamiento, medicinas no caras', 'gasto', 60],
            ['23', 'Ropa', '23 Ropa', 'gasto', 70],
            ['24', 'ca, crt y cv', '24 ca, crt y cv', 'gasto', 80],
            ['25', 'Médicos', '25 Médicos: incluye medicinas de cierta cuantía', 'gasto', 90],
            ['26', 'Coche, viajes', '26 Coche, viajes: mantenimiento y viajes largos', 'gasto', 100],
            ['27', 'Estudios y otros', '27 Estudios y otros: matrícula, suscripciones, academia, colegio profesional, cuota clubes', 'gasto', 110],
            ['28', 'Obligaciones económicas', '28 Obligaciones económicas: inversiones, créditos. No impuestos', 'gasto', 120],
            ['4', 'Ayudas familiares', '4 Ayudas familiares', 'gasto', 130],
            ['51', 'Atención sacerdotal de mujeres', '51 Atención sacerdotal de mujeres', 'gasto', 140],
            ['52', 'Atención crt, cv y otras actividades', '52 Atención crt, cv y otras actividades', 'gasto', 150],
            ['6', 'Necesidades de la casa / sede', '6 Necesidades de la casa / sede', 'gasto', 160],
            ['71', 'Necesidades generales', '71 Necesidades generales', 'gasto', 170],
            ['72', 'Fundació Montseny', '72 Fundació Montseny', 'gasto', 180],
            ['73', 'Fundació Proas', '73 Fundació Proas', 'gasto', 190],
            ['74', 'Prelatura', '74 Prelatura', 'gasto', 200],
            ['75', 'Associació Montroig', '75 Associació Montroig', 'gasto', 210],
            ['76', 'Associació Assitència i Salut', '76 Associació Assitència i Salut', 'gasto', 220],
            ['77', 'Proico', '77 Proico', 'gasto', 230],
            ['78', 'Casa Escrivá', '78 Casa Escrivá', 'gasto', 240],
            ['79', '', '79', 'gasto', 250],
            ['9', 'Saldo en las c/c personales', '9 Saldo en las c/c personales (no afecta a caja/banco)', 'saldo', 260],
        ];
        $g = [
            ['11', 'Vivienda / local', '11 Vivienda / local: para n contribución a gastos generales; para agd vivienda propia', 'ingreso', 10],
            ['12', 'Donativos', '12 Donativos: conseguidos por personas del ctr o del patronato', 'ingreso', 20],
            ['13', 'Otras ayudas', '13 Otras ayudas: compensación gastos de otro ctr', 'ingreso', 30],
            ['14', 'Ayudas de los de la casa', '14 Ayudas de los de la casa: contribución a los gastos del ctr', 'ingreso', 40],
            ['15', 'Ayudas extraordinarias', '15 Ayudas extraordinarias: contribución de la dl', 'ingreso', 50],
            ['201', 'Agua', '201 Agua', 'gasto', 60],
            ['202', 'Luz', '202 Luz', 'gasto', 70],
            ['203', 'Teléfono', '203 Teléfono', 'gasto', 80],
            ['204', 'Gas', '204 Gas', 'gasto', 90],
            ['205', 'Otros suministros', '205 Otros suministros', 'gasto', 100],
            ['206', 'Comunidad vecinos, seguros e impuestos', '206 Comunidad vecinos, seguros e impuestos', 'gasto', 110],
            ['207', 'Alquileres', '207 Alquileres', 'gasto', 120],
            ['208', 'Instalación y conservación', '208 Instalación y conservación', 'gasto', 130],
            ['209', 'Administración: sueldos y Seguridad Social', '209 Administración: sueldos y Seguridad Social', 'gasto', 140],
            ['210', 'Administración: resto', '210 Administración: resto', 'gasto', 150],
            ['211', 'Asociación', '211 Asociación: cantidad que el ctr paga a la Asociación gestora', 'gasto', 160],
            ['212', 'Suscripciones, papelería, libros', '212 Suscripciones, papelería, libros', 'gasto', 170],
            ['213', 'Coches', '213 Coches', 'gasto', 180],
            ['214', 'Reyes', '214 Reyes', 'gasto', 190],
            ['215', 'Otros', '215 Otros', 'gasto', 200],
            ['32', 'Disponible a 1 de enero', '32 Disponible a 1 de enero', 'disponible', 210],
            ['41', 'Banco a Caja', '41 Banco a Caja', 'transferencia', 220],
            ['42', 'Caja a banco', '42 Caja a banco', 'transferencia', 230],
        ];
        $out = [];
        foreach ($p as [$codigo, $nombre, $desc, $nat, $orden]) {
            $out[] = [
                'codigo' => $codigo,
                'cuenta' => 'P',
                'nombre' => $nombre,
                'descripcion' => $desc,
                'naturaleza' => $nat,
                'orden' => $orden,
            ];
        }
        foreach ($g as [$codigo, $nombre, $desc, $nat, $orden]) {
            $out[] = [
                'codigo' => $codigo,
                'cuenta' => 'G',
                'nombre' => $nombre,
                'descripcion' => $desc,
                'naturaleza' => $nat,
                'orden' => $orden,
            ];
        }

        return $out;
    }

    /** @return list<string> */
    public static function codigosCuenta(string $cuenta): array
    {
        $codigos = [];
        foreach (self::todos() as $c) {
            if ($c['cuenta'] === $cuenta) {
                $codigos[] = $c['codigo'];
            }
        }

        return $codigos;
    }
}
