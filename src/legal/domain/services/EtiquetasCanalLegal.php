<?php

declare(strict_types=1);

namespace src\legal\domain\services;

final class EtiquetasCanalLegal
{
    public static function etiqueta(string $canal): string
    {
        return match ($canal) {
            'formulario_registro' => _('Registro (formulario)'),
            'confirmacion_email' => _('Confirmación de correo'),
            'nombres_alta' => _('Alta de persona (declaración responsable)'),
            'nombres_import' => _('Importación de nombres (declaración responsable)'),
            'vinculo_alta' => _('Vinculación a centro (declaración responsable)'),
            default => $canal,
        };
    }
}
