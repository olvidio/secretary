<?php

declare(strict_types=1);

namespace src\plan\application;

use InvalidArgumentException;
use RuntimeException;
use src\ambito\application\ResolverAmbitoActual;
use src\plan\domain\contracts\DestinoSgRepository;
use src\plan\domain\services\DestinosCentroSg;

final class GuardarDestinosSg
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly DestinoSgRepository $destinos,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{partidas: list<array{codigo:string,etiqueta:string,orden:int}>, siguiente: ?string}
     */
    public function ejecutar(array $datos): array
    {
        $centroId = $this->ambito->ejecutar()->centroId;
        if ($this->destinos->nombrados($centroId) === null) {
            throw new RuntimeException(_('Este centro no usa destinos propios'));
        }
        $raw = $datos['partidas'] ?? [];
        if (!is_array($raw)) {
            throw new InvalidArgumentException(_('Formato de destinos no válido'));
        }
        $this->destinos->guardar($centroId, $this->normalizar($raw));
        $partidas = $this->destinos->paraCentro($centroId);

        return [
            'partidas' => $partidas,
            'siguiente' => DestinosCentroSg::siguiente(array_column($partidas, 'codigo')),
        ];
    }

    /**
     * @param list<mixed> $raw
     * @return list<array{codigo:string,etiqueta:string,orden:int}>
     */
    private function normalizar(array $raw): array
    {
        if (count($raw) > 13) {
            throw new InvalidArgumentException(_('Como máximo 13 destinos (del 42 al 54)'));
        }
        $out = [];
        $codigos = [];
        foreach ($raw as $fila) {
            if (!is_array($fila)) {
                throw new InvalidArgumentException(_('Cada destino debe ser un objeto'));
            }
            $codigo = trim((string) ($fila['codigo'] ?? ''));
            $etiqueta = trim((string) ($fila['etiqueta'] ?? ''));
            if (!DestinosCentroSg::esVariable($codigo)) {
                throw new InvalidArgumentException(sprintf(_('El destino %s tiene que estar entre 42 y 54'), $codigo));
            }
            if ($etiqueta === '' || $etiqueta === $codigo) {
                throw new InvalidArgumentException(sprintf(_('El destino %s necesita un nombre'), $codigo));
            }
            if (isset($codigos[$codigo])) {
                throw new InvalidArgumentException(sprintf(_('Código duplicado: %s'), $codigo));
            }
            $codigos[$codigo] = true;
            $out[] = ['codigo' => $codigo, 'etiqueta' => $etiqueta, 'orden' => (int) $codigo];
        }
        usort($out, static fn (array $a, array $b): int => $a['orden'] <=> $b['orden']);

        return $out;
    }
}
