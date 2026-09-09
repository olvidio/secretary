<?php

declare(strict_types=1);

namespace src\remesas\domain\entity;

use DateTimeImmutable;
use InvalidArgumentException;
use src\shared\domain\value_objects\Dinero;

final class Remesa
{
    public const ESTADOS = ['borrador', 'enviada', 'aceptada', 'rechazada', 'sustituida'];

    /**
     * @param list<RemesaLinea> $lineas
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $personaId,
        public readonly int $centroId,
        public readonly int $ejercicioId,
        public readonly int $anio,
        public readonly int $mes,
        public readonly int $version,
        public readonly string $estado,
        public readonly string $hashContenido,
        public readonly ?DateTimeImmutable $enviadaAt,
        public readonly ?DateTimeImmutable $resueltaAt,
        public readonly ?string $nota,
        public readonly array $lineas = [],
    ) {
        if ($this->mes < 1 || $this->mes > 12) {
            throw new InvalidArgumentException('El mes debe estar entre 1 y 12');
        }
        if ($this->version < 1) {
            throw new InvalidArgumentException('La versión debe ser al menos 1');
        }
        if (!in_array($this->estado, self::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado de remesa no válido: ' . $this->estado);
        }
    }

    public function withId(int $id): self
    {
        $lineas = [];
        foreach ($this->lineas as $linea) {
            $lineas[] = new RemesaLinea(
                $linea->id,
                $id,
                $linea->codigoMaestro,
                $linea->importeCents,
                $linea->detalle,
            );
        }

        return new self(
            $id,
            $this->personaId,
            $this->centroId,
            $this->ejercicioId,
            $this->anio,
            $this->mes,
            $this->version,
            $this->estado,
            $this->hashContenido,
            $this->enviadaAt,
            $this->resueltaAt,
            $this->nota,
            $lineas,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $total = 0;
        $lineas = [];
        foreach ($this->lineas as $linea) {
            $total += $linea->importeCents;
            $lineas[] = $linea->toArray();
        }

        return [
            'id' => $this->id,
            'persona_id' => $this->personaId,
            'centro_id' => $this->centroId,
            'ejercicio_id' => $this->ejercicioId,
            'anio' => $this->anio,
            'mes' => $this->mes,
            'version' => $this->version,
            'estado' => $this->estado,
            'hash_contenido' => $this->hashContenido,
            'enviada_at' => $this->enviadaAt?->format('c'),
            'resuelta_at' => $this->resueltaAt?->format('c'),
            'nota' => $this->nota,
            'total_cents' => $total,
            'total' => Dinero::fromCents($total)->toString(),
            'total_es' => Dinero::fromCents($total)->formatEs(),
            'lineas' => $lineas,
        ];
    }
}
