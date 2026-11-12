<?php

declare(strict_types=1);

namespace Tests\support;

use PDO;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\conceptos\application\ResolverConceptosCentro;
use src\plan\infrastructure\persistence\PdoDestinoSgRepository;
use src\plan\infrastructure\persistence\PdoPartidaLaboresRepository;
use src\plan\infrastructure\persistence\PdoPlanConceptoRepository;

final class ConceptosCentro
{
    public static function resolver(PDO $pdo): ResolverConceptosCentro
    {
        $plan = new PdoPlanConceptoRepository($pdo);

        return new ResolverConceptosCentro(
            $plan,
            new PdoPartidaLaboresRepository($pdo, $plan),
            new PdoDestinoSgRepository($pdo),
            new PdoCentroRepository($pdo),
            new PdoCuentaRepository($pdo),
        );
    }
}
