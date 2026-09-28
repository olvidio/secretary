<?php

declare(strict_types=1);

namespace src\grisbi\domain;

final readonly class CuentaGrisbi
{
    public function __construct(
        public int $numero,
        public string $nombre,
        public string $tipo,
    ) {
    }
}

final readonly class CategoriaGrisbi
{
    public function __construct(
        public int $numero,
        public string $nombre,
        public string $naturaleza,
    ) {
    }
}

final readonly class SubcategoriaGrisbi
{
    public function __construct(
        public int $categoria,
        public int $numero,
        public string $nombre,
    ) {
    }
}

final readonly class TerceroGrisbi
{
    public function __construct(
        public int $numero,
        public string $nombre,
    ) {
    }
}

final readonly class MovimientoGrisbi
{
    public function __construct(
        public int $cuenta,
        public int $numero,
        public string $fecha,
        public int $importeCents,
        public int $categoria,
        public int $subcategoria,
        public int $tercero,
        public string $nota,
        public int $traspasoNb,
    ) {
    }
}

/** @phpstan-type ParCategoriaListado array{categoria: int, subcategoria: ?int} */
final readonly class ListadoGrisbi
{
    /**
     * @param list<ParCategoriaListado> $categorias
     * @param list<int> $terceros
     */
    public function __construct(
        public string $nombre,
        public array $categorias,
        public array $terceros,
        public bool $mostrarMovimientos,
        public bool $mostrarTotales,
        public ?string $desde,
        public ?string $hasta,
    ) {
    }
}

final class LibroGrisbi
{
    /**
     * @param list<CuentaGrisbi> $cuentas
     * @param list<CategoriaGrisbi> $categorias
     * @param list<SubcategoriaGrisbi> $subcategorias
     * @param list<TerceroGrisbi> $terceros
     * @param list<MovimientoGrisbi> $movimientos
     * @param list<ListadoGrisbi> $listados
     */
    public function __construct(
        public readonly array $cuentas,
        public readonly array $categorias,
        public readonly array $subcategorias,
        public readonly array $terceros,
        public readonly array $movimientos,
        public readonly array $listados,
    ) {
    }
}
