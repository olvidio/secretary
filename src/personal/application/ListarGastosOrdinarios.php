<?php

declare(strict_types=1);

namespace src\personal\application;

use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\value_objects\Dinero;

/** Movimientos del libro X imputados a la cuenta 22 (Ordinarios) y sus subcuentas. */
final class ListarGastosOrdinarios
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly PersonaRepository $personas,
        private readonly ListarMovimientosPersonales $listar,
    ) {
    }

    /**
     * @return array{nombre: string, lineas: list<array<string, string>>, total_es: string}
     */
    public function ejecutar(string $desde, string $hasta): array
    {
        $ctx = $this->ambito->ejecutar();
        $persona = $this->personas->porId($ctx->personaId);
        $datos = self::lineasDe($this->listar->ejecutar($desde, $hasta));
        $datos['nombre'] = $persona?->nombreCompleto() ?? '';

        return $datos;
    }

    public static function esCuentaOrdinarios(string $codigo): bool
    {
        if (AsegurarPlanPersonal::esPendiente($codigo)) {
            return false;
        }

        return $codigo === '22' || str_starts_with($codigo, '22.');
    }

    /**
     * @param list<array<string, mixed>> $movimientos
     * @return array{lineas: list<array<string, string>>, total_es: string}
     */
    public static function lineasDe(array $movimientos): array
    {
        $lineas = [];
        $total = 0;
        foreach ($movimientos as $fila) {
            $codigo = (string) ($fila['categoria_codigo'] ?? '');
            if (!self::esCuentaOrdinarios($codigo)) {
                continue;
            }
            $sentido = (string) ($fila['sentido'] ?? '');
            if ($sentido !== 'gasto' && $sentido !== 'ingreso') {
                continue;
            }
            $cents = Dinero::fromInput((string) ($fila['cantidad'] ?? '0'))->toCents();
            $firmado = $sentido === 'gasto' ? $cents : -$cents;
            $total += $firmado;
            $lineas[] = [
                'fecha' => (string) ($fila['fecha'] ?? ''),
                'nota' => (string) ($fila['nota'] ?? ''),
                'categoria' => (string) ($fila['categoria'] ?? ''),
                'categoria_codigo' => $codigo,
                'importe_es' => Dinero::fromCents($firmado)->formatEs(),
            ];
        }
        usort($lineas, static fn (array $a, array $b): int => [$a['fecha'], $a['categoria_codigo'], $a['nota']] <=> [$b['fecha'], $b['categoria_codigo'], $b['nota']]);

        return [
            'lineas' => $lineas,
            'total_es' => Dinero::fromCents($total)->formatEs(),
        ];
    }
}
