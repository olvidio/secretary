<?php

declare(strict_types=1);

namespace src\legal\domain\services;

use InvalidArgumentException;
use src\legal\domain\entity\DocumentoLegal;

final class CatalogoDocumentosLegales
{
    public const VERSION_VIGENTE = 'v2';

    /** @var list<string> */
    public const TIPOS = ['condiciones', 'privacidad'];

    public function __construct(private readonly string $directorio)
    {
    }

    public static function porDefecto(): self
    {
        return new self(dirname(__DIR__, 4) . '/docs/legal');
    }

    public function vigente(string $tipo, string $idioma = 'es'): DocumentoLegal
    {
        return $this->documento($tipo, self::VERSION_VIGENTE, $idioma);
    }

    public function documento(string $tipo, string $version, string $idioma = 'es'): DocumentoLegal
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new InvalidArgumentException(_('Documento legal desconocido'));
        }
        $idioma = $idioma === 'ca' ? 'ca' : 'es';
        $ruta = $this->ruta($tipo, $version, $idioma);
        if (!is_readable($ruta) && $idioma !== 'es') {
            $ruta = $this->ruta($tipo, $version, 'es');
            $idioma = 'es';
        }
        if (!is_readable($ruta)) {
            throw new InvalidArgumentException(_('Documento legal no encontrado'));
        }
        $texto = (string) file_get_contents($ruta);

        return new DocumentoLegal(
            $tipo,
            $version,
            $idioma,
            hash('sha256', $texto),
            $texto,
        );
    }

    /**
     * @return list<DocumentoLegal>
     */
    public function todos(): array
    {
        $out = [];
        foreach (glob($this->directorio . '/*-v*.*.md') ?: [] as $fichero) {
            $base = basename($fichero);
            if (preg_match('/^(condiciones|privacidad)-(v[^.]+)\.(es|ca)\.md$/', $base, $m) !== 1) {
                continue;
            }
            $out[] = $this->documento($m[1], $m[2], $m[3]);
        }

        return $out;
    }

    public function textoCasillaRegistro(string $idioma = 'es'): string
    {
        $cond = $this->vigente('condiciones', $idioma);
        $priv = $this->vigente('privacidad', $idioma);
        if ($idioma === 'ca') {
            return sprintf(
                'He llegit i accepto les Condicions d’ús (%s) i he estat informat de la Política de privacitat (%s). El servei és gratuit.',
                $cond->version,
                $priv->version,
            );
        }

        return sprintf(
            'He leído y acepto las Condiciones de uso (%s) y he sido informado de la Política de privacidad (%s). El servicio es gratuito.',
            $cond->version,
            $priv->version,
        );
    }

    public function textoCasillaNombres(string $idioma = 'es'): string
    {
        if ($idioma === 'ca') {
            return 'Declaro que el centre, i jo com a secretari, som responsables del tractament de les dades de les persones que dono d’alta, importo o vincule. Secretario és un programa gratuit que només allotja la informació. Tinc base legal per a aquest tractament.';
        }

        return 'Declaro que el centro, y yo como secretario, somos responsables del tratamiento de los datos de las personas que doy de alta, importo o vinculo. Secretario es un programa gratuito que solo aloja la información. Tengo base legal para ese tratamiento.';
    }

    private function ruta(string $tipo, string $version, string $idioma): string
    {
        return $this->directorio . '/' . $tipo . '-' . $version . '.' . $idioma . '.md';
    }
}
