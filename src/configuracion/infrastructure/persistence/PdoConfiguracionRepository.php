<?php

declare(strict_types=1);

namespace src\configuracion\infrastructure\persistence;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\configuracion\domain\entity\ConfiguracionCentro;
use src\shared\infrastructure\persistence\ConverterDate;

final class PdoConfiguracionRepository implements ConfiguracionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): ConfiguracionCentro
    {
        $row = $this->pdo->query('SELECT * FROM configuracion WHERE id = 1')->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Falta configuración. Ejecute composer db:install');
        }

        return $this->hydrate($row);
    }

    public function guardar(ConfiguracionCentro $config): void
    {
        $st = $this->pdo->prepare(
            'UPDATE configuracion SET
                centro = :centro, anio = :anio, modo_ejercicio = :modo,
                fecha_inicio = :ini, fecha_cierre = :cie, tipo_cierre = :tipo,
                num_residentes = :num, version = :ver,
                observaciones_613_p = :op, observaciones_613_g = :og,
                media_cocina_mes = :mcm, media_cocina_acum = :mca,
                saldo_cc_personales = :scc, updated_at = :upd
             WHERE id = 1'
        );
        $st->execute([
            ':centro' => $config->centro,
            ':anio' => $config->anio,
            ':modo' => $config->modoEjercicio,
            ':ini' => (new ConverterDate('date', $config->fechaInicio))->toPg(),
            ':cie' => (new ConverterDate('date', $config->fechaCierre))->toPg(),
            ':tipo' => $config->tipoCierre,
            ':num' => $config->numResidentes,
            ':ver' => $config->version,
            ':op' => $config->observaciones613P,
            ':og' => $config->observaciones613G,
            ':mcm' => $config->mediaCocinaMes,
            ':mca' => $config->mediaCocinaAcum,
            ':scc' => $config->saldoCcPersonales,
            ':upd' => date('c'),
        ]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ConfiguracionCentro
    {
        $ini = (new ConverterDate('date', $row['fecha_inicio']))->fromPg();
        $cie = (new ConverterDate('date', $row['fecha_cierre']))->fromPg();
        if ($ini === null || $cie === null) {
            throw new RuntimeException('Fechas de configuración inválidas');
        }

        return new ConfiguracionCentro(
            (string) $row['centro'],
            (int) $row['anio'],
            (string) $row['modo_ejercicio'],
            $ini,
            $cie,
            (string) $row['tipo_cierre'],
            $row['num_residentes'] !== null ? (int) $row['num_residentes'] : null,
            $row['version'] !== null ? (string) $row['version'] : null,
            $row['observaciones_613_p'] !== null ? (string) $row['observaciones_613_p'] : null,
            $row['observaciones_613_g'] !== null ? (string) $row['observaciones_613_g'] : null,
            $row['media_cocina_mes'] !== null ? (string) $row['media_cocina_mes'] : null,
            $row['media_cocina_acum'] !== null ? (string) $row['media_cocina_acum'] : null,
            $row['saldo_cc_personales'] !== null ? (string) $row['saldo_cc_personales'] : null,
        );
    }
}
