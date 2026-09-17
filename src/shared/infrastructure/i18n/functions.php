<?php

declare(strict_types=1);

/**
 * Fallback cuando la extensión gettext no está cargada (p. ej. algunos entornos de test).
 * Con gettext activo, PHP ya define _() como alias de gettext().
 */
if (!function_exists('_')) {
    function _(?string $msgid): string
    {
        if ($msgid === null || $msgid === '') {
            return '';
        }

        return function_exists('gettext') ? gettext($msgid) : $msgid;
    }
}
