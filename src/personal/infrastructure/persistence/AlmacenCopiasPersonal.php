<?php

declare(strict_types=1);

namespace src\personal\infrastructure\persistence;

use InvalidArgumentException;
use RuntimeException;

/** Ficheros JSON de copia del libro personal, aislados por persona. */
final class AlmacenCopiasPersonal
{
    public function __construct(
        private readonly string $directorio,
        private readonly int $personaId,
        private readonly string $iniciales,
    ) {
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array{filename: string, bytes: int, fecha: string}
     */
    public function crear(array $snapshot): array
    {
        $this->asegurarDirectorio();
        $slug = preg_replace('/[^a-z0-9]/', '', strtolower($this->iniciales)) ?: 'p' . $this->personaId;
        $nombre = sprintf(
            'personal_%d_%s_%s.json',
            $this->personaId,
            $slug,
            date('Ymd_His'),
        );
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar la copia personal');
        }
        $ruta = $this->directorio . '/' . $nombre;
        if (file_put_contents($ruta, $json) === false) {
            throw new RuntimeException('No se pudo guardar la copia personal');
        }

        return $this->filaDe($nombre);
    }

    /**
     * @return list<array{filename: string, bytes: int, fecha: string}>
     */
    public function listar(): array
    {
        if (!is_dir($this->directorio)) {
            return [];
        }
        $prefijo = $this->prefijo();
        $filas = [];
        foreach (scandir($this->directorio, SCANDIR_SORT_DESCENDING) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (!str_starts_with($entry, $prefijo) || !str_ends_with(strtolower($entry), '.json')) {
                continue;
            }
            $ruta = $this->directorio . '/' . $entry;
            if (!is_file($ruta)) {
                continue;
            }
            $filas[] = $this->filaDe($entry);
        }

        return $filas;
    }

    /** @return array<string, mixed> */
    public function leer(string $nombre): array
    {
        $ruta = $this->rutaDeNombre($nombre);
        $raw = file_get_contents($ruta);
        if ($raw === false) {
            throw new RuntimeException('No se pudo leer la copia');
        }
        $datos = json_decode($raw, true);
        if (!is_array($datos)) {
            throw new InvalidArgumentException('La copia personal no es JSON válido');
        }

        return $datos;
    }

    public function rutaDeNombre(string $nombre): string
    {
        $realDir = realpath($this->directorio);
        if ($realDir === false) {
            throw new RuntimeException('No existe el directorio de copias personales');
        }
        $base = basename($nombre);
        if (!str_starts_with($base, $this->prefijo()) || !str_ends_with(strtolower($base), '.json')) {
            throw new InvalidArgumentException('Nombre de copia no permitido');
        }
        $real = realpath($this->directorio . '/' . $base);
        if ($real === false || !is_file($real) || !str_starts_with($real, $realDir . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('No existe el fichero de copia indicado');
        }

        return $real;
    }

    public function borrarPorNombre(string $nombre): void
    {
        $ruta = $this->rutaDeNombre($nombre);
        if (!unlink($ruta)) {
            throw new RuntimeException('No se pudo borrar la copia personal');
        }
    }

    private function prefijo(): string
    {
        return 'personal_' . $this->personaId . '_';
    }

    private function asegurarDirectorio(): void
    {
        if (is_dir($this->directorio)) {
            if (!is_writable($this->directorio)) {
                throw new RuntimeException(
                    'El directorio de copias personales no es escribible: ' . $this->directorio
                );
            }

            return;
        }
        if (!@mkdir($this->directorio, 0777, true) && !is_dir($this->directorio)) {
            throw new RuntimeException('No se pudo crear el directorio de copias personales');
        }
        @chmod($this->directorio, 0777);
    }

    /** @return array{filename: string, bytes: int, fecha: string} */
    private function filaDe(string $nombre): array
    {
        $ruta = $this->directorio . '/' . $nombre;
        $mtime = is_file($ruta) ? filemtime($ruta) : false;

        return [
            'filename' => $nombre,
            'bytes' => is_file($ruta) ? (int) filesize($ruta) : 0,
            'fecha' => $mtime !== false ? date('Y-m-d H:i:s', $mtime) : '',
        ];
    }
}
