<?php

declare(strict_types=1);

namespace src\acceso\application;

use src\acceso\domain\contracts\IdentidadRepository;

final class ListarAmbitosIdentidad
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    /**
     * @return array{
     *     opciones: list<array{valor: string, etiqueta: string}>,
     *     puede_elegir_ambito: bool
     * }
     */
    public function ejecutar(int $identidadId): array
    {
        $centros = [];
        foreach ($this->identidades->centrosDe($identidadId) as $v) {
            $centros[] = [
                'centro_id' => $v->centroId,
                'codigo' => $v->codigo,
                'nombre' => $v->nombre,
                'rol' => $v->rol,
            ];
        }
        $personas = $this->identidades->personasDe($identidadId);
        $puedeElegirAmbito = $centros !== [] && $personas !== [];

        $opciones = [];
        if ($personas !== []) {
            $opciones[] = [
                'valor' => 'persona',
                'etiqueta' => _('Mis cuentas (individual)'),
            ];
        }
        foreach ($centros as $c) {
            $nombre = (string) ($c['nombre'] ?? $c['codigo'] ?? $c['centro_id']);
            $opciones[] = [
                'valor' => 'centro:' . $c['centro_id'],
                'etiqueta' => $nombre,
            ];
        }

        return [
            'opciones' => $opciones,
            'puede_elegir_ambito' => $puedeElegirAmbito,
        ];
    }

    public function valorActual(string $nivel, ?int $centroId): ?string
    {
        if ($nivel === 'persona') {
            return 'persona';
        }
        if ($nivel === 'centro' && $centroId !== null && $centroId > 0) {
            return 'centro:' . $centroId;
        }

        return null;
    }

    /**
     * @param list<array{centro_id:int, codigo:string, nombre:string, rol:string}> $centros
     */
    public function rutaTrasLogin(int $identidadId, string $nivel, array $centros, ?int $centroId): ?string
    {
        if ($nivel !== 'centro') {
            return null;
        }
        $info = $this->ejecutar($identidadId);
        if ($centroId !== null && $centroId > 0) {
            return null;
        }
        if ($info['puede_elegir_ambito']) {
            return '/elegir-ambito';
        }
        if (count($centros) > 1) {
            return '/elegir-centro';
        }

        return null;
    }

    public function rutaSiFaltaCentro(int $identidadId): string
    {
        $info = $this->ejecutar($identidadId);

        return $info['puede_elegir_ambito'] ? '/elegir-ambito' : '/elegir-centro';
    }
}
