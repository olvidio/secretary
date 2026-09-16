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
            ['nav' => 'configuracion', 'href' => '/configuracion', 'label' => 'Configuración'],
            ['nav' => 'centros', 'href' => '/centros', 'label' => 'Centros'],
            ['nav' => 'copias', 'href' => '/copias', 'label' => 'Copias'],
            ['nav' => 'nombres', 'href' => '/nombres', 'label' => 'Nombres'],
            ['nav' => 'ejercicios', 'href' => '/ejercicios', 'label' => 'Ejercicios'],
            ['nav' => 'tesoreria', 'href' => '/tesoreria', 'label' => 'Tesorería'],
            ['nav' => 'presupuesto-p', 'href' => '/presupuesto-p', 'label' => 'Presupuesto P'],
            ['nav' => 'presupuesto-g', 'href' => '/presupuesto-g', 'label' => 'Presupuesto G'],
            ['nav' => 'entrada-g', 'href' => '/entrada-g', 'label' => 'Entrada G'],
            ['nav' => 'entrada-p', 'href' => '/entrada-p', 'label' => 'Entrada P'],
            ['nav' => 'saldos', 'href' => '/saldos', 'label' => 'Saldos'],
            ['nav' => 'comprobaciones', 'href' => '/comprobaciones', 'label' => 'Comprobaciones'],
            ['nav' => 'fecha-cierre', 'href' => '/fecha-cierre', 'label' => 'Fecha cierre'],
            ['nav' => 'cierre', 'href' => '/cierre', 'label' => 'Cierre de mes'],
            ['nav' => 'apuntes', 'href' => '/apuntes', 'label' => 'Apuntes'],
            ['nav' => 'remesas', 'href' => '/remesas', 'label' => 'Remesas'],
            ['nav' => 'disponible', 'href' => '/disponible', 'label' => 'Disponible'],
            ['nav' => 'enviar-dl', 'href' => '/enviar-dl', 'label' => 'Enviar a DL'],
            ['nav' => '613-p', 'href' => '/613-p', 'label' => '613 P'],
            ['nav' => '613-g', 'href' => '/613-g', 'label' => '613 G'],
            ['nav' => 'e37', 'href' => '/e37', 'label' => 'Cuentas personales'],
            ['nav' => 'e37-resumen', 'href' => '/e37-resumen', 'label' => 'Resumen E37'],
            ['nav' => 'por-concepto', 'href' => '/por-concepto', 'label' => 'Por concepto'],
            ['nav' => 'conceptos-p', 'href' => '/conceptos-p', 'label' => 'Conceptos P'],
            ['nav' => 'conceptos-g', 'href' => '/conceptos-g', 'label' => 'Conceptos G'],
            ['nav' => 'plantillas-p', 'href' => '/plantillas-p', 'label' => 'Plantillas P'],
            ['nav' => 'plantillas-g', 'href' => '/plantillas-g', 'label' => 'Plantillas G'],
            ['nav' => 'traspasos', 'href' => '/traspasos', 'label' => 'Traspasos'],
            ['nav' => 'ayuda', 'href' => '/ayuda', 'label' => 'Ayuda'],
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
            ['nav' => 'inicio', 'href' => '/', 'label' => 'Inicio'],
            ['nav' => 'arqueo-p', 'href' => '/arqueo-p', 'label' => 'Arqueo P'],
            ['nav' => 'arqueo-g', 'href' => '/arqueo-g', 'label' => 'Arqueo G'],
            ['nav' => 'cuenta-mail', 'href' => '/cuenta/mail', 'label' => 'Mail'],
            ['nav' => 'cuenta-password', 'href' => '/cuenta/password', 'label' => 'Contraseña'],
            ['nav' => 'cuenta-totp', 'href' => '/cuenta/totp', 'label' => '2FA'],
            ['nav' => 'cuenta-layout', 'href' => '/cuenta/layout', 'label' => 'Layout'],
            ['nav' => 'cuenta-idioma', 'href' => '/cuenta/idioma', 'label' => 'Idioma'],
            ['nav' => 'cuenta-centro', 'href' => '/cuenta/centro', 'label' => 'Centro'],
            ['nav' => 'cuenta-persona', 'href' => '/cuenta/persona', 'label' => 'Persona activa'],
            ['nav' => 'cuenta-tipo', 'href' => '/cuenta/tipo', 'label' => 'Tipo'],
            ['nav' => 'cuenta-copias', 'href' => '/cuenta/copias', 'label' => 'Copia personal'],
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
                'label' => 'Inicio',
                'items' => ['configuracion', 'centros', 'copias', 'nombres'],
            ],
            [
                'id' => 'presupuestos',
                'label' => 'Presupuestos',
                'items' => ['presupuesto-p', 'presupuesto-g'],
            ],
            [
                'id' => 'personales-generales',
                'label' => 'Personales y generales',
                'items' => ['apuntes', 'cierre'],
            ],
            [
                'id' => 'personales',
                'label' => 'Personales',
                'items' => ['entrada-p', 'remesas', 'disponible', 'enviar-dl'],
            ],
            [
                'id' => 'generales',
                'label' => 'Generales',
                'items' => ['entrada-g'],
            ],
            [
                'id' => 'resumenes',
                'label' => 'Resúmenes',
                'items' => ['613-p', '613-g', 'e37', 'e37-resumen', 'por-concepto', 'fecha-cierre'],
            ],
            [
                'id' => 'utilidades',
                'label' => 'Utilidades',
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
                'label' => 'Parámetros',
                'items' => ['configuracion', 'centros', 'copias', 'nombres', 'ejercicios', 'tesoreria'],
            ],
            [
                'id' => 'presupuestos',
                'label' => 'Presupuestos',
                'items' => ['presupuesto-p', 'presupuesto-g'],
            ],
            [
                'id' => 'movimientos',
                'label' => 'Movimientos',
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
                'label' => 'Resúmenes',
                'items' => ['613-p', '613-g', 'e37', 'e37-resumen', 'por-concepto', 'fecha-cierre'],
            ],
            [
                'id' => 'plan-contable',
                'label' => 'Plan contable',
                'items' => ['conceptos-p', 'conceptos-g', 'plantillas-p', 'plantillas-g', 'traspasos'],
            ],
            [
                'id' => 'ayuda',
                'label' => 'Ayuda',
                'items' => ['ayuda'],
            ],
        ];
    }
}
