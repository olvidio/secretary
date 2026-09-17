<?php

declare(strict_types=1);

namespace src\personal\infrastructure\http;

use InvalidArgumentException;
use RuntimeException;
use src\shared\infrastructure\http\Request;

/** Recibe una copia personal `.json` subida. */
final class RecibirFicheroCopiaPersonal
{
    public const MAX_BYTES = 32 * 1024 * 1024;

    public static function opcional(Request $request, string $campo = 'dump'): ?string
    {
        $info = $request->file($campo);
        if ($info === null) {
            return null;
        }
        $error = (int) ($info['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return self::obligatorio($request, $campo);
    }

    public static function obligatorio(Request $request, string $campo = 'dump'): string
    {
        $info = $request->file($campo);
        if ($info === null) {
            throw new InvalidArgumentException(_("Falta el fichero de copia personal"));
        }
        $error = (int) ($info['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException(_("La copia supera el tamaño máximo (32 MB)"));
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(_("No se pudo recibir la copia"));
        }
        $nombre = (string) ($info['name'] ?? '');
        if (strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== 'json') {
            throw new InvalidArgumentException(_("El fichero debe ser una copia .json"));
        }
        $size = (int) ($info['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException(_("La copia está vacía o supera 32 MB"));
        }
        $tmp = (string) ($info['tmp_name'] ?? '');
        if ($tmp === '' || !is_readable($tmp)) {
            throw new InvalidArgumentException(_("No se pudo leer la copia subida"));
        }
        $destino = tempnam(sys_get_temp_dir(), 'secpers_');
        if ($destino === false) {
            throw new RuntimeException(_("No se pudo crear un temporal para la copia"));
        }
        $conExt = $destino . '.json';
        if (!rename($destino, $conExt)) {
            unlink($destino);
            throw new RuntimeException(_("No se pudo preparar la copia"));
        }
        if (is_uploaded_file($tmp)) {
            if (!move_uploaded_file($tmp, $conExt)) {
                unlink($conExt);
                throw new RuntimeException(_("No se pudo guardar la copia"));
            }
        } elseif (!copy($tmp, $conExt)) {
            unlink($conExt);
            throw new RuntimeException(_("No se pudo copiar la copia"));
        }

        return $conExt;
    }

    public static function limpiar(?string $path): void
    {
        if ($path === null || $path === '' || !is_file($path)) {
            return;
        }
        $base = realpath(sys_get_temp_dir());
        $real = realpath($path);
        if ($base === false || $real === false || !str_starts_with($real, $base)) {
            return;
        }
        if (!str_contains(basename($real), 'secpers_')) {
            return;
        }
        unlink($real);
    }
}
