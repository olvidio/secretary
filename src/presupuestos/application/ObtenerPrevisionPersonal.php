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
    public function opciones(): array
    {
        return $this->hoja->opcionesPrevision();
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $personaId, ?string $etiquetaObjetivo = null): array
    {
        $persona = $this->exigirPersonaDelCentro($personaId);
        $payload = $this->hoja->ejecutar($personaId, $etiquetaObjetivo);
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
            $prevRef = $l['previsto_ejercicio_actual_cents'] ?? null;
            $para[] = [
                'codigo' => $codigo,
                'etiqueta' => (string) $l['etiqueta'],
                'grupo' => (string) ($l['grupo'] ?? AgrupadorPrevision613P::grupoDe($codigo)),
                'total_cents' => (int) $l['calculado_cents'],
                'acumulado_cents' => (int) $l['acumulado_cents'],
                'prev_actual_cents' => $prevRef !== null ? (int) $prevRef : null,
                'personas_cents' => [(int) ($l['previsto_cents'] ?? 0)],
            ];
        }
        $filas = [];
        foreach (AgrupadorPrevision613P::filas(AgrupadorPrevision613P::filtrar212SinUso($para)) as $f) {
            $orig = $f['codigo'] !== null ? ($porCodigo[$f['codigo']] ?? null) : null;
            $calc = Dinero::fromCents($f['total_cents']);
            $prevCents = $f['personas_cents'][0] ?? 0;
            $prev = Dinero::fromCents($prevCents);
            $acumCents = (int) ($f['acumulado_cents'] ?? 0);
            $prevActRaw = $f['prev_actual_cents'] ?? null;
            $prevActCents = $prevActRaw !== null ? (int) $prevActRaw : null;
            $sinReferencia = $orig !== null
                && ($orig['acumulado'] ?? null) === null
                && ($orig['acumulado_es'] ?? null) === null;
            $filas[] = [
                'tipo' => $f['tipo'],
                'grupo' => $f['grupo'],
                'codigo' => $f['codigo'],
                'etiqueta' => $f['etiqueta'],
                'editable' => $orig !== null,
                'modo' => $orig['modo'] ?? null,
                'acumulado_es' => $sinReferencia
                    ? null
                    : ($orig['acumulado_es'] ?? Dinero::fromCents($acumCents)->formatEs()),
                'referencia_es' => $sinReferencia
                    ? ''
                    : self::referenciaEs($acumCents, $prevActCents ?? 0),
                'previsto_ejercicio_actual_es' => $orig['previsto_ejercicio_actual_es'] ?? (
                    $prevActCents !== null && $prevActCents !== 0
                        ? Dinero::fromCents($prevActCents)->formatEs()
                        : null
                ),
                'calculado' => $sinReferencia ? null : $calc->toString(),
                'calculado_es' => $sinReferencia ? null : $calc->formatEs(),
                'calculado_cents' => $f['total_cents'],
                'previsto' => $orig !== null ? ($orig['previsto'] ?? null) : $prev->toString(),
                'previsto_es' => $orig !== null ? ($orig['previsto_es'] ?? null) : $prev->formatEs(),
                'previsto_cents' => $orig !== null ? ($orig['previsto_cents'] ?? null) : $prevCents,
                'previsto_ejercicio_actual_cents' => $prevActCents,
            ];
        }

        return $filas;
    }

    private static function referenciaEs(int $acumCents, int $prevActCents): string
    {
        $acumTxt = self::enteroEs(Dinero::fromCents($acumCents));
        $prevTxt = self::enteroEs(Dinero::fromCents($prevActCents));
        if ($acumTxt === '' && $prevTxt === '') {
            return '';
        }
        if ($prevTxt === '') {
            return $acumTxt;
        }
        if ($acumTxt === '') {
            return '(' . $prevTxt . ')';
        }

        return $acumTxt . ' (' . $prevTxt . ')';
    }

    private static function enteroEs(Dinero $dinero): string
    {
        $n = (int) round($dinero->toCents() / 100);
        if ($n === 0) {
            return '';
        }

        return number_format($n, 0, ',', '.');
    }
}
