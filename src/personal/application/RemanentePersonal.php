<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use src\personal\domain\contracts\RemanenteRepository;
use src\shared\domain\value_objects\Dinero;

/** Cantidad fija que la persona deja en su cuenta y no envía en la remesa. */
final class RemanentePersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly RemanenteRepository $remanentes,
    ) {
    }

    /** @return array{remanente_cents: int, remanente: string, remanente_es: string} */
    public function leer(): array
    {
        $ctx = $this->ambito->ejecutar();

        return $this->fila($this->remanentes->dePersona($ctx->personaId));
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{remanente_cents: int, remanente: string, remanente_es: string}
     */
    public function guardar(array $datos): array
    {
        $ctx = $this->ambito->ejecutar();
        $raw = $datos['remanente'] ?? '';
        if ($raw === null || trim((string) $raw) === '') {
            $cents = 0;
        } else {
            $cents = Dinero::fromInput((string) $raw)->toCents();
        }
        if ($cents < 0) {
            throw new InvalidArgumentException(_("El remanente no puede ser negativo"));
        }
        $this->remanentes->guardar($ctx->personaId, $cents);

        return $this->fila($cents);
    }

    /** @return array{remanente_cents: int, remanente: string, remanente_es: string} */
    private function fila(int $cents): array
    {
        $dinero = Dinero::fromCents($cents);

        return [
            'remanente_cents' => $cents,
            'remanente' => $dinero->toString(),
            'remanente_es' => $dinero->formatEs(),
        ];
    }
}
