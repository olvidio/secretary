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
     * @return list<array{codigo:string,etiqueta:string,orden:int,desgrava?:bool}>
     */
    public function ejecutar(array $datos): array
    {
        $ctx = $this->ambito->ejecutar();
        $raw = $datos['partidas'] ?? [];
        if (!is_array($raw)) {
            throw new InvalidArgumentException(_("Formato de partidas no válido"));
        }

        $partidas = $this->normalizar($raw);
        $this->partidas->guardar($ctx->centroId, $partidas);

        return $this->partidas->paraCentro($ctx->centroId);
    }

    /**
     * @param list<mixed> $raw
     * @return list<array{codigo:string,etiqueta:string,orden:int,desgrava:bool}>
     */
    private function normalizar(array $raw): array
    {
        if ($raw === []) {
            throw new InvalidArgumentException(_("Debe haber al menos una partida"));
        }
        if (count($raw) > 12) {
            throw new InvalidArgumentException(_("Como máximo 12 partidas en el cap. VII"));
        }

        $out = [];
        $codigos = [];
        $orden = 10;
        foreach ($raw as $fila) {
            if (!is_array($fila)) {
                throw new InvalidArgumentException(_("Cada partida debe ser un objeto"));
            }
            $codigo = trim((string) ($fila['codigo'] ?? ''));
            $etiqueta = trim((string) ($fila['etiqueta'] ?? ''));
            if ($codigo === '' || !preg_match('/^\d{2,3}$/', $codigo)) {
                throw new InvalidArgumentException(sprintf(_("Código de partida no válido: %s"), $codigo));
            }
            if (!str_starts_with($codigo, '7')) {
                throw new InvalidArgumentException(_("Las partidas del cap. VII empiezan por 7 (p. ej. 71, 791)"));
            }
            if ($etiqueta === '') {
                throw new InvalidArgumentException(sprintf(_("La etiqueta es obligatoria en la partida %s"), $codigo));
            }
            if (isset($codigos[$codigo])) {
                throw new InvalidArgumentException(sprintf(_("Código duplicado: %s"), $codigo));
            }
            $codigos[$codigo] = true;
            $desgrava = !empty($fila['desgrava']);
            $out[] = ['codigo' => $codigo, 'etiqueta' => $etiqueta, 'orden' => $orden, 'desgrava' => $desgrava];
            $orden += 10;
        }

        return $out;
    }
}
