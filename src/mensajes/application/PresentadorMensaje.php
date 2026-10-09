<?php

declare(strict_types=1);

namespace src\mensajes\application;

use src\disponible\domain\services\RepartidorLabores;

/** Texto visible de un aviso de la bandeja. */
final class PresentadorMensaje
{
    /**
     * @param array{id:int, tipo:string, payload:string, leido_at:?string, creado_at:string} $row
     * @return array{id:int, tipo:string, titulo:string, cuerpo:string, href:?string, accion:?string, leido:bool, fecha:string}
     */
    public static function presentar(array $row): array
    {
        $payload = json_decode($row['payload'], true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $vista = match ($row['tipo']) {
            'destinos_7' => self::destinos($payload),
            'remesa_detalle' => self::detalle($payload),
            default => [
                'titulo' => _('Aviso'),
                'cuerpo' => '',
                'href' => null,
                'accion' => null,
            ],
        };
        $fecha = substr($row['creado_at'], 0, 10);

        return [
            'id' => $row['id'],
            'tipo' => $row['tipo'],
            'titulo' => $vista['titulo'],
            'cuerpo' => $vista['cuerpo'],
            'href' => $vista['href'],
            'accion' => $vista['accion'],
            'leido' => $row['leido_at'] !== null,
            'fecha' => $fecha,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{titulo:string, cuerpo:string, href:?string, accion:?string}
     */
    private static function destinos(array $payload): array
    {
        $filas = [];
        foreach ($payload['lineas'] ?? [] as $linea) {
            if (!is_array($linea)) {
                continue;
            }
            $filas[] = [
                'persona_id' => 0,
                'codigo' => (string) ($linea['codigo'] ?? ''),
                'etiqueta' => (string) ($linea['etiqueta'] ?? ''),
                'cents' => (int) ($linea['cents'] ?? 0),
            ];
        }

        $texto = RepartidorLabores::texto($filas);
        $quien = trim((string) ($payload['quien'] ?? ''));
        if ($quien !== '' && $texto !== '') {
            $texto = $quien . ': ' . $texto;
        }

        return [
            'titulo' => _('Destinos de labores'),
            'cuerpo' => $texto,
            'href' => '/yo/remesas',
            'accion' => _('Ver en la remesa'),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{titulo:string, cuerpo:string, href:?string, accion:?string}
     */
    private static function detalle(array $payload): array
    {
        $codigo = (string) ($payload['codigo'] ?? '');
        $nombre = trim((string) ($payload['nombre'] ?? ''));
        $concepto = trim($codigo . ($nombre !== '' ? ' ' . $nombre : ''));
        $mes = (int) ($payload['mes'] ?? 0);
        $anio = (int) ($payload['anio'] ?? 0);
        $version = (int) ($payload['version'] ?? 0);

        $quien = trim((string) ($payload['quien'] ?? ''));
        $cuerpo = sprintf(
            _('Concepto %s de %02d/%d (v%d). Hay que autorizar o denegar el desglose.'),
            $concepto !== '' ? $concepto : '—',
            $mes,
            $anio,
            $version,
        );
        if ($quien !== '') {
            $cuerpo = $quien . '. ' . $cuerpo;
        }

        return [
            'titulo' => _('El centro pide el detalle de una remesa'),
            'cuerpo' => $cuerpo,
            'href' => '/yo/remesas',
            'accion' => _('Ir a la remesa'),
        ];
    }
}
