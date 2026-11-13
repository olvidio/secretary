<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\application\CrearCentro;
use src\ambito\domain\entity\Centro;
use src\ambito\domain\services\TipoEntidad;
use src\plan\domain\services\CatalogoPlanesContables;

/**
 * Una identidad ya dada de alta crea otra entidad (centro n, centro sg, asociación o fundación)
 * y recibe el mandato de administrador sobre ella (D15): no hace falta otro alias ni otro correo.
 */
final class CrearEntidadPropia
{
    public function __construct(
        private readonly CrearCentro $crearCentro,
        private readonly IdentidadRepository $identidades,
    ) {
    }

    /**
     * @return array{centro: Centro, totp_confirmado: bool}
     */
    public function ejecutar(int $identidadId, string $tipoCuenta, string $codigo, string $nombre): array
    {
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null || !$identidad->activo) {
            throw new InvalidArgumentException(_("Cuenta no válida"));
        }
        if ($identidad->esAdmin) {
            throw new InvalidArgumentException(_("El administrador de plataforma crea las entidades desde Administración"));
        }
        $codigo = trim($codigo);
        $nombre = trim($nombre);
        if ($codigo === '' || $nombre === '') {
            throw new InvalidArgumentException(_("La sigla y el nombre son obligatorios"));
        }
        [$tipo, $plan] = match (strtolower(trim($tipoCuenta))) {
            'centro' => [TipoEntidad::CENTRO_N, CatalogoPlanesContables::H16N],
            'centro-sg' => [TipoEntidad::CENTRO_SG, CatalogoPlanesContables::CENTRO_SG],
            'club' => [TipoEntidad::ASOCIACION, CatalogoPlanesContables::CLUB],
            'fundacion' => [TipoEntidad::FUNDACION, CatalogoPlanesContables::CLUB],
            default => throw new InvalidArgumentException(
                _("Indique el tipo de entidad: centro n, centro sg, asociación o fundación")
            ),
        };

        $anio = (int) (new DateTimeImmutable())->format('Y');
        $alta = $this->crearCentro->ejecutar([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'plan_contable' => $plan,
            'email' => $identidad->email,
            'fecha_inicio' => sprintf('%d-01-01', $anio),
            'fecha_fin' => sprintf('%d-12-31', $anio),
        ]);

        return [
            'centro' => $alta['centro'],
            'totp_confirmado' => $this->identidades->totpConfirmado($identidadId),
        ];
    }
}
