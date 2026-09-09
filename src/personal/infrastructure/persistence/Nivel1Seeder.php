<?php

declare(strict_types=1);

namespace src\personal\infrastructure\persistence;

use PDO;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\personal\application\AsegurarPlanPersonal;

/** Siembra el plan X de cada persona con centro (Fase 7). Idempotente. */
final class Nivel1Seeder
{
    public static function sembrar(PDO $pdo): void
    {
        $existe = $pdo->query(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'cuentas'"
        )->fetchColumn();
        if ($existe === false) {
            return;
        }
        $asegurar = new AsegurarPlanPersonal(new PdoCuentaRepository($pdo));
        $st = $pdo->query('SELECT id, centro_id FROM personas WHERE centro_id IS NOT NULL');
        if ($st === false) {
            return;
        }
        foreach ($st->fetchAll() as $row) {
            $asegurar->ejecutar((int) $row['centro_id'], (int) $row['id']);
        }
    }
}
