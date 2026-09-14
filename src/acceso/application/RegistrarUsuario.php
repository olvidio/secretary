<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\ambito\application\AsegurarCuentaCorrientePersona;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\entity\Centro;
use src\personal\application\AsegurarPlanPersonal;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;

/**
 * Alta pública desde el login: identidad de persona (nivel 1) en un centro.
 * El libro personal y la fila de Nombres se crean en ese centro.
 */
final class RegistrarUsuario
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly PersonaRepository $personas,
        private readonly CentroRepository $centros,
        private readonly AsegurarPlanPersonal $planPersonal,
        private readonly AsegurarCuentaCorrientePersona $cuentaCorriente,
    ) {
    }

    /**
     * @return array{identidad: Identidad, persona_id: int}
     */
    public function ejecutar(
        string $alias,
        string $email,
        string $password,
        string $passwordConfirm,
        string $nombre = '',
        ?int $centroId = null,
    ): array {
        $alias = strtolower(trim($alias));
        $email = strtolower(trim($email));
        $password = trim($password);
        $passwordConfirm = trim($passwordConfirm);
        $nombre = trim($nombre);
        if ($alias === '' || $email === '') {
            throw new InvalidArgumentException('Usuario y correo son obligatorios');
        }
        if (preg_match('/^[a-z][a-z0-9._-]{1,31}$/', $alias) !== 1) {
            throw new InvalidArgumentException(
                'El usuario debe empezar por letra y tener 2-32 caracteres (letras, números, punto, guion)'
            );
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('El correo no es válido');
        }
        if (strlen($password) < 6) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 6 caracteres');
        }
        if ($password !== $passwordConfirm) {
            throw new InvalidArgumentException('Las contraseñas no coinciden');
        }
        if ($this->identidades->porEmailOAlias($alias) !== null) {
            throw new InvalidArgumentException('Ese usuario ya existe');
        }
        if ($this->identidades->porEmailOAlias($email) !== null) {
            throw new InvalidArgumentException('Ese correo ya tiene una cuenta');
        }
        if ($this->personas->porEmail($email) !== null) {
            throw new InvalidArgumentException('Ese correo ya está asignado a un nombre');
        }

        $centro = $this->resolverCentro($centroId);
        if ($nombre === '') {
            $nombre = $alias;
        }
        $aporta = $centro->tipoCierre === 'vivienda';
        $persona = $this->personas->guardar(new Persona(
            null,
            $nombre,
            '',
            $this->inicialesLibres($alias, $centro->id ?? 0),
            null,
            null,
            null,
            null,
            null,
            0,
            $centro->id,
            true,
            $email,
            $aporta,
        ));
        if ($persona->id === null || $persona->centroId === null) {
            throw new InvalidArgumentException('No se pudo crear la persona');
        }

        $creada = $this->identidades->guardar(new Identidad(
            null,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $nombre,
            true,
            0,
            null,
            null,
            $alias,
        ));
        if ($creada->id === null) {
            throw new InvalidArgumentException('No se pudo crear la cuenta');
        }
        $this->identidades->vincularPersona($creada->id, $persona->id);
        $this->cuentaCorriente->ejecutar($persona);
        $this->planPersonal->ejecutar($persona->centroId, $persona->id);

        return ['identidad' => $creada, 'persona_id' => $persona->id];
    }

    private function resolverCentro(?int $centroId): Centro
    {
        $listados = array_values(array_filter(
            $this->centros->listar(),
            static fn (Centro $c): bool => $c->id !== null && $c->activo,
        ));
        if ($listados === []) {
            throw new InvalidArgumentException(
                'Todavía no hay ningún centro. Pida a un secretario que lo cree.'
            );
        }
        if ($centroId !== null && $centroId > 0) {
            foreach ($listados as $c) {
                if ($c->id === $centroId) {
                    return $c;
                }
            }
            throw new InvalidArgumentException('Ese centro no existe');
        }
        if (count($listados) === 1) {
            return $listados[0];
        }
        throw new InvalidArgumentException('Indique el centro');
    }

    private function inicialesLibres(string $alias, int $centroId): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', $alias) ?? '';
        $base = substr($base, 0, 6);
        if ($base === '') {
            $base = 'usr';
        }
        $candidato = $base;
        $n = 1;
        while ($this->personas->porInicialesDeCentro($centroId, $candidato) !== null) {
            $suf = (string) $n;
            $candidato = substr($base, 0, max(1, 6 - strlen($suf))) . $suf;
            $n++;
            if ($n > 99) {
                throw new InvalidArgumentException('No se pudieron generar iniciales únicas');
            }
        }

        return $candidato;
    }
}
