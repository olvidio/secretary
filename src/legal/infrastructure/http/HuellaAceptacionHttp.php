<?php

declare(strict_types=1);

namespace src\legal\infrastructure\http;

use src\legal\domain\value_objects\HuellaAceptacion;
use src\shared\infrastructure\http\Request;

final class HuellaAceptacionHttp
{
    public static function desde(
        Request $request,
        string $idioma = 'es',
        ?string $email = null,
        ?string $alias = null,
        ?int $centroId = null,
        ?int $personaId = null,
        ?string $tokenHash = null,
    ): HuellaAceptacion {
        $extra = [];
        $xff = $request->header('x-forwarded-for');
        if ($xff !== null && $xff !== '' && $xff !== $request->clientIp) {
            $extra['x_forwarded_for'] = $xff;
        }

        return new HuellaAceptacion(
            $request->clientIp,
            $request->userAgent,
            $idioma === 'ca' ? 'ca' : 'es',
            $email,
            $alias,
            $centroId,
            $personaId,
            $tokenHash,
            $extra === [] ? null : $extra,
        );
    }
}
