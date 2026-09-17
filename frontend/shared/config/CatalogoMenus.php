<?php

declare(strict_types=1);

namespace frontend\shared\config;

/**
 * Ítems de pantalla del centro y su agrupación por layout.
 *
 * @phpstan-type Item array{nav: string, href: string, label: string}
 * @phpstan-type Grupo array{id: string, label: string, items: list<Item>}
 */
final class CatalogoMenus
{
    /**
     * Pantallas con enlace en algún layout (excel o burger).
     *
     * @return list<Item>
     */
    public static function items(): array
    {
        return [
            ['nav' => 'configuracion', 'href' => '/configuracion', 'label' => _("Configuración")],
            ['nav' => 'centros', 'href' => '/centros', 'label' => _("Centros")],
            ['nav' => 'copias', 'href' => '/copias', 'label' => _("Copias")],
            ['nav' => 'nombres', 'href' => '/nombres', 'label' => _("Nombres")],
            ['nav' => 'ejercicios', 'href' => '/ejercicios', 'label' => _("Ejercicios")],
            ['nav' => 'tesoreria', 'href' => '/tesoreria', 'label' => _("Tesorería")],
            ['nav' => 'prevision-personal', 'href' => '/prevision-personal', 'label' => _("Previsión personal")],
            ['nav' => 'prevision', 'href' => '/prevision', 'label' => _("Previsión")],
            ['nav' => 'presupuesto-p', 'href' => '/presupuesto-p', 'label' => _("Presupuesto P")],
            ['nav' => 'presupuesto-g', 'href' => '/presupuesto-g', 'label' => _("Presupuesto G")],
            ['nav' => 'entrada-g', 'href' => '/entrada-g', 'label' => _("Entrada G")],
            ['nav' => 'entrada-p', 'href' => '/entrada-p', 'label' => _("Entrada P")],
            ['nav' => 'saldos', 'href' => '/saldos', 'label' => _("Saldos")],
            ['nav' => 'comprobaciones', 'href' => '/comprobaciones', 'label' => _("Comprobaciones")],
            ['nav' => 'fecha-cierre', 'href' => '/fecha-cierre', 'label' => _("Fecha cierre")],
            ['nav' => 'cierre', 'href' => '/cierre', 'label' => _("Cierre de mes")],
            ['nav' => 'apuntes', 'href' => '/apuntes', 'label' => _("Apuntes")],
            ['nav' => 'remesas', 'href' => '/remesas', 'label' => _("Remesas")],
            ['nav' => 'disponible', 'href' => '/disponible', 'label' => _("Disponible")],
            ['nav' => 'enviar-dl', 'href' => '/enviar-dl', 'label' => _("Enviar a DL")],
            ['nav' => '613-p', 'href' => '/613-p', 'label' => _("613 P")],
            ['nav' => '613-g', 'href' => '/613-g', 'label' => _("613 G")],
            ['nav' => 'e37', 'href' => '/e37', 'label' => _("Cuentas personales")],
            ['nav' => 'e37-resumen', 'href' => '/e37-resumen', 'label' => _("Resumen E37")],
            ['nav' => 'por-concepto', 'href' => '/por-concepto', 'label' => _("Por concepto")],
            ['nav' => 'conceptos-p', 'href' => '/conceptos-p', 'label' => _("Conceptos P")],
            ['nav' => 'conceptos-g', 'href' => '/conceptos-g', 'label' => _("Conceptos G")],
            ['nav' => 'plantillas-p', 'href' => '/plantillas-p', 'label' => _("Plantillas P")],
            ['nav' => 'plantillas-g', 'href' => '/plantillas-g', 'label' => _("Plantillas G")],
            ['nav' => 'traspasos', 'href' => '/traspasos', 'label' => _("Traspasos")],
            ['nav' => 'ayuda', 'href' => '/ayuda', 'label' => _("Ayuda")],
        ];
    }

    /**
     * Pantallas del centro que existen pero no salen en el menú (se llega por otro sitio).
     *
     * @return list<Item>
     */
    public static function pantallasSinMenu(): array
    {
        return [
            ['nav' => 'inicio', 'href' => '/', 'label' => _("Inicio")],
            ['nav' => 'arqueo-p', 'href' => '/arqueo-p', 'label' => _("Arqueo P")],
            ['nav' => 'arqueo-g', 'href' => '/arqueo-g', 'label' => _("Arqueo G")],
            ['nav' => 'cuenta-mail', 'href' => '/cuenta/mail', 'label' => _("Mail")],
            ['nav' => 'cuenta-password', 'href' => '/cuenta/password', 'label' => _("Contraseña")],
            ['nav' => 'cuenta-totp', 'href' => '/cuenta/totp', 'label' => _("2FA")],
            ['nav' => 'cuenta-layout', 'href' => '/cuenta/layout', 'label' => _("Layout")],
            ['nav' => 'cuenta-idioma', 'href' => '/cuenta/idioma', 'label' => _("Idioma")],
            ['nav' => 'cuenta-centro', 'href' => '/cuenta/centro', 'label' => _("Centro")],
            ['nav' => 'cuenta-persona', 'href' => '/cuenta/persona', 'label' => _("Persona activa")],
            ['nav' => 'cuenta-tipo', 'href' => '/cuenta/tipo', 'label' => _("Tipo")],
            ['nav' => 'cuenta-copias', 'href' => '/cuenta/copias', 'label' => _("Copia personal")],
        ];
    }

