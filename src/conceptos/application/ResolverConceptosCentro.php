<?php

declare(strict_types=1);

namespace src\conceptos\application;

use src\conceptos\domain\entity\Concepto;
use src\conceptos\domain\services\ReglasConceptoPlan;
use src\plan\domain\contracts\PartidaLaboresRepository;
use src\plan\domain\contracts\PlanConceptoRepository;

/** Catálogo efectivo de un centro: plan (sin 7x) + partidas VII del centro. */
final class ResolverConceptosCentro
{
    public function __construct(
        private readonly PlanConceptoRepository $planConceptos,
        private readonly PartidaLaboresRepository $partidasLabores,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listar(int $centroId, ?string $cuenta = null): array
    {
        $planId = $this->planConceptos->planIdDeCentro($centroId);
        if ($planId === null) {
            return [];
        }
        $ordenesCapituloVII = [];
        foreach ($this->planConceptos->listar($planId, 'P') as $c) {
            if (ReglasConceptoPlan::esCapituloVII($c->codigo, $c->cuenta)) {
                $ordenesCapituloVII[$c->codigo] = $c->orden;
            }
        }

        $out = [];
        foreach ($this->planConceptos->listar($planId, $cuenta) as $c) {
            if (ReglasConceptoPlan::esCapituloVII($c->codigo, $c->cuenta)) {
                continue;
            }
            $out[] = $c->toArray();
        }
        if ($cuenta === null || $cuenta === 'P') {
            foreach ($this->partidasLabores->paraCentro($centroId) as $p) {
                $codigo = $p['codigo'];
                $nombre = $p['etiqueta'];
                $orden = $ordenesCapituloVII[$codigo] ?? (int) $p['orden'];
                $out[] = (new Concepto(
                    $codigo,
                    'P',
                    $nombre,
                    $codigo . ' ' . $nombre,
                    'gasto',
                    $orden,
                ))->toArray();
            }
        }
        usort($out, static fn (array $a, array $b): int => [$a['cuenta'], $a['orden'], $a['codigo']]
            <=> [$b['cuenta'], $b['orden'], $b['codigo']]);

        return $out;
    }

    public function buscar(int $centroId, string $cuenta, string $codigo): ?Concepto
    {
        $cuenta = strtoupper(trim($cuenta));
        $codigo = trim($codigo);
        foreach ($this->listar($centroId, $cuenta) as $c) {
            if ($c['cuenta'] === $cuenta && $c['codigo'] === $codigo) {
                return new Concepto(
                    $c['codigo'],
                    $c['cuenta'],
                    $c['nombre'],
                    $c['descripcion'],
                    $c['naturaleza'],
                    (int) $c['orden'],
                );
            }
        }

        return null;
    }
}
