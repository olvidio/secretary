<?php

declare(strict_types=1);

namespace src\personas\domain\entity;

use src\shared\domain\value_objects\Dinero;

final class Persona
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $nombre,
        public readonly string $apellidos,
        public readonly string $iniciales,
        public readonly ?int $mesExentoInicio,
        public readonly ?int $mesExentoFin,
        public readonly ?int $mesExento2Inicio,
        public readonly ?int $mesExento2Fin,
        public readonly ?Dinero $importeViviendaFijo,
        public readonly int $orden = 0,
        // Ámbito (Fase 2, D3): a qué centro pertenece esta persona. Deliberadamente NO
        // se expone en toArray() para no alterar el golden master (tests/golden/personas.json
        // compara la salida de ListarPersonas byte a byte). Se backfillea vía
        // AmbitoSeeder::sembrarAmbitoPersonas() y no se toca en las actualizaciones desde
        // el formulario (ver PdoPersonaRepository::guardar()), para no perderlo al editar.
        public readonly ?int $centroId = null,
        // Fase 2b (reparación del importador, docs/dev/plan_ampliaciones.md): una
        // persona que desaparece del Excel de origen no se borra —romperia la FK de
        // `cuentas.persona_id` y perdería su histórico de apuntes/saldos— sino que se
        // marca inactiva. Deliberadamente NO se expone en toArray() por la misma razón
        // que centroId: no debe alterar tests/golden/personas.json. El criterio de baja
        // vive en ImportarExcelSecretario::importarPersonas().
        public readonly bool $activo = true,
    ) {
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . $this->apellidos);
    }

    public function exentaEnMes(int $mes): bool
    {
        return self::enIntervalo($mes, $this->mesExentoInicio, $this->mesExentoFin)
            || self::enIntervalo($mes, $this->mesExento2Inicio, $this->mesExento2Fin);
    }

    private static function enIntervalo(int $mes, ?int $ini, ?int $fin): bool
    {
        if ($ini === null || $fin === null) {
            return false;
        }
        if ($ini <= $fin) {
            return $mes >= $ini && $mes <= $fin;
        }

        return $mes >= $ini || $mes <= $fin;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'apellidos' => $this->apellidos,
            'iniciales' => $this->iniciales,
            'nombre_completo' => $this->nombreCompleto(),
            'mes_exento_inicio' => $this->mesExentoInicio,
            'mes_exento_fin' => $this->mesExentoFin,
            'mes_exento2_inicio' => $this->mesExento2Inicio,
            'mes_exento2_fin' => $this->mesExento2Fin,
            'importe_vivienda_fijo' => $this->importeViviendaFijo?->toString(),
            'orden' => $this->orden,
        ];
    }
}
