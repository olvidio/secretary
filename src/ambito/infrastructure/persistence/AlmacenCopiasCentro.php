<?php

declare(strict_types=1);

namespace src\ambito\infrastructure\persistence;

use InvalidArgumentException;
use RuntimeException;
use src\shared\infrastructure\persistence\RutasCopiasSeguridad;

/** Ficheros JSON de un solo centro. Cada centro tiene su carpeta. */
final class AlmacenCopiasCentro
{
    public function __construct(private readonly int $centroId)
    {
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array{filename: string, bytes: int, fecha: string}
     */
    public function crear(array $snapshot, string $sigla): array
    {
        $this->asegurarDirectorio();
        $slug = preg_replace('/[^a-z0-9]/', '', strtolower($sigla)) ?: 'c' . $this->centroId;
        $nombre = sprintf('centro_%d_%s_%s.json', $this->centroId, $slug, date('Ymd_His'));
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException(_('No se pudo serializar la copia del centro'));
        }
        $ruta = $this->directorio() . '/' . $nombre;
        if (file_put_contents($ruta, $json) === false) {
            throw new RuntimeException(_('No se pudo guardar la copia del centro'));
        }

        return $this->filaDe($nombre);
    }

    /** @return list<array{filename: string, bytes: int, fecha: string}> */
    public function listar(): array
    {
        $dir = $this->directorio();
        if (!is_dir($dir)) {
            return [];
        }
        $prefijo = 'centro_' . $this->centroId . '_';
        $filas = [];
        foreach (scandir($dir, SCANDIR_SORT_DESCENDING) ?: [] as $entry) {
            if (!str_starts_with($entry, $prefijo) || !str_ends_with(strtolower($entry), '.json')) {
                continue;
            }
            if (is_file($dir . '/' . $entry)) {
                $filas[] = $this->filaDe($entry);
            }
        }

        return $filas;
    }

    /** @return array<string, mixed> */
    public function leer(string $nombre): array
    {
        $raw = file_get_contents($this->rutaDeNombre($nombre));
        if ($raw === false) {
            throw new RuntimeException(_('No se pudo leer la copia'));
        }
        $datos = json_decode($raw, true);
        if (!is_array($datos)) {
            throw new InvalidArgumentException(_('La copia no es un fichero de centro válido'));
        }

        return $datos;
    }

    public function rutaDeNombre(string $nombre): string
    {
        $base = basename($nombre);
        if (!str_starts_with($base, 'centro_' . $this->centroId . '_') || !str_ends_with(strtolower($base), '.json')) {
            throw new InvalidArgumentException(_('Esa copia no es de este centro'));
        }
        $ruta = $this->directorio() . '/' . $base;
        if (!is_file($ruta)) {
            throw new InvalidArgumentException(_('No existe el fichero de copia indicado'));
        }

        return $ruta;
    }

    public function borrarPorNombre(string $nombre): void
    {
        $ruta = $this->rutaDeNombre($nombre);
        if (!unlink($ruta)) {
            throw new RuntimeException(_('No se pudo borrar la copia'));
        }
    }

    public function directorio(): string
    {
        return RutasCopiasSeguridad::directorio() . '/centros/' . $this->centroId;
    }

    private function asegurarDirectorio(): void
    {
        $dir = $this->directorio();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(_('No se pudo crear el directorio de copias del centro'));
        }
    }

    /** @return array{filename: string, bytes: int, fecha: string} */
    private function filaDe(string $nombre): array
    {
        $ruta = $this->directorio() . '/' . $nombre;
        $mtime = filemtime($ruta);

        return [
            'filename' => $nombre,
            'bytes' => (int) filesize($ruta),
            'fecha' => date('Y-m-d H:i', $mtime !== false ? $mtime : time()),
        ];
    }
}
