<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use src\acceso\domain\contracts\CifradorSecretos;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\services\TotpRfc6238;

final class VerificarSegundoFactor
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly CifradorSecretos $cifrador,
        private readonly string $pimiento,
    ) {
    }

    public function ejecutar(int $identidadId, string $codigo, ?DateTimeImmutable $ahora = null): ResultadoLogin
    {
        $ahora ??= new DateTimeImmutable();
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null || $identidad->id === null || !$identidad->activo) {
            return new ResultadoLogin('fallo', 'Código incorrecto');
        }
        if ($identidad->estaBloqueada($ahora)) {
            return new ResultadoLogin('fallo', 'Cuenta temporalmente bloqueada. Pruebe más tarde.');
        }
        $codigo = strtoupper(trim($codigo));
        $ok = false;
        if (preg_match('/^\d{6}$/', str_replace(' ', '', $codigo)) === 1) {
            $cifrado = $this->identidades->totpSecretoCifrado($identidadId);
            if ($cifrado !== null) {
                $ok = TotpRfc6238::verificar($this->cifrador->descifrar($cifrado), $codigo);
            }
        } else {
            $normalizado = preg_replace('/[^A-Z0-9]/', '', $codigo) ?? '';
            $formateado = strlen($normalizado) === 8
                ? substr($normalizado, 0, 4) . '-' . substr($normalizado, 4, 4)
                : $codigo;
            $pimiento = $this->pimiento;
            $objetivo = hash('sha256', strtoupper($formateado) . '|' . $pimiento);
            foreach ($this->identidades->recoveryPendientes($identidadId) as $fila) {
                if (hash_equals($fila['hash'], $objetivo)) {
                    $this->identidades->marcarRecoveryUsado($fila['id'], $ahora);
                    $ok = true;
                    break;
                }
            }
        }
        if (!$ok) {
            $this->identidades->registrarFallo($identidad, $ahora);

            return new ResultadoLogin('fallo', 'Código incorrecto');
        }
        $this->identidades->registrarExito($identidad, $ahora);
        $centros = [];
        foreach ($this->identidades->centrosDe($identidad->id) as $v) {
            $centros[] = [
                'centro_id' => $v->centroId,
                'codigo' => $v->codigo,
                'nombre' => $v->nombre,
                'rol' => $v->rol,
            ];
        }
        $nivel = $centros !== [] ? 'centro' : 'persona';
        $personaId = null;
        if ($nivel === 'persona') {
            $personas = $this->identidades->personasDe($identidad->id);
            $personaId = $personas[0] ?? null;
        }

        return new ResultadoLogin(
            'autenticado',
            '',
            $identidad->id,
            $identidad->nombre,
            $identidad->email,
            $nivel,
            $centros,
            $personaId,
        );
    }
}
