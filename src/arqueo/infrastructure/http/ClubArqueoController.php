<?php

declare(strict_types=1);

namespace src\arqueo\infrastructure\http;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\arqueo\application\GuardarArqueo;
use src\arqueo\domain\contracts\ArqueoRepository;
use src\arqueo\infrastructure\persistence\PdoArqueoCuadrado;
use src\plan\domain\services\CatalogoPlanesContables;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class ClubArqueoController
{
    public function __construct(
        private readonly PdoArqueoCuadrado $cuadrados,
        private readonly ResolverAmbitoActual $ambito,
        private readonly CentroRepository $centros,
        private readonly ArqueoRepository $arqueos,
        private readonly GuardarArqueo $guardar,
    ) {
    }

    public function estado(Request $request, array $vars = []): Response
    {
        try {
            $centroId = $this->centroClub();
            $fecha = $this->fecha((string) ($request->query('fecha') ?? date('Y-m-d')));

            return ContestarJson::ok($this->datos($centroId, $fecha));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    /** Guarda el recuento de billetes y monedas de una caja para ese día, cuadre o no. */
    public function recuento(Request $request, array $vars = []): Response
    {
        try {
            $centroId = $this->centroClub();
            $body = $request->json();
            $fecha = $this->fecha((string) ($body['fecha'] ?? ''));
            $fisicaId = (int) ($body['cuenta_fisica_id'] ?? 0);
            $desglose = is_array($body['desglose'] ?? null) ? $body['desglose'] : [];
            $esCaja = false;
            foreach ($this->cuadrados->tesoreria($centroId, $fecha) as $fila) {
                $esCaja = $esCaja || ($fila['id'] === $fisicaId && $fila['tipo'] === 'caja');
            }
            if (!$esCaja) {
                throw new InvalidArgumentException(_('Esa caja no es de esta associació'));
            }
            $this->guardar->ejecutar('C', $fecha, $desglose, $fisicaId);

            return ContestarJson::ok($this->datos($centroId, $fecha));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    /**
     * Estado del arqueo a una fecha: tesorería con su saldo (y el recuento guardado ese
     * día, en las cajas), periodos cuadrados y recuentos anteriores.
     *
     * @return array<string, mixed>
     */
    private function datos(int $centroId, string $fecha): array
    {
        $dia = new \DateTimeImmutable($fecha);
        $tesoreria = [];
        $recuentos = [];
        foreach ($this->cuadrados->tesoreria($centroId, $fecha) as $fila) {
            if ($fila['tipo'] === 'caja') {
                $fila['recuento'] = $this->arqueos->deFisicaEnFecha($fila['id'], $dia)?->toArray();
                foreach ($this->arqueos->historialDeFisica($fila['id']) as $arqueo) {
                    $f = $arqueo->fecha->format('Y-m-d');
                    $saldo = 0;
                    foreach ($this->cuadrados->tesoreria($centroId, $f) as $entonces) {
                        if ($entonces['id'] === $fila['id']) {
                            $saldo = $entonces['saldo_cents'];
                        }
                    }
                    $recuentos[] = [
                        'fecha' => $f,
                        'cuenta_fisica_id' => $fila['id'],
                        'nombre' => $fila['nombre'],
                        'contado_cents' => $arqueo->total->toCents(),
                        'saldo_cents' => $saldo,
                    ];
                }
            }
            $tesoreria[] = $fila;
        }
        usort($recuentos, static fn (array $a, array $b): int => [$b['fecha'], $a['nombre']] <=> [$a['fecha'], $b['nombre']]);

        return [
            'fecha' => $fecha,
            'tesoreria' => $tesoreria,
            'cuadrados' => $this->cuadrados->listar($centroId),
            'recuentos' => $recuentos,
        ];
    }

    public function cuadrar(Request $request, array $vars = []): Response
    {
        try {
            $centroId = $this->centroClub();
            $body = $request->json();
            $fecha = $this->fecha((string) ($body['fecha'] ?? ''));
            $contados = is_array($body['contados'] ?? null) ? $body['contados'] : [];
            $this->cuadrados->cuadrar($centroId, $fecha, $contados);

            return ContestarJson::ok([
                'cuadrado' => true,
                'cuadrados' => $this->cuadrados->listar($centroId),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    private function centroClub(): int
    {
        $centroId = $this->ambito->ejecutar()->centroId;
        $centro = $this->centros->porId($centroId);
        if ($centro === null || !CatalogoPlanesContables::esClub($centro->planContableCodigo)) {
            throw new InvalidArgumentException(_('El arqueo de periodo es de una associació'));
        }

        return $centroId;
    }

    private function fecha(string $fecha): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            throw new InvalidArgumentException(_('Fecha no válida'));
        }

        return $fecha;
    }
}
