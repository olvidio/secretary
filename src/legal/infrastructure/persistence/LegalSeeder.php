<?php

declare(strict_types=1);

namespace src\legal\infrastructure\persistence;

use PDO;
use src\legal\domain\services\CatalogoDocumentosLegales;

final class LegalSeeder
{
    public static function sembrar(PDO $pdo): void
    {
        $existe = $pdo->query(
            "SELECT 1 FROM information_schema.tables
             WHERE table_schema = 'public' AND table_name = 'documentos_legales'"
        )->fetchColumn();
        if ($existe === false) {
            return;
        }
        $st = $pdo->prepare(
            'INSERT INTO documentos_legales (tipo, version, idioma, hash_sha256, texto, vigente_desde)
             VALUES (:tipo, :ver, :idioma, :hash, :texto, NOW())
             ON CONFLICT (tipo, version, idioma) DO NOTHING'
        );
        foreach (CatalogoDocumentosLegales::porDefecto()->todos() as $doc) {
            $st->execute([
                ':tipo' => $doc->tipo,
                ':ver' => $doc->version,
                ':idioma' => $doc->idioma,
                ':hash' => $doc->hashSha256,
                ':texto' => $doc->texto,
            ]);
        }
    }
}
