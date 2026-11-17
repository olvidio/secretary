<?php

declare(strict_types=1);

namespace src\personal\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaRepository;
use src\personal\domain\services\FiltroMovimientosPersonales;
use src\shared\domain\value_objects\Dinero;

/**
 * Buscador del libro personal: movimientos entre dos fechas cualesquiera
 * (por defecto el año en curso) filtrados por tipo, categoría, caja/banco,
 * texto e importe, con sus totales.
 */
final class BuscarMovimientosPersonales
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly ListarMovimientosPersonales $listar,
        private readonly CuentaRepository $cuentas,
    ) {
    }

    /**
     * @param array<string, mixed> $p desde, hasta, sentido, categoria_id, tesoreria, texto, importe_min, importe_max
     * @return array{desde:string, hasta:string, movimientos:list<array<string, mixed>>, totales:array<string, string|int>}
     */
    public function ejecutar(array $p): array
    {
        $anio = date('Y');
        $desde = $this->fecha($p['desde'] ?? null) ?? $anio . '-01-01';
        $hasta = $this->fecha($p['hasta'] ?? null) ?? $anio . '-12-31';
        if ($desde > $hasta) {
            throw new InvalidArgumentException(_("La fecha «desde» es posterior a «hasta»"));
        }
        $sentido = (string) ($p['sentido'] ?? '');
        if (!in_array($sentido, ['', 'ingreso', 'gasto', 'traspaso'], true)) {
            $sentido = '';
        }
        $min = $this->cents($p['importe_min'] ?? null);
        $max = $this->cents($p['importe_max'] ?? null);
        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidArgumentException(_("El importe mínimo es mayor que el máximo"));
        }
        $filtro = new FiltroMovimientosPersonales(
            $sentido,
            $this->categoriaConSubcuentas((int) ($p['categoria_id'] ?? 0)),
            trim((string) ($p['tesoreria'] ?? '')),
            trim((string) ($p['texto'] ?? '')),
            $min,
            $max,
        );
        $movs = $filtro->aplicar($this->listar->ejecutarEntre($desde, $hasta));
        $t = FiltroMovimientosPersonales::totales($movs);

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'movimientos' => $movs,
            'totales' => [
                'numero' => $t['numero'],
                'ingresos_es' => Dinero::fromCents($t['ingresos'])->formatEs(),
                'gastos_es' => Dinero::fromCents($t['gastos'])->formatEs(),
                'neto_es' => Dinero::fromCents($t['neto'])->formatEs(),
            ],
        ];
    }

    private function fecha(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
        if ($d === false || $d->format('Y-m-d') !== $s) {
            throw new InvalidArgumentException(sprintf(_("Fecha no válida: %s"), $s));
        }

        return $s;
    }

    private function cents(mixed $v): ?int
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') {
            return null;
        }
        try {
            return abs(Dinero::fromInput($s)->toCents());
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException(sprintf(_("Importe no válido: %s"), $s));
        }
    }

    /** @return list<int> */
    private function categoriaConSubcuentas(int $id): array
    {
        if ($id <= 0) {
            return [];
        }
        $ctx = $this->ambito->ejecutar();
        $hijos = [];
        foreach ($this->cuentas->listarDePersona($ctx->centroId, $ctx->personaId, 'X') as $c) {
            if ($c->id !== null && $c->padreId !== null) {
                $hijos[$c->padreId][] = $c->id;
            }
        }
        $ids = [$id];
        for ($i = 0; $i < count($ids); $i++) {
            foreach ($hijos[$ids[$i]] ?? [] as $hijo) {
                if (!in_array($hijo, $ids, true)) {
                    $ids[] = $hijo;
                }
            }
        }

        return $ids;
    }
}
