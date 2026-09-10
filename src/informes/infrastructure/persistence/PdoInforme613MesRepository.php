<?php

declare(strict_types=1);

namespace src\informes\infrastructure\persistence;

use DateTimeImmutable;
use PDO;
use src\informes\domain\contracts\Informe613MesRepository;
use src\informes\domain\entity\Informe613Mes;
use src\shared\infrastructure\persistence\ConverterDate;

final class PdoInforme613MesRepository implements Informe613MesRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function buscar(int $ejercicioId, DateTimeImmutable $fechaCierre, string $cuenta): ?Informe613Mes
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM informes_613_mes
             WHERE ejercicio_id = :ej AND fecha_cierre = :fc AND cuenta = :c'
        );
        $st->execute([
            ':ej' => $ejercicioId,
            ':fc' => (new ConverterDate('date', $fechaCierre))->toPg(),
            ':c' => strtoupper($cuenta),
        ]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function guardar(Informe613Mes $informe): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO informes_613_mes (
                ejercicio_id, fecha_cierre, cuenta,
                observaciones, saldo_cc_personales,
                media_cocina_mes, media_cocina_acum,
                dinero_arqueo_caja, dinero_arqueo_banco,
                enviado, enviado_en, updated_at
             ) VALUES (
                :ej, :fc, :c,
                :obs, :scc,
                :mcm, :mca,
                :dac, :dab,
                :env, :env_en, :upd
             )
             ON CONFLICT (ejercicio_id, fecha_cierre, cuenta) DO UPDATE SET
                observaciones = EXCLUDED.observaciones,
                saldo_cc_personales = EXCLUDED.saldo_cc_personales,
                media_cocina_mes = EXCLUDED.media_cocina_mes,
                media_cocina_acum = EXCLUDED.media_cocina_acum,
                dinero_arqueo_caja = EXCLUDED.dinero_arqueo_caja,
                dinero_arqueo_banco = EXCLUDED.dinero_arqueo_banco,
                enviado = EXCLUDED.enviado,
                enviado_en = EXCLUDED.enviado_en,
                updated_at = EXCLUDED.updated_at'
        );
        $st->execute([
            ':ej' => $informe->ejercicioId,
            ':fc' => (new ConverterDate('date', $informe->fechaCierre))->toPg(),
            ':c' => strtoupper($informe->cuenta),
            ':obs' => $informe->observaciones,
            ':scc' => $informe->saldoCcPersonales,
            ':mcm' => $informe->mediaCocinaMes,
            ':mca' => $informe->mediaCocinaAcum,
            ':dac' => $informe->dineroArqueoCaja,
            ':dab' => $informe->dineroArqueoBanco,
            ':env' => $informe->enviado ? 'true' : 'false',
            ':env_en' => $informe->enviadoEn !== null
                ? $informe->enviadoEn->format('c') : null,
            ':upd' => date('c'),
        ]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Informe613Mes
    {
        $fc = (new ConverterDate('date', $row['fecha_cierre']))->fromPg();
        $enviadoEn = null;
        if (isset($row['enviado_en']) && $row['enviado_en'] !== null && $row['enviado_en'] !== '') {
            $enviadoEn = new DateTimeImmutable((string) $row['enviado_en']);
        }

        return new Informe613Mes(
            (int) $row['ejercicio_id'],
            $fc,
            (string) $row['cuenta'],
            $row['observaciones'] !== null ? (string) $row['observaciones'] : null,
            $row['saldo_cc_personales'] !== null ? (string) $row['saldo_cc_personales'] : null,
            $row['media_cocina_mes'] !== null ? (string) $row['media_cocina_mes'] : null,
            $row['media_cocina_acum'] !== null ? (string) $row['media_cocina_acum'] : null,
            isset($row['dinero_arqueo_caja']) && $row['dinero_arqueo_caja'] !== null
                ? (string) $row['dinero_arqueo_caja'] : null,
            isset($row['dinero_arqueo_banco']) && $row['dinero_arqueo_banco'] !== null
                ? (string) $row['dinero_arqueo_banco'] : null,
            filter_var($row['enviado'] ?? false, FILTER_VALIDATE_BOOLEAN),
            $enviadoEn,
        );
    }
}
