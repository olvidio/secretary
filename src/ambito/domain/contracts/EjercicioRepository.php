<?php

declare(strict_types=1);

namespace src\ambito\domain\contracts;

use DateTimeImmutable;
use src\ambito\domain\entity\Ejercicio;

interface EjercicioRepository
{
    /** @return list<Ejercicio> */
    public function listarDeCentro(int $centroId): array;

    public function porId(int $id): ?Ejercicio;

    /** El ejercicio abierto más reciente del centro, o null si no hay ninguno. */
    public function abiertoDe(int $centroId): ?Ejercicio;

    /** Ejercicio del centro cuyo período [fecha_inicio, fecha_fin] contiene la fecha. */
    public function deCentroEnFecha(int $centroId, DateTimeImmutable $fecha): ?Ejercicio;

    /** Ejercicio contiguo cuyo fin + 1 día coincide con `fechaInicio`. */
    public function contiguoAnterior(int $centroId, DateTimeImmutable $fechaInicio): ?Ejercicio;

    /** Ejercicio que declara `ejercicio_anterior_id` apuntando a este id. */
    public function posteriorConAnteriorId(int $ejercicioId): ?Ejercicio;

    public function guardar(Ejercicio $ejercicio): Ejercicio;
}
