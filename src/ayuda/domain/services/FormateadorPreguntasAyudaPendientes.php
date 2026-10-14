<?php

declare(strict_types=1);

namespace src\ayuda\domain\services;

use src\ayuda\domain\value_objects\PreguntaAyudaPendiente;

final class FormateadorPreguntasAyudaPendientes
{
    /**
     * @param list<PreguntaAyudaPendiente> $pendientes
     */
    public function json(string $versionManual, array $pendientes): string
    {
        $payload = [
            'exportado_en' => gmdate('c'),
            'version_manual' => $versionManual,
            'total_preguntas_distintas' => count($pendientes),
            'preguntas' => array_map(
                static fn (PreguntaAyudaPendiente $p): array => $p->toArray(),
                $pendientes,
            ),
        ];

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * @param list<PreguntaAyudaPendiente> $pendientes
     */
    public function markdown(string $versionManual, array $pendientes): string
    {
        $lineas = [
            '# Preguntas pendientes del manual',
            '',
            'Exportado: ' . gmdate('Y-m-d H:i:s') . ' UTC',
            'Versión del manual (hash): `' . $versionManual . '`',
            '',
            'La ayuda con IA solo usa `docs/manual/`. Estas preguntas recibieron la respuesta',
            '«no está en el manual» (origen `ia`, `resuelta = false`).',
            '',
            '## Cómo procesarlas en local (Cursor)',
            '',
            '1. Ampliar o crear ficheros en `docs/manual/` (esqueleto: para qué sirve, cómo se usa, reglas, problemas frecuentes).',
            '2. Añadir línea `- Ruta:` si es una pantalla nueva (`tests/unit/ManualCubrePantallasTest.php`).',
            '3. Volver a exportar tras desplegar: las preguntas antiguas dejan de coincidir cuando cambia el hash del manual.',
            '',
        ];
        if ($pendientes === []) {
            $lineas[] = '_No hay preguntas pendientes registradas._';
            $lineas[] = '';

            return implode("\n", $lineas);
        }
        $lineas[] = '## Listado';
        $lineas[] = '';
        foreach ($pendientes as $i => $p) {
            $n = $i + 1;
            $amb = $p->ambitos !== [] ? implode(', ', $p->ambitos) : '—';
            $lineas[] = '### ' . $n . '. ' . $this->tituloPregunta($p->pregunta);
            $lineas[] = '';
            $lineas[] = '- **Consultas:** ' . $p->veces;
            $lineas[] = '- **Primera:** ' . $p->primeraEn;
            $lineas[] = '- **Última:** ' . $p->ultimaEn;
            $lineas[] = '- **Ámbitos:** ' . $amb;
            $lineas[] = '- **Consultantes:** ' . $this->lineaConsultantes($p);
            $lineas[] = '';
            $lineas[] = '**Pregunta (texto literal):**';
            $lineas[] = '';
            $lineas[] = '```';
            $lineas[] = $p->pregunta;
            $lineas[] = '```';
            $lineas[] = '';
            $lineas[] = '**Ficheros manual sugeridos:** _(completar al editar)_';
            $lineas[] = '';
            $lineas[] = '- [ ] `docs/manual/....md`';
            $lineas[] = '';
            $lineas[] = '**Notas para el redactor:**';
            $lineas[] = '';
            $lineas[] = '- ';
            $lineas[] = '';
        }

        return implode("\n", $lineas);
    }

    private function lineaConsultantes(PreguntaAyudaPendiente $p): string
    {
        if ($p->consultantes === []) {
            return '—';
        }
        $partes = [];
        foreach ($p->consultantes as $c) {
            $txt = $c->etiqueta();
            if ($c->veces > 1) {
                $txt .= ' (' . $c->veces . '×)';
            }
            $partes[] = $txt;
        }

        return implode('; ', $partes);
    }

    private function tituloPregunta(string $pregunta): string
    {
        $una = preg_replace('/\s+/u', ' ', trim($pregunta)) ?? trim($pregunta);
        if (mb_strlen($una) <= 120) {
            return $una;
        }

        return mb_substr($una, 0, 117) . '…';
    }
}
