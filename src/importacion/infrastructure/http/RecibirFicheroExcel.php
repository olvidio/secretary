<?php

declare(strict_types=1);

namespace src\importacion\infrastructure\http;

use InvalidArgumentException;
use RuntimeException;
use src\shared\infrastructure\http\Request;

/** Copia un `.xlsm`/`.xlsx` subido a un temporal local. */
final class RecibirFicheroExcel
{
    public const MAX_BYTES = 32 * 1024 * 1024;

    public static function opcional(Request $request, string $campo = 'excel'): ?string
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

    public static function obligatorio(Request $request, string $campo = 'excel'): string
    {
        $info = $request->file($campo);
        if ($info === null) {
            throw new InvalidArgumentException('Falta el fichero Excel');
        }
        $error = (int) ($info['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('El Excel supera el tamaño máximo (32 MB)');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('No se pudo recibir el Excel');
        }
        $nombre = (string) ($info['name'] ?? '');
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsm', 'xlsx'], true)) {
            throw new InvalidArgumentException('El fichero debe ser .xlsm o .xlsx');
        }
        $size = (int) ($info['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('El Excel está vacío o supera 32 MB');
        }
        $tmp = (string) ($info['tmp_name'] ?? '');
        if ($tmp === '' || !is_readable($tmp)) {
            throw new InvalidArgumentException('No se pudo leer el Excel subido');
        }
        $destino = tempnam(sys_get_temp_dir(), 'secxl_');
        if ($destino === false) {
            throw new RuntimeException('No se pudo crear un temporal para el Excel');
        }
        $conExt = $destino . '.' . $ext;
        if (!rename($destino, $conExt)) {
            unlink($destino);
            throw new RuntimeException('No se pudo preparar el Excel');
        }
        if (is_uploaded_file($tmp)) {
            if (!move_uploaded_file($tmp, $conExt)) {
                unlink($conExt);
                throw new RuntimeException('No se pudo guardar el Excel');
            }
        } elseif (!copy($tmp, $conExt)) {
            unlink($conExt);
            throw new RuntimeException('No se pudo copiar el Excel');
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
        if (!str_contains(basename($real), 'secxl_')) {
            return;
        }
        unlink($real);
    }
}
