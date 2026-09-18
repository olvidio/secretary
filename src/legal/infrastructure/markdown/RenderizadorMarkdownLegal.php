<?php

declare(strict_types=1);

namespace src\legal\infrastructure\markdown;

final class RenderizadorMarkdownLegal
{
    public static function aHtml(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $bloques = preg_split("/\n{2,}/", trim($markdown)) ?: [];
        $html = [];
        foreach ($bloques as $bloque) {
            $bloque = trim($bloque);
            if ($bloque === '') {
                continue;
            }
            if (preg_match('/^# (.+)$/s', $bloque, $m) === 1) {
                $html[] = '<h1>' . self::linea(trim($m[1])) . '</h1>';
                continue;
            }
            if (preg_match('/^## (.+)$/s', $bloque, $m) === 1) {
                $html[] = '<h2>' . self::linea(trim($m[1])) . '</h2>';
                continue;
            }
            $lineas = explode("\n", $bloque);
            $esLista = true;
            foreach ($lineas as $linea) {
                if (!str_starts_with(ltrim($linea), '- ')) {
                    $esLista = false;
                    break;
                }
            }
            if ($esLista) {
                $items = [];
                foreach ($lineas as $linea) {
                    $items[] = '<li>' . self::linea(substr(ltrim($linea), 2)) . '</li>';
                }
                $html[] = '<ul>' . implode('', $items) . '</ul>';
                continue;
            }
            $html[] = '<p>' . self::linea(str_replace("\n", ' ', $bloque)) . '</p>';
        }

        return implode("\n", $html);
    }

    private static function linea(string $texto): string
    {
        $escapado = htmlspecialchars($texto, ENT_QUOTES);
        $escapado = (string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $escapado);
        $escapado = (string) preg_replace(
            '/\[([^\]]+)\]\((https?:[^)]+|\/[^)]+)\)/',
            '<a href="$2">$1</a>',
            $escapado,
        );

        return $escapado;
    }
}
