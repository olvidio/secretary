<?php

declare(strict_types=1);

namespace src\presupuestos\application;

use InvalidArgumentException;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;
use src\presupuestos\domain\services\AgrupadorPrevision613P;
use src\shared\domain\value_objects\Dinero;

final class ObtenerPrevisionPersonal
{
    public function __construct(
        private readonly ConstruirHojaPrevision $hoja,
        private readonly PersonaRepository $personas,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $personaId): array
    {
        $persona = $this->exigirPersonaDelCentro($personaId);
        $payload = $this->hoja->ejecutar($personaId);
        $payload['persona'] = [
            'id' => $persona->id,
            'iniciales' => $persona->iniciales,
            'nombre' => $persona->nombreCompleto(),
        ];
        $payload['filas'] = self::filasAgrupadas($payload['lineas']);

        return $payload;
    }

    public function exigirPersonaDelCentro(int $personaId): Persona
    {
        $datos = $this->hoja->contextoEstructura();
        $persona = $this->personas->porId($personaId);
        if ($persona === null || $persona->id === null || $persona->centroId !== $datos['centro_id'] || !$persona->activo) {
            throw new InvalidArgumentException(_("Persona no encontrada en este centro"));
        }

        return $persona;
    }

    /**
     * @param list<array<string, mixed>> $lineas
     * @return list<array<string, mixed>>
     */
    private static function filasAgrupadas(array $lineas): array
    {
        $porCodigo = [];
        $para = [];
        foreach ($lineas as $l) {
            $codigo = (string) $l['codigo'];
            $porCodigo[$codigo] = $l;
            $para[] = [
                'codigo' => $codigo,
                'etiqueta' => (string) $l['etiqueta'],
                'grupo' => (string) ($l['grupo'] ?? AgrupadorPrevision613P::grupoDe($codigo)),
                'total_cents' => (int) $l['calculado_cents'],
                'personas_cents' => [(int) ($l['previsto_cents'] ?? 0)],
            ];
        }
        $filas = [];
        foreach (AgrupadorPrevision613P::filas(AgrupadorPrevision613P::filtrar212SinUso($para)) as $f) {
            $orig = $f['codigo'] !== null ? ($porCodigo[$f['codigo']] ?? null) : null;
            $calc = Dinero::fromCents($f['total_cents']);
            $prevCents = $f['personas_cents'][0] ?? 0;
            $prev = Dinero::fromCents($prevCents);
            $filas[] = [
                'tipo' => $f['tipo'],
                'grupo' => $f['grupo'],
                'codigo' => $f['codigo'],
                'etiqueta' => $f['etiqueta'],
                'editable' => $orig !== null,
                'modo' => $orig['modo'] ?? null,
                'acumulado_es' => $orig['acumulado_es'] ?? null,
                'calculado' => $calc->toString(),
                'calculado_es' => $calc->formatEs(),
                'calculado_cents' => $f['total_cents'],
                'previsto' => $orig !== null ? $orig['previsto'] : $prev->toString(),
                'previsto_es' => $orig !== null ? $orig['previsto_es'] : $prev->formatEs(),
                'previsto_cents' => $orig !== null ? $orig['previsto_cents'] : $prevCents,
            ];
        }

        return $filas;
    }
}
