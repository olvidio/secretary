<?php

declare(strict_types=1);

namespace src\legal\infrastructure\persistence;

use PDO;
use src\legal\domain\contracts\AceptacionLegalRepository;
use src\legal\domain\entity\AceptacionLegal;

final class PdoAceptacionLegalRepository implements AceptacionLegalRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function registrar(AceptacionLegal $aceptacion): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO aceptaciones_legales (
                identidad_id, canal, momento,
                condiciones_version, condiciones_hash,
                privacidad_version, privacidad_hash,
                texto_casilla, idioma, ip, user_agent,
                email, alias, centro_id, persona_id, token_hash, extra
            ) VALUES (
                :identidad, :canal, :momento,
                :cver, :chash, :pver, :phash,
                :casilla, :idioma, :ip, :ua,
                :email, :alias, :centro, :persona, :token, :extra
            )'
        );
        $extra = $aceptacion->huella->extra;
        $st->execute([
            ':identidad' => $aceptacion->identidadId,
            ':canal' => $aceptacion->canal,
            ':momento' => $aceptacion->momento->format('c'),
            ':cver' => $aceptacion->condicionesVersion,
            ':chash' => $aceptacion->condicionesHash,
            ':pver' => $aceptacion->privacidadVersion,
            ':phash' => $aceptacion->privacidadHash,
            ':casilla' => $aceptacion->textoCasilla,
            ':idioma' => $aceptacion->huella->idioma,
            ':ip' => $aceptacion->huella->ip,
            ':ua' => $aceptacion->huella->userAgent,
            ':email' => $aceptacion->huella->email,
            ':alias' => $aceptacion->huella->alias,
            ':centro' => $aceptacion->huella->centroId,
            ':persona' => $aceptacion->huella->personaId,
            ':token' => $aceptacion->huella->tokenHash,
            ':extra' => $extra === null ? null : json_encode($extra, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
