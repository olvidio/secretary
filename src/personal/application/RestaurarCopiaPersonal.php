<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use src\personal\domain\contracts\CopiaPersonalRepository;
use src\personal\infrastructure\persistence\AlmacenCopiasPersonal;
use src\personal\infrastructure\persistence\RutasCopiasPersonal;
use src\personas\domain\contracts\PersonaRepository;

final class RestaurarCopiaPersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly PersonaRepository $personas,
        private readonly CopiaPersonalRepository $copias,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{movimientos: int, mensaje: string}
     */
    public function ejecutar(array $datos, ?string $rutaSubida = null): array
    {
        if (empty($datos['confirmar'])) {
            throw new InvalidArgumentException('Confirme la restauración');
        }
        $ctx = $this->ambito->ejecutar();
        $persona = $this->personas->porId($ctx->personaId);
        if ($persona === null) {
            throw new InvalidArgumentException('Persona no encontrada');
        }
        $almacen = new AlmacenCopiasPersonal(
            RutasCopiasPersonal::directorio(),
            $ctx->personaId,
            $persona->iniciales,
        );
        if ($rutaSubida !== null) {
            $raw = file_get_contents($rutaSubida);
            if ($raw === false) {
                throw new InvalidArgumentException('No se pudo leer la copia subida');
            }
            $snapshot = json_decode($raw, true);
            if (!is_array($snapshot)) {
                throw new InvalidArgumentException('La copia personal no es JSON válido');
            }
        } else {
            $nombre = trim((string) ($datos['fichero'] ?? ''));
            if ($nombre === '') {
                throw new InvalidArgumentException('Indique el fichero a restaurar');
            }
            $snapshot = $almacen->leer($nombre);
        }
        $this->copias->restaurar($ctx->centroId, $ctx->personaId, $snapshot);
        $n = count($snapshot['asientos'] ?? []);

        return [
            'movimientos' => $n,
            'mensaje' => sprintf(
                'Restaurados %d movimientos personales. Las remesas del centro no se modifican.',
                $n,
            ),
        ];
    }
}
