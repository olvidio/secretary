<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use PDO;
use src\acceso\application\AsegurarLibroPersonalIdentidad;
use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\domain\contracts\CentroRepository;
use src\personal\infrastructure\persistence\AlmacenCopiasPersonal;
use src\personal\infrastructure\persistence\RutasCopiasPersonal;
use src\personas\domain\contracts\PersonaRepository;

final class ResumenEliminacionCuentaPersonal
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly PersonaRepository $personas,
        private readonly CentroRepository $centros,
        private readonly PDO $pdo,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $identidadId): array
    {
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null) {
            throw new InvalidArgumentException(_("Usuario no encontrado"));
        }
        if ($identidad->esAdmin) {
            throw new InvalidArgumentException(_("No se puede eliminar al administrador de plataforma"));
        }

        $esPersonal = $this->identidades->esCuentaPersonal($identidadId);
        if (!$esPersonal) {
            return [
                'id' => $identidadId,
                'alias' => $identidad->alias,
                'email' => $identidad->email,
                'nombre' => $identidad->nombre,
                'es_personal' => false,
                'puede_borrar' => false,
                'motivo_bloqueo' => _(
                    'Esta cuenta es de secretario de centro. Su baja se definirá aparte; por ahora no se puede eliminar desde aquí.'
                ),
                'datos' => null,
            ];
        }

        $datos = $this->contarDatos($identidadId);

        return [
            'id' => $identidadId,
            'alias' => $identidad->alias,
            'email' => $identidad->email,
            'nombre' => $identidad->nombre,
            'es_personal' => true,
            'puede_borrar' => true,
            'motivo_bloqueo' => null,
            'datos' => $datos,
            'texto_datos' => $this->textoDatos($datos),
            'texto_conservacion' => _(
                'Se conservarán en el centro las remesas ya enviadas o resueltas y el registro legal de consentimientos (sin correo activo).'
            ),
        ];
    }

    /** @return array<string, int|list<array{centro: string, persona: string}>> */
    private function contarDatos(int $identidadId): array
    {
        $movimientos = 0;
        $remesasConservadas = 0;
        $remesasBorrar = 0;
        $copias = 0;
        $vinculos = [];
        $tieneAmbitoPropio = false;

        foreach ($this->identidades->personasDe($identidadId) as $personaId) {
            $persona = $this->personas->porId($personaId);
            if ($persona === null || $persona->centroId === null) {
                continue;
            }
            $centro = $this->centros->porId($persona->centroId);
            if ($centro === null) {
                continue;
            }

            $movimientos += $this->contar(
                'SELECT COUNT(*) FROM asientos WHERE libro = \'X\' AND persona_id = :p AND anulado_at IS NULL',
                [':p' => $personaId],
            );
            $remesasConservadas += $this->contar(
                'SELECT COUNT(*) FROM remesas WHERE persona_id = :p AND estado <> \'borrador\'',
                [':p' => $personaId],
            );
            $remesasBorrar += $this->contar(
                'SELECT COUNT(*) FROM remesas WHERE persona_id = :p AND estado = \'borrador\'',
                [':p' => $personaId],
            );

            $almacen = new AlmacenCopiasPersonal(
                RutasCopiasPersonal::directorio(),
                $personaId,
                $persona->iniciales,
            );
            $copias += count($almacen->listar());

            if ($centro->tipo === AsegurarLibroPersonalIdentidad::TIPO_CENTRO) {
                $tieneAmbitoPropio = true;
            } else {
                $vinculos[] = [
                    'centro' => $centro->nombre,
                    'persona' => trim($persona->nombre . ' ' . ($persona->apellidos ?? '')),
                ];
            }
        }

        $consentimientos = $this->contar(
            'SELECT COUNT(*) FROM aceptaciones_legales WHERE identidad_id = :id',
            [':id' => $identidadId],
        );
        $solicitudesPendientes = $this->contar(
            'SELECT COUNT(*) FROM solicitudes_vinculo_centro WHERE identidad_id = :id AND estado = \'pendiente\'',
            [':id' => $identidadId],
        );

        return [
            'movimientos' => $movimientos,
            'remesas_conservadas' => $remesasConservadas,
            'remesas_borrar' => $remesasBorrar,
            'copias' => $copias,
            'vinculos_centro' => $vinculos,
            'ambito_propio' => $tieneAmbitoPropio,
            'consentimientos' => $consentimientos,
            'solicitudes_pendientes' => $solicitudesPendientes,
        ];
    }

    /** @param array<string, int|bool|list<array{centro: string, persona: string}>> $datos */
    private function textoDatos(array $datos): string
    {
        $lineas = [];
        if ($datos['movimientos'] > 0) {
            $n = (int) $datos['movimientos'];
            $lineas[] = $n === 1
                ? _('1 movimiento en el libro personal.')
                : sprintf(_('%d movimientos en el libro personal.'), $n);
        }
        if ($datos['copias'] > 0) {
            $n = (int) $datos['copias'];
            $lineas[] = $n === 1
                ? _('1 copia de seguridad personal.')
                : sprintf(_('%d copias de seguridad personal.'), $n);
        }
        if ($datos['remesas_borrar'] > 0) {
            $n = (int) $datos['remesas_borrar'];
            $lineas[] = $n === 1
                ? _('1 remesa en borrador (se eliminará).')
                : sprintf(_('%d remesas en borrador (se eliminarán).'), $n);
        }
        if ($datos['remesas_conservadas'] > 0) {
            $n = (int) $datos['remesas_conservadas'];
            $lineas[] = $n === 1
                ? _('1 remesa enviada al centro (se conserva).')
                : sprintf(_('%d remesas enviadas al centro (se conservan).'), $n);
        }
        /** @var list<array{centro: string, persona: string}> $vinculos */
        $vinculos = $datos['vinculos_centro'];
        if ($vinculos !== []) {
            $nombres = array_map(
                static fn (array $v) => $v['persona'] . ' (' . $v['centro'] . ')',
                $vinculos,
            );
            $lineas[] = _('Se desvinculará del centro (el nombre y su histórico contable siguen): ')
                . implode(', ', $nombres);
        }
        if ($datos['ambito_propio']) {
            $lineas[] = _('Se borrará por completo su ámbito personal (centro tipo p).');
        }
        if ($datos['solicitudes_pendientes'] > 0) {
            $n = (int) $datos['solicitudes_pendientes'];
            $lineas[] = $n === 1
                ? _('1 solicitud de acceso a centro pendiente.')
                : sprintf(_('%d solicitudes de acceso a centro pendientes.'), $n);
        }
        if ($lineas === []) {
            return _('No hay datos asociados aparte de la cuenta de acceso.');
        }

        return implode("\n", $lineas);
    }

    /** @param array<string, int|string> $params */
    private function contar(string $sql, array $params): int
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return (int) $st->fetchColumn();
    }
}