    /**
     * @return list<Grupo>
     */
    public static function grupos(string $layout): array
    {
        $porNav = [];
        foreach (self::items() as $item) {
            $porNav[$item['nav']] = $item;
        }
        $out = [];
        foreach (self::clavesGrupos($layout) as $grupo) {
            $items = [];
            foreach ($grupo['items'] as $nav) {
                if (!isset($porNav[$nav])) {
                    throw new \RuntimeException('Ítem de menú desconocido: ' . $nav);
                }
                $items[] = $porNav[$nav];
            }
            $out[] = [
                'id' => $grupo['id'],
                'label' => $grupo['label'],
                'items' => $items,
            ];
        }

        return $out;
    }

    public static function grupoDe(string $layout, string $nav): string
    {
        foreach (self::clavesGrupos($layout) as $grupo) {
            if (in_array($nav, $grupo['items'], true)) {
                return $grupo['id'];
            }
        }

        return self::clavesGrupos($layout)[0]['id'] ?? '';
    }

    /**
     * @return list<array{id: string, label: string, items: list<string>}>
     */
    private static function clavesGrupos(string $layout): array
    {
        if ($layout === 'burger') {
            return self::gruposBurger();
        }

        return self::gruposExcel();
    }

    /**
     * Cinta actual (tipo excel): grupos como en la hoja.
     *
     * @return list<array{id: string, label: string, items: list<string>}>
     */
    private static function gruposExcel(): array
    {
        return [
            [
                'id' => 'inicio',
                'label' => _("Inicio"),
                'items' => ['configuracion', 'centros', 'copias', 'nombres'],
            ],
            [
                'id' => 'presupuestos',
                'label' => _("Presupuestos"),
                'items' => ['prevision-personal', 'prevision', 'presupuesto-p', 'presupuesto-g'],
            ],
            [
                'id' => 'personales-generales',
                'label' => _("Personales y generales"),
                'items' => ['apuntes', 'cierre'],
            ],
            [
                'id' => 'personales',
                'label' => _("Personales"),
                'items' => ['entrada-p', 'remesas', 'disponible', 'enviar-dl'],
            ],
            [
                'id' => 'generales',
                'label' => _("Generales"),
                'items' => ['entrada-g'],
            ],
            [
                'id' => 'resumenes',
                'label' => _("Resúmenes"),
                'items' => ['613-p', '613-g', 'e37', 'e37-resumen', 'por-concepto', 'fecha-cierre'],
            ],
            [
                'id' => 'utilidades',
                'label' => _("Utilidades"),
                'items' => [
                    'saldos',
                    'comprobaciones',
                    'conceptos-p',
                    'conceptos-g',
                    'plantillas-p',
                    'plantillas-g',
                    'ejercicios',
                    'tesoreria',
                    'traspasos',
                    'ayuda',
                ],
            ],
        ];
    }

    /**
     * @return list<array{id: string, label: string, items: list<string>}>
     */
    private static function gruposBurger(): array
    {
        return [
            [
                'id' => 'parametros',
                'label' => _("Parámetros"),
                'items' => ['configuracion', 'centros', 'copias', 'nombres', 'ejercicios', 'tesoreria'],
            ],
            [
                'id' => 'presupuestos',
                'label' => _("Presupuestos"),
                'items' => ['prevision-personal', 'prevision', 'presupuesto-p', 'presupuesto-g'],
            ],
            [
                'id' => 'movimientos',
                'label' => _("Movimientos"),
                'items' => [
                    'entrada-g',
                    'entrada-p',
                    'saldos',
                    'comprobaciones',
                    'cierre',
                    'apuntes',
                    'remesas',
                    'disponible',
                    'enviar-dl',
                ],
            ],
            [
                'id' => 'resumenes',
                'label' => _("Resúmenes"),
                'items' => ['613-p', '613-g', 'e37', 'e37-resumen', 'por-concepto', 'fecha-cierre'],
            ],
            [
                'id' => 'plan-contable',
                'label' => _("Plan contable"),
                'items' => ['conceptos-p', 'conceptos-g', 'plantillas-p', 'plantillas-g', 'traspasos'],
            ],
            [
                'id' => 'ayuda',
                'label' => _("Ayuda"),
                'items' => ['ayuda'],
            ],
        ];
    }
}
