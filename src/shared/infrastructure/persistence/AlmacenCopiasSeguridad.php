<?php

declare(strict_types=1);

namespace src\shared\infrastructure\persistence;

use InvalidArgumentException;
use RuntimeException;

/** Volcados en disco y operaciones pg_dump/pg_restore. */
final class AlmacenCopiasSeguridad
{
    public function __construct(
        private readonly string $directorio,
        private readonly PostgresDumper $dumper,
    ) {
    }

    public function database(): string
    {
        return $this->dumper->database();
    }

    /** @return array{filename: string, bytes: int, fecha: string} */
    public function crear(): array
    {
        $this->asegurarDirectorio();
        $nombre = 'secretario_' . date('Ymd_His') . '.sql';
        $ruta = $this->directorio . '/' . $nombre;
        $this->dumper->backup($ruta);

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
        $filas = [];
        foreach (scandir($this->directorio, SCANDIR_SORT_DESCENDING) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (!$this->esFicheroCopia($entry)) {
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

    public function restaurar(string $ruta): void
    {
        $this->dumper->restore($this->validarRuta($ruta));
    }

    public function restaurarTemporal(string $ruta): void
    {
        $this->dumper->restore($this->validarTemporal($ruta));
    }

    public function restaurarPorNombre(string $nombre): void
    {
        $this->restaurar($this->rutaDeNombre($nombre));
    }

    public function rutaDeNombre(string $nombre): string
    {
        return $this->validarRuta($this->directorio . '/' . basename($nombre));
    }

    public function borrarPorNombre(string $nombre): void
    {
        $ruta = $this->rutaDeNombre($nombre);
        if (!unlink($ruta)) {
            throw new RuntimeException('No se pudo borrar la copia: ' . basename($nombre));
        }
    }

    private function validarRuta(string $ruta): string
    {
        $realDir = realpath($this->directorio);
        if ($realDir === false) {
            throw new RuntimeException('No existe el directorio de copias de seguridad');
        }
        $real = realpath($ruta);
        if ($real === false || !is_file($real)) {
            throw new InvalidArgumentException('No existe el fichero de copia indicado');
        }
        if (!str_starts_with($real, $realDir . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Ruta de copia no permitida');
        }

        return $this->validarExtensionCopia($real);
    }

    private function validarTemporal(string $ruta): string
    {
        $real = realpath($ruta);
        if ($real === false || !is_file($real)) {
            throw new InvalidArgumentException('No se pudo leer la copia subida');
        }
        $base = realpath(sys_get_temp_dir());
        if ($base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Ruta de copia no permitida');
        }
        if (!str_contains(basename($real), 'secdmp_')) {
            throw new InvalidArgumentException('Ruta de copia no permitida');
        }

        return $this->validarExtensionCopia($real);
    }

    private function esFicheroCopia(string $nombre): bool
    {
        $lower = strtolower($nombre);

        return str_ends_with($lower, '.sql') || str_ends_with($lower, '.dump');
    }

    private function validarExtensionCopia(string $real): string
    {
        if (!$this->esFicheroCopia(basename($real))) {
            throw new InvalidArgumentException('El fichero debe ser una copia .sql o .dump');
        }

        return $real;
    }

    private function asegurarDirectorio(): void
    {
        if (is_dir($this->directorio)) {
            if (!is_writable($this->directorio)) {
                throw new RuntimeException(
                    'El directorio de copias no es escribible: ' . $this->directorio
                    . '. En Docker, asegure permisos en var/backups (p. ej. chmod 775 var/backups).'
                );
            }

            return;
        }
        if (!@mkdir($this->directorio, 0775, true) && !is_dir($this->directorio)) {
            throw new RuntimeException(
                'No se pudo crear el directorio de copias: ' . $this->directorio
                . '. Créelo con permisos de escritura para PHP-FPM (var/backups).'
            );
        }
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
