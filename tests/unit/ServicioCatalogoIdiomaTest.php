<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use src\acceso\domain\value_objects\IdiomaUsuario;
use src\shared\infrastructure\i18n\ServicioCatalogoIdioma;

final class ServicioCatalogoIdiomaTest extends TestCase
{
    public function testLocaleGettext(): void
    {
        self::assertSame('es_ES.UTF-8', ServicioCatalogoIdioma::localeGettext(IdiomaUsuario::ES));
        self::assertSame('ca_ES.UTF-8', ServicioCatalogoIdioma::localeGettext(IdiomaUsuario::CA));
    }

    public function testLocaleJs(): void
    {
        self::assertSame('es-ES', ServicioCatalogoIdioma::localeJs(IdiomaUsuario::ES));
        self::assertSame('ca-ES', ServicioCatalogoIdioma::localeJs(IdiomaUsuario::CA));
    }

    public function testDirectorioLanguagesExiste(): void
    {
        self::assertDirectoryExists(ServicioCatalogoIdioma::directorioLanguages());
    }
}
