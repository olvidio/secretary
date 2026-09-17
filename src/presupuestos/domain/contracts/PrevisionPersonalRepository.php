<?php

declare(strict_types=1);

namespace src\presupuestos\domain\contracts;

use src\presupuestos\domain\entity\LineaPrevisionPersonal;

interface PrevisionPersonalRepository
{
    /** @return list<LineaPrevisionPersonal> */
    public function listarDePersona(int $ejercicioId, int $personaId): array;

    /** @return list<LineaPrevisionPersonal> */
    public function listarDeEjercicio(int $ejercicioId): array;

    /**
     * Sustituye las líneas de esa persona en el ejercicio.
     *
     * @param list<LineaPrevisionPersonal> $lineas
     */
    public function reemplazarDePersona(int $ejercicioId, int $personaId, array $lineas): void;
}
