<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\ConfirmarEmailRegistro;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\legal\application\RegistrarAceptacion;
use src\legal\domain\contracts\AceptacionLegalRepository;
use src\legal\domain\entity\AceptacionLegal;
use src\legal\domain\services\CatalogoDocumentosLegales;
use src\legal\domain\services\DatosOperador;

final class ConfirmarEmailRegistroTest extends TestCase
{
    public function testConfirmaTokenValidoYRegistraAceptacion(): void
    {
        $repo = $this->createMock(IdentidadRepository::class);
        $repo->method('porTokenVerificacionEmail')->willReturn([
            'identidad_id' => 7,
            'expira' => new DateTimeImmutable('+1 day'),
        ]);
        $repo->method('emailVerificado')->willReturn(false);
        $repo->expects(self::once())->method('confirmarEmail')->with(7, self::isInstanceOf(DateTimeImmutable::class));
        $repo->method('porId')->willReturn(new Identidad(7, 'a@x.local', 'h', 'Ana', true, 0, null, null, 'ana'));
        $aceptaciones = $this->createMock(AceptacionLegalRepository::class);
        $aceptaciones->expects(self::once())->method('registrar')->with(self::callback(
            static function (AceptacionLegal $a): bool {
                return $a->identidadId === 7
                    && $a->canal === 'confirmacion_email'
                    && $a->huella->email === 'a@x.local'
                    && $a->huella->tokenHash !== null;
            }
        ));
        $this->caso($repo, $aceptaciones)->ejecutar('abc123');
    }

    public function testRechazaTokenCaducado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('porTokenVerificacionEmail')->willReturn([
            'identidad_id' => 7,
            'expira' => new DateTimeImmutable('-1 hour'),
        ]);
        $aceptaciones = $this->createMock(AceptacionLegalRepository::class);
        $aceptaciones->expects(self::never())->method('registrar');
        $this->caso($repo, $aceptaciones)->ejecutar('abc123', new DateTimeImmutable());
    }

    private function caso(IdentidadRepository $repo, AceptacionLegalRepository $aceptaciones): ConfirmarEmailRegistro
    {
        $catalogo = CatalogoDocumentosLegales::porDefecto();

        return new ConfirmarEmailRegistro(
            $repo,
            new RegistrarAceptacion($aceptaciones, $catalogo, new DatosOperador('Op', 'op@x.local', 'Dir')),
            $catalogo,
        );
    }
}
