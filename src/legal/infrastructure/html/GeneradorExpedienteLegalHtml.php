<?php

declare(strict_types=1);

namespace src\legal\infrastructure\html;

use src\legal\infrastructure\markdown\RenderizadorMarkdownLegal;

final class GeneradorExpedienteLegalHtml
{
    /**
     * @param array{
     *     generado: string,
     *     operador: array{nombre: string, email: string, direccion: string},
     *     usuario: array{
     *         id: int,
     *         email: string,
     *         alias: ?string,
     *         nombre: string,
     *         es_admin: bool,
     *         email_verificado_at: ?string
     *     },
     *     aceptaciones: list<array<string, mixed>>,
     *     documentos: array<string, array{
     *         tipo: string,
     *         version: string,
     *         idioma: string,
     *         hash: string,
     *         texto: string
     *     }>
     * } $expediente
     */
    public static function documento(array $expediente): string
    {
        $usuario = $expediente['usuario'];
        $alias = $usuario['alias'] ?? $usuario['email'];
        $titulo = sprintf(_('Expediente legal — %s'), (string) $alias);
        $cuerpo = self::cuerpo($expediente);

        return '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>' . self::esc($titulo) . '</title>
<style>
body { font-family: Georgia, "Times New Roman", serif; font-size: 11pt; line-height: 1.45; color: #111; margin: 18mm 16mm; }
h1 { font-size: 18pt; margin: 0 0 8pt; }
h2 { font-size: 13pt; margin: 18pt 0 8pt; page-break-after: avoid; }
h3 { font-size: 11pt; margin: 12pt 0 6pt; page-break-after: avoid; }
p, li { margin: 0 0 6pt; }
.muted { color: #444; font-size: 10pt; }
table { width: 100%; border-collapse: collapse; margin: 8pt 0 12pt; font-size: 10pt; }
th, td { border: 1px solid #bbb; padding: 4pt 6pt; vertical-align: top; text-align: left; }
th { background: #f3f3f3; }
.evento { border: 1px solid #ccc; padding: 8pt 10pt; margin: 0 0 10pt; page-break-inside: avoid; }
.doc-anexo { border-top: 2px solid #999; margin-top: 16pt; padding-top: 10pt; page-break-before: always; }
.doc-anexo:first-of-type { page-break-before: auto; }
@media print { body { margin: 12mm; } }
</style>
</head>
<body>
' . $cuerpo . '
</body>
</html>';
    }

    /**
     * @param array{
     *     generado: string,
     *     operador: array{nombre: string, email: string, direccion: string},
     *     usuario: array{
     *         id: int,
     *         email: string,
     *         alias: ?string,
     *         nombre: string,
     *         es_admin: bool,
     *         email_verificado_at: ?string
     *     },
     *     aceptaciones: list<array<string, mixed>>,
     *     documentos: array<string, array{
     *         tipo: string,
     *         version: string,
     *         idioma: string,
     *         hash: string,
     *         texto: string
     *     }>
     * } $expediente
     */
    public static function cuerpo(array $expediente): string
    {
        $usuario = $expediente['usuario'];
        $operador = $expediente['operador'];
        $alias = $usuario['alias'] ?? $usuario['email'];
        $html = '<h1>' . self::esc(sprintf(_('Expediente legal — %s'), (string) $alias)) . '</h1>';
        $html .= '<p class="muted">' . self::esc(_('Generado el')) . ' '
            . self::esc(self::fmtFecha($expediente['generado'])) . '</p>';
        $html .= '<h2>' . self::esc(_('Operador del servicio')) . '</h2>';
        $html .= '<p>' . self::esc((string) $operador['nombre']) . '<br>'
            . self::esc((string) $operador['email']) . '<br>'
            . self::esc((string) $operador['direccion']) . '</p>';
        $html .= '<h2>' . self::esc(_('Usuario')) . '</h2>';
        $html .= '<table><tbody>';
        $html .= self::fila(_('Identificador'), (string) $usuario['id']);
        $html .= self::fila(_('Correo'), (string) $usuario['email']);
        $html .= self::fila(_('Alias'), (string) ($usuario['alias'] ?? '—'));
        $html .= self::fila(_('Nombre'), (string) $usuario['nombre']);
        $html .= self::fila(
            _('Correo verificado'),
            $usuario['email_verificado_at'] === null
                ? _('No')
                : self::fmtFecha((string) $usuario['email_verificado_at']),
        );
        $html .= '</tbody></table>';
        $html .= '<h2>' . self::esc(_('Cronología de aceptaciones')) . '</h2>';
        if ($expediente['aceptaciones'] === []) {
            $html .= '<p class="muted">' . self::esc(_('No hay registros de aceptación para este usuario.')) . '</p>';
        }
        foreach ($expediente['aceptaciones'] as $i => $a) {
            $n = $i + 1;
            $html .= '<div class="evento">';
            $html .= '<h3>' . self::esc((string) ($a['canal_etiqueta'] ?? $a['canal'])) . ' #' . $n . '</h3>';
            $html .= '<table><tbody>';
            $html .= self::fila(_('Momento'), self::fmtFecha((string) $a['momento']));
            $html .= self::fila(_('Canal'), (string) ($a['canal_etiqueta'] ?? $a['canal']));
            $html .= self::fila(_('Idioma'), (string) $a['idioma']);
            $html .= self::fila(_('Texto de la casilla'), (string) $a['texto_casilla']);
            $html .= self::fila(
                _('Condiciones'),
                (string) $a['condiciones_version'] . ' · SHA-256: ' . (string) $a['condiciones_hash'],
            );
            $html .= self::fila(
                _('Privacidad'),
                (string) $a['privacidad_version'] . ' · SHA-256: ' . (string) $a['privacidad_hash'],
            );
            $html .= self::fila(_('IP'), (string) ($a['ip'] ?? '—'));
            $html .= self::fila(_('Navegador'), (string) ($a['user_agent'] ?? '—'));
            if (!empty($a['centro_id'])) {
                $centro = trim((string) ($a['centro_codigo'] ?? '') . ' — ' . (string) ($a['centro_nombre'] ?? ''));
                $html .= self::fila(_('Centro'), $centro . ' (#' . (int) $a['centro_id'] . ')');
            }
            if (!empty($a['persona_id'])) {
                $persona = trim((string) ($a['persona_iniciales'] ?? '') . ' ' . (string) ($a['persona_nombre'] ?? ''));
                $html .= self::fila(_('Persona'), $persona . ' (#' . (int) $a['persona_id'] . ')');
            }
            if (!empty($a['token_hash'])) {
                $html .= self::fila(_('Huella del token'), (string) $a['token_hash']);
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }
        if ($expediente['documentos'] !== []) {
            $html .= '<h2>' . self::esc(_('Anexos — textos legales aceptados')) . '</h2>';
            foreach ($expediente['documentos'] as $doc) {
                $tipo = $doc['tipo'] === 'privacidad' ? _('Política de privacidad') : _('Condiciones de uso');
                $html .= '<div class="doc-anexo">';
                $html .= '<h3>' . self::esc($tipo) . ' '
                    . self::esc((string) $doc['version']) . ' (' . self::esc((string) $doc['idioma']) . ')</h3>';
                $html .= '<p class="muted">SHA-256: ' . self::esc((string) $doc['hash']) . '</p>';
                $html .= RenderizadorMarkdownLegal::aHtml((string) $doc['texto']);
                $html .= '</div>';
            }
        }
        $html .= '<p class="muted">' . self::esc(
            _('Nota: la dirección IP puede corresponder a una red compartida o a un operador de acceso; no identifica por sí sola a una persona.'),
        ) . '</p>';

        return $html;
    }

    private static function fila(string $etiqueta, string $valor): string
    {
        return '<tr><th>' . self::esc($etiqueta) . '</th><td>' . self::esc($valor) . '</td></tr>';
    }

    private static function fmtFecha(string $iso): string
    {
        try {
            return (new \DateTimeImmutable($iso))->format('Y-m-d H:i:s T');
        } catch (\Exception) {
            return $iso;
        }
    }

    private static function esc(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
