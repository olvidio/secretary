<?php

declare(strict_types=1);

namespace src\acceso\application;

use src\acceso\domain\contracts\AccesoRutaRepository;
use src\acceso\domain\contracts\IdentidadRepository;

final class DecisionAcceso
{
    public function __construct(
        public readonly bool $permitido,
        public readonly int $status = 200,
        public readonly ?string $redirect = null,
        public readonly ?string $error = null,
    ) {
    }
}

final class AutorizarPeticion
{
    public function __construct(
        private readonly AccesoRutaRepository $rutas,
        private readonly IdentidadRepository $identidades,
    ) {
    }

    public function ejecutar(
        string $clase,
        string $metodoPhp,
        string $httpMethod,
        bool $csrfValido,
        ?int $identidadId,
        ?int $pendingId,
        ?int $centroId,
        string $nivel,
        bool $esApi,
        ?int $personaId = null,
    ): DecisionAcceso {
        $ambito = $this->rutas->ambitoDe($clase, $metodoPhp);
        if ($ambito === null) {
            return $this->denegar($esApi, 'No autorizado', 401, '/login');
        }
        $mutacion = in_array($httpMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if ($mutacion && !$csrfValido) {
            return $this->denegar($esApi, 'Token CSRF inválido', 403, null);
        }
        if ($ambito === 'publico') {
            return new DecisionAcceso(true);
        }

        $hayPendiente = $pendingId !== null;
        $haySesion = $identidadId !== null;
        if ($ambito === 'pendiente') {
            if (!$hayPendiente && !$haySesion) {
                return $this->denegar($esApi, 'No autenticado', 401, '/login');
            }

            return new DecisionAcceso(true);
        }
        if (!$haySesion) {
            if ($hayPendiente && $ambito === 'centro') {
                $totp = $this->identidades->totpConfirmado($pendingId);
                $destino = $totp ? '/totp-verificar' : '/totp-activar';

                return $this->denegar($esApi, 'Falta el segundo factor', 401, $destino);
            }

            return $this->denegar($esApi, 'No autenticado', 401, '/login');
        }
        if ($ambito === 'autenticado') {
            return new DecisionAcceso(true);
        }
        if ($ambito === 'centro') {
            if ($nivel !== 'centro') {
                return $this->denegar($esApi, 'Esta área es del centro', 403, '/yo');
            }
            if (!$this->identidades->totpConfirmado($identidadId)) {
                return $this->denegar($esApi, 'Debe confirmar el segundo factor', 401, '/totp-activar');
            }
            if ($centroId === null) {
                return $this->denegar($esApi, 'Seleccione un centro', 401, '/elegir-centro');
            }

            return new DecisionAcceso(true);
        }
        if ($ambito === 'persona') {
            if ($nivel !== 'persona') {
                return $this->denegar($esApi, 'Esta área es personal', 403, '/');
            }
            if ($personaId === null) {
                return $this->denegar($esApi, 'Sesión de persona incompleta', 401, '/login');
            }

            return new DecisionAcceso(true);
        }

        return $this->denegar($esApi, 'No autorizado', 401, '/login');
    }

    private function denegar(bool $esApi, string $error, int $status, ?string $redirect): DecisionAcceso
    {
        if ($esApi) {
            return new DecisionAcceso(false, $status, null, $error);
        }

        return new DecisionAcceso(false, $redirect !== null ? 302 : $status, $redirect, $error);
    }
}
