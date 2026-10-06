<?php

declare(strict_types=1);

namespace src\presupuestos\domain\contracts;

use src\presupuestos\domain\entity\LineaPresupuesto;

interface PresupuestoRepository
{
    /** @return list<LineaPresupuesto> */
    public function listar(string $cuenta, int $ejercicioId): array;

    public function guardar(int $ejercicioId, LineaPresupuesto $linea): void;

    public function previsto(string $cuenta, string $concepto, int $ejercicioId): string;

    /** Borra todas las líneas de la cuenta en un ejercicio. */
    public function borrarCuenta(string $cuenta, int $ejercicioId): void;

    /** Importación / reemplazo total: todas las filas de la cuenta. */
    public function vaciarCuenta(string $cuenta): void;
}
