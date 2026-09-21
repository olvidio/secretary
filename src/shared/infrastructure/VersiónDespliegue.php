<?php

declare(strict_types=1);

namespace src\shared\infrastructure;

final class VersiónDespliegue
{
    public function __construct(private readonly string $raízProyecto)
    {
    }

    public static function porDefecto(): self
    {
        return new self(dirname(__DIR__, 3));
    }

    public function etiqueta(): string
    {
        $datos = $this->datos();
        if ($datos !== null && ($datos['version'] ?? '') !== '') {
            return (string) $datos['version'];
        }

        return 'desarrollo';
    }

    public function rutaChangelog(): string
    {
        return $this->raízProyecto . '/CHANGELOG.md';
    }

    public function textoChangelog(): ?string
    {
        $ruta = $this->rutaChangelog();
        if (!is_readable($ruta)) {
            return null;
        }

        return (string) file_get_contents($ruta);
    }

    /** @return array{version?: string, fecha?: string, commit?: string}|null */
    private function datos(): ?array
    {
        $ruta = $this->raízProyecto . '/var/version.json';
        if (!is_readable($ruta)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($ruta), true);

        return is_array($json) ? $json : null;
    }
}
