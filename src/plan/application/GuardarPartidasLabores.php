<?php

declare(strict_types=1);

namespace src\plan\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\plan\domain\contracts\PartidaLaboresRepository;

final class GuardarPartidasLabores
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly PartidaLaboresRepository $partidas,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<array{codigo:string,etiqueta:string,orden:int}>
     */
    public function ejecutar(array $datos): array
    {
        $ctx = $this->ambito->ejecutar();
        $raw = $datos['partidas'] ?? [];
        if (!is_array($raw)) {
            throw new InvalidArgumentException('Formato de partidas no válido');
        }

        $partidas = $this->normalizar($raw);
        $this->partidas->guardar($ctx->centroId, $partidas);

        return $this->partidas->paraCentro($ctx->centroId);
    }

    /**
     * @param list<mixed> $raw
     * @return list<array{codigo:string,etiqueta:string,orden:int}>
     */
    private function normalizar(array $raw): array
    {
        if ($raw === []) {
            throw new InvalidArgumentException('Debe haber al menos una partida');
        }
        if (count($raw) > 12) {
            throw new InvalidArgumentException('Como máximo 12 partidas en el cap. VII');
        }

        $out = [];
        $codigos = [];
        $orden = 10;
        foreach ($raw as $fila) {
            if (!is_array($fila)) {
                throw new InvalidArgumentException('Cada partida debe ser un objeto');
            }
            $codigo = trim((string) ($fila['codigo'] ?? ''));
            $etiqueta = trim((string) ($fila['etiqueta'] ?? ''));
            if ($codigo === '' || !preg_match('/^\d{2,3}$/', $codigo)) {
                throw new InvalidArgumentException('Código de partida no válido: ' . $codigo);
            }
            if (!str_starts_with($codigo, '7')) {
                throw new InvalidArgumentException('Las partidas del cap. VII empiezan por 7 (p. ej. 71, 791)');
            }
            if ($etiqueta === '') {
                throw new InvalidArgumentException('La etiqueta es obligatoria en la partida ' . $codigo);
            }
            if (isset($codigos[$codigo])) {
                throw new InvalidArgumentException('Código duplicado: ' . $codigo);
            }
            $codigos[$codigo] = true;
            $out[] = ['codigo' => $codigo, 'etiqueta' => $etiqueta, 'orden' => $orden];
            $orden += 10;
        }

        return $out;
    }
}
