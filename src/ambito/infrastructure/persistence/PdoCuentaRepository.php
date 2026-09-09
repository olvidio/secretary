<?php

declare(strict_types=1);

namespace src\ambito\infrastructure\persistence;

use PDO;
use RuntimeException;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Cuenta;

final class PdoCuentaRepository implements CuentaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listarDeCentro(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM cuentas WHERE centro_id = :c AND libro IN (\'P\', \'G\')
             ORDER BY libro, orden, codigo'
        );
        $st->execute([':c' => $centroId]);

        return array_map($this->hydrate(...), $st->fetchAll());
    }

    public function buscar(int $centroId, ?int $personaId, string $libro, string $codigo): ?Cuenta
    {
        $sql = 'SELECT * FROM cuentas WHERE centro_id = :c AND libro = :lib AND codigo = :cod AND '
            . ($personaId === null ? 'persona_id IS NULL' : 'persona_id = :pid');
        $st = $this->pdo->prepare($sql);
        $params = [':c' => $centroId, ':lib' => $libro, ':cod' => $codigo];
        if ($personaId !== null) {
            $params[':pid'] = $personaId;
        }
        $st->execute($params);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function imputablesDe(int $centroId, string $libro): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM cuentas WHERE centro_id = :c AND libro = :lib AND imputable = TRUE
             AND persona_id IS NULL ORDER BY orden, codigo'
        );
        $st->execute([':c' => $centroId, ':lib' => $libro]);

        return array_map($this->hydrate(...), $st->fetchAll());
    }

    public function listarDePersona(int $centroId, int $personaId, string $libro): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM cuentas WHERE centro_id = :c AND persona_id = :p AND libro = :lib AND activo = TRUE
             ORDER BY orden, codigo'
        );
        $st->execute([':c' => $centroId, ':p' => $personaId, ':lib' => $libro]);

        return array_map($this->hydrate(...), $st->fetchAll());
    }

    public function tesoreriaDePersona(int $centroId, int $personaId, string $libro, string $codigoMaestro): ?Cuenta
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM cuentas WHERE centro_id = :c AND persona_id = :p AND libro = :lib
             AND tipo = 'tesoreria' AND codigo_maestro = :m AND activo = TRUE
             ORDER BY orden, codigo LIMIT 1"
        );
        $st->execute([':c' => $centroId, ':p' => $personaId, ':lib' => $libro, ':m' => $codigoMaestro]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function tesoreria(int $centroId, string $libro, string $codigoMaestro): ?Cuenta
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM cuentas WHERE centro_id = :c AND libro = :lib
             AND tipo = 'tesoreria' AND codigo_maestro = :m AND persona_id IS NULL AND activo = TRUE
             ORDER BY orden, codigo LIMIT 1"
        );
        $st->execute([':c' => $centroId, ':lib' => $libro, ':m' => $codigoMaestro]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function tesoreriaDeFisica(int $centroId, string $libro, int $cuentaFisicaId): ?Cuenta
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM cuentas WHERE centro_id = :c AND libro = :lib
             AND tipo = 'tesoreria' AND cuenta_fisica_id = :f AND activo = TRUE LIMIT 1"
        );
        $st->execute([':c' => $centroId, ':lib' => $libro, ':f' => $cuentaFisicaId]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function listarTesoreria(int $centroId, ?string $libro = null): array
    {
        $sql = "SELECT * FROM cuentas WHERE centro_id = :c AND tipo = 'tesoreria'
                AND persona_id IS NULL AND activo = TRUE";
        $params = [':c' => $centroId];
        if ($libro !== null) {
            $sql .= ' AND libro = :lib';
            $params[':lib'] = $libro;
        }
        $sql .= ' ORDER BY libro, orden, codigo';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return array_map($this->hydrate(...), $st->fetchAll());
    }

    public function puenteEntreLibros(int $centroId, string $libro): ?Cuenta
    {
        return $this->buscar($centroId, null, $libro, 'PUENTE.LIBROS');
    }

    public function puentePeriodificacion(int $centroId, string $libro): ?Cuenta
    {
        return $this->buscar($centroId, null, $libro, 'PUENTE.PERIODIFICACION');
    }

    public function desactivarPorCuentaFisicaId(int $cuentaFisicaId): void
    {
        $st = $this->pdo->prepare('UPDATE cuentas SET activo = FALSE WHERE cuenta_fisica_id = :f');
        $st->execute([':f' => $cuentaFisicaId]);
    }

    public function personalDe(int $centroId, int $personaId): ?Cuenta
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM cuentas WHERE centro_id = :c AND persona_id = :p
             AND libro = 'P' AND tipo = 'personal' LIMIT 1"
        );
        $st->execute([':c' => $centroId, ':p' => $personaId]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function deudoresVivienda(int $centroId): ?Cuenta
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM cuentas WHERE centro_id = :c AND libro = 'G'
             AND codigo = 'DEUDORES.VIV' AND persona_id IS NULL LIMIT 1"
        );
        $st->execute([':c' => $centroId]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function guardar(Cuenta $cuenta): Cuenta
    {
        if ($cuenta->id === null) {
            $st = $this->pdo->prepare(
                'INSERT INTO cuentas (centro_id, persona_id, cuenta_fisica_id, padre_id, libro, codigo,
                    nombre, descripcion, tipo, naturaleza, codigo_maestro, imputable, orden, activo)
                 VALUES (:centro, :persona, :fisica, :padre, :libro, :codigo,
                    :nombre, :descripcion, :tipo, :naturaleza, :maestro, :imputable, :orden, :activo)
                 RETURNING id'
            );
            $st->execute($this->params($cuenta));
            $id = (int) $st->fetchColumn();
        } else {
            $st = $this->pdo->prepare(
                'UPDATE cuentas SET cuenta_fisica_id = :fisica, padre_id = :padre, nombre = :nombre,
                    descripcion = :descripcion, tipo = :tipo, naturaleza = :naturaleza,
                    codigo_maestro = :maestro, imputable = :imputable, orden = :orden, activo = :activo
                 WHERE id = :id'
            );
            $params = $this->params($cuenta);
            unset($params[':centro'], $params[':persona'], $params[':libro'], $params[':codigo']);
            $params[':id'] = $cuenta->id;
            $st->execute($params);
            $id = $cuenta->id;
        }
        $st = $this->pdo->prepare('SELECT * FROM cuentas WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Cuenta no encontrada tras guardar');
        }

        return $this->hydrate($row);
    }

    /** @return array<string, mixed> */
    private function params(Cuenta $c): array
    {
        return [
            ':centro' => $c->centroId,
            ':persona' => $c->personaId,
            ':fisica' => $c->cuentaFisicaId,
            ':padre' => $c->padreId,
            ':libro' => $c->libro,
            ':codigo' => $c->codigo,
            ':nombre' => $c->nombre,
            ':descripcion' => $c->descripcion,
            ':tipo' => $c->tipo,
            ':naturaleza' => $c->naturaleza,
            ':maestro' => $c->codigoMaestro,
            ':imputable' => (int) $c->imputable,
            ':orden' => $c->orden,
            ':activo' => (int) $c->activo,
        ];
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Cuenta
    {
        return new Cuenta(
            (int) $row['id'],
            (int) $row['centro_id'],
            isset($row['persona_id']) ? (int) $row['persona_id'] : null,
            isset($row['cuenta_fisica_id']) ? (int) $row['cuenta_fisica_id'] : null,
            isset($row['padre_id']) ? (int) $row['padre_id'] : null,
            (string) $row['libro'],
            (string) $row['codigo'],
            (string) $row['nombre'],
            (string) ($row['descripcion'] ?? ''),
            (string) $row['tipo'],
            (string) $row['naturaleza'],
            (string) $row['codigo_maestro'],
            (bool) $row['imputable'],
            (int) $row['orden'],
            (bool) $row['activo'],
        );
    }
}
