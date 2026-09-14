<?php

declare(strict_types=1);

namespace src\shared\infrastructure\persistence;

use PDO;
use RuntimeException;

/** Copia de seguridad y restauración PostgreSQL (SQL plano; legado custom .dump). */
final class PostgresDumper
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $user,
        private readonly string $password,
        private readonly ?int $serverMajor = null,
        private ?string $pgDump = null,
        private ?string $pgRestore = null,
    ) {
    }

    public static function fromEnv(?PDO $pdo = null): self
    {
        $parsed = self::parsePgsqlDsn(ConnectionFactory::env('DATABASE_DSN', '') ?: '');
        $user = ConnectionFactory::env('DB_USER', '') ?: '';
        $password = ConnectionFactory::env('DB_PASSWORD', '') ?: '';
        if ($user === '') {
            throw new RuntimeException('DB_USER es obligatorio para copias de seguridad');
        }
        $serverMajor = $pdo !== null ? self::serverMajorVersion($pdo) : null;

        return self::forConnection(
            $parsed['host'],
            $parsed['port'],
            $parsed['dbname'],
            $user,
            $password,
            $serverMajor,
        );
    }

    public static function forConnection(
        string $host,
        int $port,
        string $database,
        string $user,
        string $password,
        ?int $serverMajor = null,
    ): self {
        return new self($host, $port, $database, $user, $password, $serverMajor);
    }

    public static function serverMajorVersion(PDO $pdo): int
    {
        $num = $pdo->query('SHOW server_version_num')->fetchColumn();
        if ($num === false) {
            throw new RuntimeException('No se pudo leer la versión del servidor PostgreSQL');
        }

        return (int) ((int) $num / 10000);
    }

    /** @return array{host: string, port: int, dbname: string} */
    public static function parsePgsqlDsn(string $dsn): array
    {
        if ($dsn === '') {
            throw new RuntimeException('DATABASE_DSN es obligatorio');
        }
        if (!str_starts_with($dsn, 'pgsql:')) {
            throw new RuntimeException('DATABASE_DSN debe ser un DSN PostgreSQL (pgsql:...)');
        }

        $params = [];
        foreach (explode(';', substr($dsn, strlen('pgsql:'))) as $part) {
            if ($part === '' || !str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $part, 2);
            $params[$key] = $value;
        }

        $host = $params['host'] ?? '';
        $dbname = $params['dbname'] ?? '';
        if ($host === '' || $dbname === '') {
            throw new RuntimeException('DATABASE_DSN debe incluir host y dbname');
        }

        return [
            'host' => $host,
            'port' => isset($params['port']) && $params['port'] !== '' ? (int) $params['port'] : 5432,
            'dbname' => $dbname,
        ];
    }

    public function database(): string
    {
        return $this->database;
    }

    /** Genera un volcado SQL plano (legible y portable entre versiones cercanas). */
    public function backup(string $outputPath): void
    {
        $dir = dirname($outputPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("No se pudo crear el directorio de copias: $dir");
        }

        if (str_ends_with(strtolower($outputPath), '.dump')) {
            $this->backupCustom($outputPath);

            return;
        }

        $sql = $this->runCapture([
            $this->clienteDump(),
            '-h', $this->host,
            '-p', (string) $this->port,
            '-U', $this->user,
            '-Fp',
            '--clean',
            '--if-exists',
            '--no-owner',
            $this->database,
        ]);
        if (file_put_contents($outputPath, $this->sanitizarSqlVolcado($sql)) === false) {
            throw new RuntimeException("No se pudo escribir la copia: $outputPath");
        }
    }

    public function restore(string $inputPath): void
    {
        if (!is_file($inputPath)) {
            throw new RuntimeException("No existe el fichero de copia: $inputPath");
        }
        if (!is_readable($inputPath)) {
            throw new RuntimeException("No se puede leer el fichero de copia: $inputPath");
        }

        if (str_ends_with(strtolower($inputPath), '.sql')) {
            $this->restoreSql($inputPath);

            return;
        }

        try {
            $this->restoreCustom($inputPath);
        } catch (RuntimeException $e) {
            if (!$this->esIncompatibilidadParametroSesion($e->getMessage())) {
                throw $e;
            }
            $this->restoreCustomFiltrado($inputPath);
        }
    }

    private function backupCustom(string $outputPath): void
    {
        $this->run([
            $this->clienteDump(),
            '-h', $this->host,
            '-p', (string) $this->port,
            '-U', $this->user,
            '-Fc',
            '-f', $outputPath,
            $this->database,
        ]);
    }

    private function restoreSql(string $inputPath): void
    {
        $ruta = $this->prepararSqlRestauracion($inputPath);
        try {
            $this->run([
                self::resolveBinary('psql', $this->serverMajor, true),
                '-h', $this->host,
                '-p', (string) $this->port,
                '-U', $this->user,
                '-d', $this->database,
                '-v', 'ON_ERROR_STOP=1',
                '-f', $ruta,
            ]);
        } finally {
            if ($ruta !== $inputPath && is_file($ruta)) {
                unlink($ruta);
            }
        }
    }

    private function restoreCustom(string $inputPath): void
    {
        $this->run([
            $this->clienteRestore(),
            '-h', $this->host,
            '-p', (string) $this->port,
            '-U', $this->user,
            '-d', $this->database,
            '--clean',
            '--if-exists',
            '--no-owner',
            $inputPath,
        ]);
    }

    private function restoreCustomFiltrado(string $inputPath): void
    {
        $psql = self::resolveBinary('psql', $this->serverMajor, true);
        $comando = sprintf(
            '%s --clean --if-exists --no-owner -f - %s 2>/dev/null | %s | %s -h %s -p %d -U %s -d %s -v ON_ERROR_STOP=1',
            escapeshellarg($this->clienteRestore()),
            escapeshellarg($inputPath),
            escapeshellarg($this->filtroSedParametrosSesion()),
            escapeshellarg($psql),
            escapeshellarg($this->host),
            $this->port,
            escapeshellarg($this->user),
            escapeshellarg($this->database),
        );
        $this->runShell($comando);
    }

    private function prepararSqlRestauracion(string $inputPath): string
    {
        $sql = file_get_contents($inputPath);
        if ($sql === false) {
            throw new RuntimeException("No se pudo leer la copia: $inputPath");
        }
        $limpio = $this->sanitizarSqlVolcado($sql);
        if ($limpio === $sql) {
            return $inputPath;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'secsql_');
        if ($tmp === false) {
            throw new RuntimeException('No se pudo preparar un temporal para la restauración');
        }
        $conExt = $tmp . '.sql';
        if (!rename($tmp, $conExt)) {
            unlink($tmp);
            throw new RuntimeException('No se pudo preparar un temporal para la restauración');
        }
        if (file_put_contents($conExt, $limpio) === false) {
            unlink($conExt);
            throw new RuntimeException('No se pudo preparar un temporal para la restauración');
        }

        return $conExt;
    }

    /**
     * Quita SET de parámetros que solo existen en majors más nuevos que el servidor
     * (p. ej. transaction_timeout, PG 17).
     */
    private function sanitizarSqlVolcado(string $sql): string
    {
        if ($this->serverMajor === null || $this->serverMajor >= 17) {
            return $sql;
        }

        $lineas = preg_split('/\r\n|\r|\n/', $sql) ?: [];
        $filtradas = [];
        foreach ($lineas as $linea) {
            if (preg_match('/^\s*SET\s+transaction_timeout\s*=/i', $linea) === 1) {
                continue;
            }
            $inicio = ltrim($linea);
            if (str_starts_with($inicio, '\\restrict') || str_starts_with($inicio, '\\unrestrict')) {
                continue;
            }
            $filtradas[] = $linea;
        }

        return implode("\n", $filtradas);
    }

    private function filtroSedParametrosSesion(): string
    {
        return 'sed -e "/transaction_timeout/d"';
    }

    private function esIncompatibilidadParametroSesion(string $mensaje): bool
    {
        return str_contains($mensaje, 'transaction_timeout')
            || str_contains($mensaje, 'unrecognized configuration parameter');
    }

    private function clienteDump(): string
    {
        return $this->pgDump ??= self::resolveBinary('pg_dump', $this->serverMajor, true);
    }

    private function clienteRestore(): string
    {
        return $this->pgRestore ??= self::resolveBinary('pg_restore', $this->serverMajor, true);
    }

    /** @param list<string> $command */
    private function run(array $command): void
    {
        $this->runProcess($command);
    }

    /** @param list<string> $command */
    private function runCapture(array $command): string
    {
        return $this->runProcess($command, true);
    }

    /** @param list<string> $command */
    private function runProcess(array $command, bool $capturarSalida = false): string
    {
        $env = getenv();
        if (!is_array($env)) {
            $env = [];
        }
        $env['PGPASSWORD'] = $this->password;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptors, $pipes, null, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('No se pudo ejecutar ' . $command[0]);
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $detail = trim($stderr !== false && $stderr !== '' ? $stderr : (string) $stdout);
            $msg = $command[0] . ' falló (código ' . $exitCode . ')';
            if ($detail !== '') {
                $msg .= ': ' . $detail;
            }
            throw new RuntimeException($msg);
        }

        return $stdout !== false ? $stdout : '';
    }

    private static function resolveBinary(string $name, ?int $serverMajor, bool $permitirMasNuevo = true): string
    {
        $envKey = match ($name) {
            'pg_dump' => 'PG_DUMP',
            'pg_restore' => 'PG_RESTORE',
            'psql' => 'PG_PSQL',
            default => strtoupper($name),
        };
        $fromEnv = ConnectionFactory::env($envKey);
        if ($fromEnv !== null && $fromEnv !== '' && is_executable($fromEnv)) {
            return $fromEnv;
        }

        $candidatos = [];
        if ($serverMajor !== null) {
            $candidatos[] = "/usr/lib/postgresql/$serverMajor/bin/$name";
        }
        $candidatos[] = '/usr/bin/' . $name;
        $candidatos[] = '/usr/local/bin/' . $name;

        foreach ($candidatos as $path) {
            if (!is_executable($path)) {
                continue;
            }
            if (
                !$permitirMasNuevo
                && $serverMajor !== null
                && self::clientMajorVersion($path) > $serverMajor
            ) {
                continue;
            }

            return $path;
        }

        $out = [];
        $code = 0;
        exec('command -v ' . escapeshellarg($name), $out, $code);
        if ($code === 0 && isset($out[0]) && $out[0] !== '' && is_executable($out[0])) {
            $path = $out[0];
            if (
                !$permitirMasNuevo
                && $serverMajor !== null
                && self::clientMajorVersion($path) > $serverMajor
            ) {
                throw new RuntimeException(
                    "El cliente $name (PostgreSQL " . self::clientMajorVersion($path) . ') '
                    . "es más nuevo que el servidor ($serverMajor). "
                    . "Instale postgresql-client-$serverMajor o defina $envKey en .env."
                );
            }

            return $path;
        }

        throw new RuntimeException(
            "No se encontró $name. Instala postgresql-client"
            . ($serverMajor !== null ? "-$serverMajor" : '')
            . ' o usa los scripts backup.sh / restore.sh del stack Docker.'
        );
    }

    private static function clientMajorVersion(string $binary): int
    {
        $out = [];
        exec(escapeshellarg($binary) . ' --version 2>/dev/null', $out);
        if (preg_match('/PostgreSQL\) (\d+)/', $out[0] ?? '', $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function runShell(string $command): void
    {
        $env = getenv();
        if (!is_array($env)) {
            $env = [];
        }
        $env['PGPASSWORD'] = $this->password;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(['sh', '-c', $command], $descriptors, $pipes, null, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('No se pudo ejecutar el comando de restauración');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $detail = trim($stderr !== false && $stderr !== '' ? $stderr : (string) $stdout);
            $msg = 'Restauración filtrada falló (código ' . $exitCode . ')';
            if ($detail !== '') {
                $msg .= ': ' . $detail;
            }
            throw new RuntimeException($msg);
        }
    }
}
