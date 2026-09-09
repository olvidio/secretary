<?php

declare(strict_types=1);

namespace src\personas\application;

use InvalidArgumentException;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;
use src\shared\domain\value_objects\Dinero;

final class GuardarPersona
{
    public function __construct(private readonly PersonaRepository $repo)
    {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos): Persona
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $apellidos = trim((string) ($datos['apellidos'] ?? ''));
        $iniciales = strtolower(trim((string) ($datos['iniciales'] ?? '')));
        if ($nombre === '' || $iniciales === '') {
            throw new InvalidArgumentException('Nombre e iniciales son obligatorios');
        }
        if (str_contains($iniciales, ' ')) {
            throw new InvalidArgumentException('Las iniciales no deben tener espacios');
        }
        if (strlen($iniciales) > 6) {
            $iniciales = substr($iniciales, 0, 6);
        }
        $fijo = null;
        if (!empty($datos['importe_vivienda_fijo'])) {
            $fijo = Dinero::fromInput((string) $datos['importe_vivienda_fijo']);
        }
        $id = isset($datos['id']) && $datos['id'] !== '' ? (int) $datos['id'] : null;
        $persona = new Persona(
            $id,
            $nombre,
            $apellidos,
            $iniciales,
            self::mes($datos['mes_exento_inicio'] ?? null),
            self::mes($datos['mes_exento_fin'] ?? null),
            self::mes($datos['mes_exento2_inicio'] ?? null),
            self::mes($datos['mes_exento2_fin'] ?? null),
            $fijo,
            (int) ($datos['orden'] ?? 0),
        );
        return $this->repo->guardar($persona);
    }

    private static function mes(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;
        if ($n < 1 || $n > 12) {
            throw new InvalidArgumentException('El mes debe estar entre 1 y 12');
        }

        return $n;
    }
}
