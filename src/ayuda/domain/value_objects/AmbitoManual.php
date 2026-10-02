<?php

declare(strict_types=1);

namespace src\ayuda\domain\value_objects;

use InvalidArgumentException;

/**
 * Tipo de cuenta que consulta la ayuda. El manual se recorta a este ámbito
 * para no contestar con pantallas de otro tipo de centro.
 */
final class AmbitoManual
{
    public const CENTRO_N = 'centro-n';

    public const CENTRO_SG = 'centro-sg';

    public const ASOCIACION = 'asociacion';

    public const FUNDACION = 'fundacion';

    public const PERSONA = 'persona';

    /** @var list<string> */
    public const TODOS = [
        self::CENTRO_N,
        self::CENTRO_SG,
        self::ASOCIACION,
        self::FUNDACION,
        self::PERSONA,
    ];

    public function __construct(public readonly string $codigo)
    {
        if (!in_array($codigo, self::TODOS, true)) {
            throw new InvalidArgumentException('Ámbito de ayuda desconocido');
        }
    }

    public static function centroN(): self
    {
        return new self(self::CENTRO_N);
    }

    public static function centroSg(): self
    {
        return new self(self::CENTRO_SG);
    }

    public static function asociacion(): self
    {
        return new self(self::ASOCIACION);
    }

    public static function fundacion(): self
    {
        return new self(self::FUNDACION);
    }

    public static function persona(): self
    {
        return new self(self::PERSONA);
    }

    /** Frase para la instrucción del modelo: quién pregunta. */
    public function descripcion(): string
    {
        return match ($this->codigo) {
            self::CENTRO_SG => 'un centro sg: un solo libro, como el Excel Secretario sg. No hay libro personal (P), ni Mis cuentas, ni remesas, ni cierre de vivienda.',
            self::ASOCIACION => 'una asociación (plan Club): un solo libro, caja, banco e importación Grisbi. No es un centro n.',
            self::FUNDACION => 'una fundación (plan Club): un solo libro, caja, banco e importación Grisbi. No es un centro n ni una asociación; si el manual dice «associació», aplíquelo a la fundación.',
            self::PERSONA => 'el libro personal Mis cuentas. No lleva la contabilidad del centro.',
            default => 'un centro n: libros personal (P) y general (G), con viviendas, remesas y cierre de mes.',
        };
    }
}
