<?php

declare(strict_types=1);

namespace src\personas\application;

use InvalidArgumentException;
use src\acceso\application\VincularEmailPersona;
use src\ambito\application\AsegurarCuentaCorrientePersona;
use src\ambito\application\ResolverAmbitoActual;
use src\personal\application\AsegurarPlanPersonal;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;
use src\shared\domain\value_objects\Dinero;

final class GuardarPersona
{
    public function __construct(
        private readonly PersonaRepository $repo,
        private readonly ResolverAmbitoActual $ambito,
        private readonly VincularEmailPersona $vincularEmail,
        private readonly AsegurarCuentaCorrientePersona $cuentaCorriente,
        private readonly AsegurarPlanPersonal $planPersonal,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{persona: Persona, password_inicial: ?string}
     */
    public function ejecutar(array $datos): array
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $apellidos = trim((string) ($datos['apellidos'] ?? ''));
        $iniciales = strtolower(trim((string) ($datos['iniciales'] ?? '')));
        if ($nombre === '' || $iniciales === '') {
            throw new InvalidArgumentException('Nombre e iniciales son obligatorios');
        }
        if (str_contains($iniciales, ' ')) {
            throw new InvalidArgumentException('Las iniciales no deben tener espacios');
        }
        if (strlen($iniciales) > 6) {
            $iniciales = substr($iniciales, 0, 6);
        }
        $fijo = null;
        if (!empty($datos['importe_vivienda_fijo'])) {
            $fijo = Dinero::fromInput((string) $datos['importe_vivienda_fijo']);
        }
        $id = isset($datos['id']) && $datos['id'] !== '' ? (int) $datos['id'] : null;
        $contexto = $this->ambito->ejecutar();
        $existente = $id !== null ? $this->repo->porId($id) : null;
        if ($id !== null && $existente === null) {
            throw new InvalidArgumentException('Persona no encontrada en este centro');
        }
        if (
            $existente !== null
            && $existente->centroId !== null
            && $existente->centroId !== $contexto->centroId
        ) {
            throw new InvalidArgumentException('Persona no encontrada en este centro');
        }
        $otra = $this->repo->porInicialesDeCentro($contexto->centroId, $iniciales);
        if ($otra !== null && $otra->id !== $id) {
            throw new InvalidArgumentException('Ya hay un nombre con esas iniciales en este centro');
        }
        $orden = (int) ($datos['orden'] ?? ($existente !== null ? $existente->orden : 0));
        $centroPersona = $existente !== null && $existente->centroId !== null
            ? $existente->centroId
            : $contexto->centroId;
        $activo = $existente !== null ? $existente->activo : true;
        $emailPersona = $existente !== null ? $existente->email : null;
        $persona = new Persona(
            $id,
            $nombre,
            $apellidos,
            $iniciales,
            self::mes($datos['mes_exento_inicio'] ?? null),
            self::mes($datos['mes_exento_fin'] ?? null),
            self::mes($datos['mes_exento2_inicio'] ?? null),
            self::mes($datos['mes_exento2_fin'] ?? null),
            $fijo,
            $orden,
            $centroPersona,
            $activo,
            $emailPersona,
        );
        $guardada = $this->repo->guardar($persona);
        $this->cuentaCorriente->ejecutar($guardada);
        if ($guardada->id !== null && $guardada->centroId !== null) {
            $this->planPersonal->ejecutar($guardada->centroId, $guardada->id);
        }
        $passwordInicial = null;
        if (array_key_exists('email', $datos)) {
            $passwordInicial = $this->vincularEmail->ejecutar($guardada, (string) $datos['email']);
            $releida = $this->repo->porId((int) $guardada->id);
            if ($releida !== null) {
                $guardada = $releida;
            }
        }

        return ['persona' => $guardada, 'password_inicial' => $passwordInicial];
    }

    private static function mes(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;
        if ($n < 1 || $n > 12) {
            throw new InvalidArgumentException('El mes debe estar entre 1 y 12');
        }

        return $n;
    }
}
