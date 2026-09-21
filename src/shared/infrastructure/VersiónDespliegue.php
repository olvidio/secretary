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
        $datos = $this->datosJson();
        if ($datos !== null && ($datos['version'] ?? '') !== '') {
            return (string) $datos['version'];
        }

        $fichero = $this->desdeFicheroVersion();
        if ($fichero !== null) {
            return $fichero;
        }

        $git = $this->desdeGit();
        if ($git !== null) {
            return $git;
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
    private function datosJson(): ?array
    {
        $ruta = $this->raízProyecto . '/var/version.json';
        if (!is_readable($ruta)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($ruta), true);

        return is_array($json) ? $json : null;
    }

    private function desdeFicheroVersion(): ?string
    {
        $ruta = $this->raízProyecto . '/VERSION';
        if (!is_readable($ruta)) {
            return null;
        }
        $linea = trim((string) file_get_contents($ruta));
        if ($linea === '' || preg_match('/^v?[0-9]+(\.[0-9]+)*(-[0-9A-Za-z.-]+)?$/', $linea) !== 1) {
            return null;
        }

        return str_starts_with($linea, 'v') ? $linea : 'v' . $linea;
    }

    private function desdeGit(): ?string
    {
        if (!is_dir($this->raízProyecto . '/.git')) {
            return null;
        }
        $out = [];
        $cmd = sprintf(
            'git -c safe.directory=%s describe --tags --always 2>/dev/null',
            escapeshellarg($this->raízProyecto),
        );
        exec($cmd, $out, $code);
        if ($code !== 0 || ($out[0] ?? '') === '') {
            return null;
        }

        return (string) $out[0];
    }
}
