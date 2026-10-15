<?php

declare(strict_types=1);

namespace src\importacion\infrastructure\http;

use InvalidArgumentException;
use RuntimeException;
use src\shared\infrastructure\http\Request;

final class RecibirFicheroExtractoBanco
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    /** @var list<string> */
    private const EXTENSIONES = ['csv', 'xls', 'xlsx'];

    /**
     * @return array{csv:?string, temporal:?string}
     */
    public static function recibir(Request $request, string $campo = 'fichero'): array
    {
        $info = $request->file($campo);
        if ($info === null) {
            throw new InvalidArgumentException(_("Falta el fichero del extracto"));
        }
        $error = (int) ($info['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException(_("El fichero supera el tamaño máximo (2 MB)"));
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(_("No se pudo recibir el fichero"));
        }
        $nombre = (string) ($info['name'] ?? '');
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONES, true)) {
            throw new InvalidArgumentException(sprintf(
                _('Use un extracto en %s'),
                implode(', ', self::EXTENSIONES),
            ));
        }
        $size = (int) ($info['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException(_("El fichero está vacío o supera 2 MB"));
        }
        $tmp = (string) ($info['tmp_name'] ?? '');
        if ($tmp === '' || !is_readable($tmp)) {
            throw new InvalidArgumentException(_("No se pudo leer el fichero subido"));
        }
        if ($ext === 'csv') {
            $raw = file_get_contents($tmp);
            if ($raw === false) {
                throw new RuntimeException(_("No se pudo leer el CSV"));
            }

            return ['csv' => $raw, 'temporal' => null];
        }

        return ['csv' => null, 'temporal' => self::temporal($tmp, $ext)];
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
        if (!str_contains(basename($real), 'secbanco_')) {
            return;
        }
        unlink($real);
    }

    private static function temporal(string $origen, string $ext): string
    {
        $destino = tempnam(sys_get_temp_dir(), 'secbanco_');
        if ($destino === false) {
            throw new RuntimeException(_("No se pudo crear un temporal para el extracto"));
        }
        $conExt = $destino . '.' . $ext;
        if (!rename($destino, $conExt)) {
            unlink($destino);
            throw new RuntimeException(_("No se pudo preparar el extracto"));
        }
        if (is_uploaded_file($origen)) {
            if (!move_uploaded_file($origen, $conExt)) {
                unlink($conExt);
                throw new RuntimeException(_("No se pudo guardar el extracto"));
            }
        } elseif (!copy($origen, $conExt)) {
            unlink($conExt);
            throw new RuntimeException(_("No se pudo copiar el extracto"));
        }

        return $conExt;
    }
}
