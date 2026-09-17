<?php

declare(strict_types=1);

namespace src\shared\infrastructure\i18n;

use src\acceso\domain\value_objects\IdiomaUsuario;

final class ServicioCatalogoIdioma
{
    public const DOMINIO = 'secretary';

    public static function activarDesdeSesion(): string
    {
        $codigo = $_SESSION['idioma'] ?? IdiomaUsuario::ES;
        if (!is_string($codigo) || $codigo === '') {
            $codigo = IdiomaUsuario::ES;
        }
        try {
            new IdiomaUsuario($codigo);
        } catch (\InvalidArgumentException) {
            $codigo = IdiomaUsuario::ES;
        }

        return self::activar($codigo);
    }

    public static function activar(string $codigoIdioma): string
    {
        $locale = self::localeGettext($codigoIdioma);

        putenv('LANGUAGE');
        putenv('LC_ALL');
        putenv('LANG');
        putenv("LANGUAGE={$locale}");
        putenv("LC_ALL={$locale}");
        putenv("LANG={$locale}");
        $_ENV['LANGUAGE'] = $locale;
        $_ENV['LC_ALL'] = $locale;
        $_ENV['LANG'] = $locale;

        $localeOk = setlocale(LC_ALL, $locale);
        if ($localeOk === false) {
            $localeOk = setlocale(LC_ALL, str_replace('.UTF-8', '.utf8', $locale));
        }
        if ($localeOk === false) {
            setlocale(LC_ALL, 'C.UTF-8', 'C.utf8', 'en_US.utf8', 'en_US.UTF-8', 'C');
        }

        bindtextdomain(self::DOMINIO, self::directorioLanguages());
        bind_textdomain_codeset(self::DOMINIO, 'UTF-8');
        textdomain(self::DOMINIO . '_reset');
        textdomain(self::DOMINIO);

        return $locale;
    }

    public static function localeGettext(string $codigo): string
    {
        return match ($codigo) {
            IdiomaUsuario::CA => 'ca_ES.UTF-8',
            default => 'es_ES.UTF-8',
        };
    }

    public static function localeJs(string $codigo): string
    {
        return match ($codigo) {
            IdiomaUsuario::CA => 'ca-ES',
            default => 'es-ES',
        };
    }

    public static function directorioLanguages(): string
    {
        return dirname(__DIR__, 4) . '/languages';
    }
}
