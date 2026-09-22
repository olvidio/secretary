<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\legal\application\RegistrarAceptacion;
use src\legal\domain\services\CatalogoDocumentosLegales;
use src\legal\domain\value_objects\HuellaAceptacion;

final class ConfirmarEmailRegistro
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly PersonaRepository $personas,
        private readonly AplicarEmailIdentidad $aplicarEmail,
        private readonly RegistrarAceptacion $registrarAceptacion,
        private readonly CatalogoDocumentosLegales $documentos,
    ) {
    }

    public function ejecutar(string $token, ?DateTimeImmutable $ahora = null, ?HuellaAceptacion $huella = null): void
    {
        $ahora ??= new DateTimeImmutable();
        $token = trim($token);
        if ($token === '') {
            throw new InvalidArgumentException(_('Enlace de confirmación no válido'));
        }
        $datos = $this->identidades->porTokenVerificacionEmail($token);
        if ($datos === null) {
            throw new InvalidArgumentException(_('Enlace de confirmación no válido o ya utilizado'));
        }
        if ($datos['expira'] < $ahora) {
            throw new InvalidArgumentException(_('El enlace ha caducado. Regístrese de nuevo o pida otro correo.'));
        }
        $identidadId = $datos['identidad_id'];
        $pendiente = $this->identidades->emailPendienteDe($identidadId);
        if ($pendiente !== null) {
            $this->aplicarEmail->comprobarDisponible($identidadId, $pendiente);
            $nuevo = $this->identidades->confirmarCambioEmailPendiente($identidadId, $ahora);
            if ($nuevo === null) {
                throw new InvalidArgumentException(_('Enlace de confirmación no válido o ya utilizado'));
            }
            foreach ($this->identidades->personasDe($identidadId) as $personaId) {
                $this->personas->guardarEmail($personaId, $nuevo);
            }

            return;
        }
        if ($this->identidades->emailVerificado($identidadId)) {
            return;
        }
        $this->identidades->confirmarEmail($identidadId, $ahora);
        $identidad = $this->identidades->porId($identidadId);
        $idioma = 'es';
        if ($huella !== null) {
            $idioma = $huella->idioma === 'ca' ? 'ca' : 'es';
        }
        $base = $huella ?? new HuellaAceptacion(idioma: $idioma);
        $email = $base->email;
        $alias = $base->alias;
        if ($identidad !== null) {
            $email = $identidad->email;
            $alias = $identidad->alias;
        }
        $huellaFinal = new HuellaAceptacion(
            $base->ip,
            $base->userAgent,
            $idioma,
            $email,
            $alias,
            $base->centroId,
            $base->personaId,
            hash('sha256', $token),
            $base->extra,
        );
        $this->registrarAceptacion->ejecutar(
            $identidadId,
            'confirmacion_email',
            $this->documentos->textoCasillaRegistro($idioma),
            $huellaFinal,
            $ahora,
        );
    }
}
