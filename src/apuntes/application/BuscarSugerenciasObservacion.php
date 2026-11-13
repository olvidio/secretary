<?php

declare(strict_types=1);

namespace src\apuntes\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\plan\domain\services\CatalogoPlanesContables;

/** Sugerencias de observaciones ya usadas en el libro (Entrada P/G). */
final class BuscarSugerenciasObservacion
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly ResolverAmbitoActual $ambito,
        private readonly ?CentroRepository $centros = null,
    ) {
    }

    /**
     * @return list<array{observaciones: string, concepto_codigo: string}>
     */
    public function ejecutar(string $texto, string $cuenta, string $iniciales): array
    {
        $cuenta = strtoupper(trim($cuenta));
        if (!in_array($cuenta, ['P', 'G'], true)) {
            throw new InvalidArgumentException(_("Cuenta P o G"));
        }
        $q = trim($texto);
        $ini = trim($iniciales);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $ctx = $this->ambito->ejecutar();
        if ($ini === '') {
            // Associació: no hay personas; se busca en todos los apuntes del centro.
            return $this->esClub($ctx->centroId)
                ? $this->asientos->sugerirPorGlosaDeCentro($ctx->centroId, $cuenta, $q, 12)
                : [];
        }

        return $this->asientos->sugerirPorGlosa($ctx->ejercicioId, $cuenta, $q, $ini, 12);
    }

    private function esClub(int $centroId): bool
    {
        $centro = $this->centros?->porId($centroId);

        return $centro !== null && CatalogoPlanesContables::esClub($centro->planContableCodigo);
    }
}
