#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Escribe var/version.json y VERSION a partir de un tag (p. ej. en deploy.sh) o de git describe.
 *
 *   php tools/release/escribir_version.php           # git describe local
 *   php tools/release/escribir_version.php v0.1.4    # tag concreto (deploy)
 */
$root = dirname(__DIR__, 2);
$tag = isset($argv[1]) && is_string($argv[1]) && $argv[1] !== '' ? $argv[1] : null;

$commit = null;
$anterior = getcwd();
chdir($root);
$gitBase = 'git -c safe.directory=' . escapeshellarg($root);
if ($tag === null) {
    $out = [];
    exec("$gitBase describe --tags --always --dirty 2>/dev/null", $out, $code);
    $tag = $code === 0 && ($out[0] ?? '') !== '' ? $out[0] : null;
}
if ($tag === null) {
    $version = @file_get_contents($root . '/VERSION');
    $tag = is_string($version) && trim($version) !== '' ? trim($version) : 'desarrollo';
}
exec("$gitBase rev-parse --short HEAD 2>/dev/null", $commitOut, $commitCode);
if ($commitCode === 0 && ($commitOut[0] ?? '') !== '') {
    $commit = $commitOut[0];
}
if ($anterior !== false) {
    chdir($anterior);
}

$datos = [
    'version' => $tag,
    'fecha' => date('Y-m-d'),
];
if ($commit !== null) {
    $datos['commit'] = $commit;
}

$directorio = $root . '/var';
if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
    fwrite(STDERR, "No se pudo crear $directorio\n");
    exit(1);
}

$json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, "No se pudo serializar version.json\n");
    exit(1);
}

file_put_contents($directorio . '/version.json', $json . "\n");
@chmod($directorio . '/version.json', 0644);
file_put_contents($root . '/VERSION', $tag . "\n");
@chmod($root . '/VERSION', 0644);
fwrite(STDOUT, "Escrito var/version.json y VERSION ($tag)\n");
