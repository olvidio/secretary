<?php

declare(strict_types=1);

namespace src\ambito\domain\entity;

/**
 * Plan de cuentas jerárquico (D2, docs/dev/plan_ampliaciones.md). `codigoMaestro`
 * es el código del plan principal al que consolida esta cuenta; solo las hojas
 * (`imputable = true`) admiten movimientos.
 */
final class Cuenta
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $centroId,
        public readonly ?int $personaId,
        public readonly ?int $cuentaFisicaId,
        public readonly ?int $padreId,
        public readonly string $libro,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $tipo,
        public readonly string $naturaleza,
        public readonly string $codigoMaestro,
        public readonly bool $imputable,
        public readonly int $orden = 0,
        public readonly bool $activo = true,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'centro_id' => $this->centroId,
            'persona_id' => $this->personaId,
            'cuenta_fisica_id' => $this->cuentaFisicaId,
            'padre_id' => $this->padreId,
            'libro' => $this->libro,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'tipo' => $this->tipo,
            'naturaleza' => $this->naturaleza,
            'codigo_maestro' => $this->codigoMaestro,
            'imputable' => $this->imputable,
            'orden' => $this->orden,
            'activo' => $this->activo,
        ];
    }
}
