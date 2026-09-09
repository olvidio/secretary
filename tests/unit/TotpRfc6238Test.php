<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\acceso\domain\services\TotpRfc6238;

/** Vectores RFC 4226 (HOTP) y RFC 6238 (TOTP SHA-1, 6 dígitos). */
final class TotpRfc6238Test extends TestCase
{
    private const SECRETO_ASCII = '12345678901234567890';

    public function testHotpRfc4226(): void
    {
        $secret = TotpRfc6238::base32Encode(self::SECRETO_ASCII);
        $esperados = [
            0 => '755224',
            1 => '287082',
            2 => '359152',
            3 => '969429',
            4 => '338314',
            5 => '254676',
            6 => '287922',
            7 => '162583',
            8 => '399871',
            9 => '520489',
        ];
        foreach ($esperados as $contador => $otp) {
            self::assertSame($otp, TotpRfc6238::hotp($secret, $contador), "contador $contador");
        }
    }

    public function testTotpEnElInstante59EsElHotpDelContador1(): void
    {
        $secret = TotpRfc6238::base32Encode(self::SECRETO_ASCII);
        self::assertSame('287082', TotpRfc6238::codigo($secret, 59));
        self::assertTrue(TotpRfc6238::verificar($secret, '287082', 0, 59));
        self::assertFalse(TotpRfc6238::verificar($secret, '000000', 0, 59));
    }

    public function testOtpauthUri(): void
    {
        $uri = TotpRfc6238::otpauthUri('scl@secretario.local', 'MFRGGZDF');
        self::assertStringStartsWith('otpauth://totp/', $uri);
        self::assertStringContainsString('secret=MFRGGZDF', $uri);
        self::assertStringContainsString('digits=6', $uri);
        self::assertStringContainsString('period=30', $uri);
    }
}
