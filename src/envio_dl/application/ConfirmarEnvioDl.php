<?php

declare(strict_types=1);

namespace src\envio_dl\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\application\CrearApunte;
use src\envio_dl\domain\contracts\EnvioDlRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\remesas\domain\contracts\RemesaRepository;
use src\shared\domain\value_objects\Dinero;

final class ConfirmarEnvioDl
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly EnvioDlRepository $envios,
        private readonly PersonaRepository $personas,
        private readonly CrearApunte $crearApunte,
        private readonly RemesaRepository $remesas,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $id): array
    {
        $ctx = $this->ambito->ejecutar();
        $envio = $this->envios->porId($id, $ctx->centroId);
        if ($envio === null) {
            throw new InvalidArgumentException('Propuesta no encontrada');
        }
        if ($envio['estado'] !== 'borrador') {
            throw new InvalidArgumentException('Solo se puede confirmar una propuesta en borrador');
        }
        $lineas = $envio['lineas'];
        if ($lineas === []) {
            throw new InvalidArgumentException('La propuesta no tiene líneas');
        }
        $fecha = new DateTimeImmutable('today');
        $fechaStr = $fecha->format('Y-m-d');
        $nombres = [];
        foreach ($this->personas->listarDeCentro($ctx->centroId) as $p) {
            if ($p->id !== null) {
                $nombres[$p->id] = $p;
            }
        }
        $this->remesas->enTransaccion(function () use ($lineas, $nombres, $fechaStr, $id): void {
            foreach ($lineas as $l) {
                $pid = (int) $l['persona_id'];
                $persona = $nombres[$pid] ?? null;
                if ($persona === null) {
                    continue;
                }
                $cents = (int) $l['importe_cents'];
                if ($cents <= 0) {
                    continue;
                }
                $this->crearApunte->ejecutar([
                    'fecha' => $fechaStr,
                    'cuenta' => 'P',
                    'origen' => 'C',
                    'iniciales' => $persona->iniciales,
                    'concepto_codigo' => '71',
                    'observaciones' => 'Envío DL ' . $fechaStr,
                    'cantidad' => Dinero::fromCents($cents)->formatEs(),
                ]);
            }
            $this->envios->marcarConfirmada($id);
        });
        $out = $this->envios->porId($id, $ctx->centroId);
        if ($out === null) {
            throw new InvalidArgumentException('No se pudo releer la propuesta');
        }
        $out['total_es'] = Dinero::fromCents((int) $out['importe_total_cents'])->formatEs();

        return $out;
    }
}
