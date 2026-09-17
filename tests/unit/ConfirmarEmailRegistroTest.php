<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\ConfirmarEmailRegistro;
use src\acceso\domain\contracts\IdentidadRepository;

final class ConfirmarEmailRegistroTest extends TestCase
{
    public function testConfirmaTokenValido(): void
    {
        $repo = $this->createMock(IdentidadRepository::class);
        $repo->method('porTokenVerificacionEmail')->willReturn([
            'identidad_id' => 7,
            'expira' => new DateTimeImmutable('+1 day'),
        ]);
        $repo->method('emailVerificado')->willReturn(false);
        $repo->expects(self::once())->method('confirmarEmail')->with(7, self::isInstanceOf(DateTimeImmutable::class));
        (new ConfirmarEmailRegistro($repo))->ejecutar('abc123');
    }

    public function testRechazaTokenCaducado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('porTokenVerificacionEmail')->willReturn([
            'identidad_id' => 7,
            'expira' => new DateTimeImmutable('-1 hour'),
        ]);
        (new ConfirmarEmailRegistro($repo))->ejecutar('abc123', new DateTimeImmutable());
    }
}
