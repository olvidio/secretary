<?php

declare(strict_types=1);

namespace src\personal\application;

use src\asientos\domain\contracts\AsientoRepository;
use src\shared\domain\value_objects\Dinero;

final class ResumenMensualPersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly AsientoRepository $asientos,
        private readonly ListarMovimientosPersonales $listar,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(string $desde, string $hasta): array
    {
        $ctx = $this->ambito->ejecutar();
        $saldos = $this->asientos->saldosPorCuenta($ctx->centroId, $ctx->ejercicioId, null, $hasta, 'X');
        $caja = 0;
        $banco = 0;
        $porCategoria = [];
        foreach ($saldos as $row) {
            if ($row['persona_id'] !== $ctx->personaId) {
                continue;
            }
            if ($row['tipo'] === 'tesoreria' && $row['codigo_maestro'] === 'CAJA') {
                $caja = $row['saldo_cents'];
            }
            if ($row['tipo'] === 'tesoreria' && $row['codigo_maestro'] === 'BANCO') {
                $banco = $row['saldo_cents'];
            }
        }

        $ingresos = 0;
        $gastos = 0;
        $gastosCat = [];
        $ingresosCat = [];
        foreach ($this->listar->ejecutar($desde, $hasta) as $fila) {
            $cents = Dinero::fromInput((string) $fila['cantidad'])->toCents();
            $codigo = (string) ($fila['categoria_codigo'] ?? '');
            $nombre = (string) ($fila['categoria'] ?? '');
            if ($fila['sentido'] === 'ingreso') {
                $ingresos += $cents;
                if ($codigo !== '') {
                    $ingresosCat[$codigo] = [
                        'codigo' => $codigo,
                        'nombre' => $nombre,
                        'cents' => ($ingresosCat[$codigo]['cents'] ?? 0) + $cents,
                    ];
                }
            } elseif ($fila['sentido'] === 'gasto') {
                $gastos += $cents;
                if ($codigo !== '') {
                    $gastosCat[$codigo] = [
                        'codigo' => $codigo,
                        'nombre' => $nombre,
                        'cents' => ($gastosCat[$codigo]['cents'] ?? 0) + $cents,
                    ];
                }
            }
        }

        $fmt = static function (int $cents): array {
            $d = Dinero::fromCents($cents);

            return ['cents' => $cents, 'importe' => $d->toString(), 'importe_es' => $d->formatEs()];
        };

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'saldo' => $fmt($caja + $banco),
            'caja' => $fmt($caja),
            'banco' => $fmt($banco),
            'ingresos' => $fmt($ingresos),
            'gastos' => $fmt($gastos),
            'gastos_por_categoria' => array_values($gastosCat),
            'ingresos_por_categoria' => array_values($ingresosCat),
        ];
    }
}
