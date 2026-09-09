<?php

declare(strict_types=1);

namespace src\ambito\domain\contracts;

use src\ambito\domain\entity\Cuenta;

interface CuentaRepository
{
    /** @return list<Cuenta> */
    public function listarDeCentro(int $centroId): array;

    /**
     * Busca una cuenta por su clave de ámbito completa. `personaId = null` busca
     * entre las cuentas sin persona (plan maestro, tesorería, colectivas).
     */
    public function buscar(int $centroId, ?int $personaId, string $libro, string $codigo): ?Cuenta;

    /** Cuentas imputables (hoja) de un libro del centro. @return list<Cuenta> */
    public function imputablesDe(int $centroId, string $libro): array;

    /** Cuentas activas de una persona en un libro (nivel 1). @return list<Cuenta> */
    public function listarDePersona(int $centroId, int $personaId, string $libro): array;

    /** Tesorería (CAJA/BANCO) del libro personal de una persona. */
    public function tesoreriaDePersona(int $centroId, int $personaId, string $libro, string $codigoMaestro): ?Cuenta;

    /** Cuenta de tesorería (CAJA o BANCO) de un libro del centro. */
    public function tesoreria(int $centroId, string $libro, string $codigoMaestro): ?Cuenta;

    /** Cuenta de mayor de una física concreta en un libro. */
    public function tesoreriaDeFisica(int $centroId, string $libro, int $cuentaFisicaId): ?Cuenta;

    /** @return list<Cuenta> */
    public function listarTesoreria(int $centroId, ?string $libro = null): array;

    /** Cuenta puente PUENTE.LIBROS de un libro. */
    public function puenteEntreLibros(int $centroId, string $libro): ?Cuenta;

    /** Cuenta puente PUENTE.PERIODIFICACION de un libro (D13). */
    public function puentePeriodificacion(int $centroId, string $libro): ?Cuenta;

    public function desactivarPorCuentaFisicaId(int $cuentaFisicaId): void;

    /** Cuenta corriente personal (libro P) de una persona. */
    public function personalDe(int $centroId, int $personaId): ?Cuenta;

    /** Cuenta colectiva de deudores por vivienda (libro G). */
    public function deudoresVivienda(int $centroId): ?Cuenta;

    public function guardar(Cuenta $cuenta): Cuenta;
}
