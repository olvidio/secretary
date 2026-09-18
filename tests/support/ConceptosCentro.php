<?php

declare(strict_types=1);

namespace Tests\support;

use PDO;
use src\conceptos\application\ResolverConceptosCentro;
use src\plan\infrastructure\persistence\PdoPartidaLaboresRepository;
use src\plan\infrastructure\persistence\PdoPlanConceptoRepository;

final class ConceptosCentro
{
    public static function resolver(PDO $pdo): ResolverConceptosCentro
    {
        $plan = new PdoPlanConceptoRepository($pdo);

        return new ResolverConceptosCentro($plan, new PdoPartidaLaboresRepository($pdo, $plan));
    }
}
