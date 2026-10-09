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
            ['nav' => 'centros', 'href' => '/centros', 'label' => _("Centro")],
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
            ['nav' => 'listados', 'href' => '/listados', 'label' => _("Listados")],
            ['nav' => '613-p', 'href' => '/613-p', 'label' => _("613 P")],
            ['nav' => '613-g', 'href' => '/613-g', 'label' => _("613 G")],
            ['nav' => 'e37', 'href' => '/e37', 'label' => _("Cuentas personales")],
            ['nav' => 'e37-resumen', 'href' => '/e37-resumen', 'label' => _("Resumen E37")],
            ['nav' => 'por-concepto', 'href' => '/por-concepto', 'label' => _("Por concepto")],
            ['nav' => 'arqueo-p', 'href' => '/arqueo-p', 'label' => _("Arqueo Caja")],
            ['nav' => 'arqueo-g', 'href' => '/arqueo-g', 'label' => _("Saldo banco")],
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
            ['nav' => 'arqueo', 'href' => '/arqueo', 'label' => _("Arqueo")],
            ['nav' => 'aportaciones', 'href' => '/aportaciones', 'label' => _("Listado de aportaciones")],
            ['nav' => 'donativos-fundacion', 'href' => '/donativos-fundacion', 'label' => _("Donativos a Fundación")],
            ['nav' => 'entradas-periodicas', 'href' => '/entradas-periodicas', 'label' => _("Entradas periódicas")],
            ['nav' => 'ejecutar-entradas-periodicas', 'href' => '/ejecutar-entradas-periodicas', 'label' => _("Ejecutar periódicas")],
            ['nav' => 'cuenta-mail', 'href' => '/cuenta/mail', 'label' => _("Mail")],
            ['nav' => 'cuenta-password', 'href' => '/cuenta/password', 'label' => _("Contraseña")],
            ['nav' => 'cuenta-totp', 'href' => '/cuenta/totp', 'label' => _("2FA")],
            ['nav' => 'cuenta-layout', 'href' => '/cuenta/layout', 'label' => _("Layout")],
            ['nav' => 'cuenta-idioma', 'href' => '/cuenta/idioma', 'label' => _("Idioma")],
            ['nav' => 'cuenta-centro', 'href' => '/cuenta/centro', 'label' => _("Centro")],
            ['nav' => 'cuenta-ambito', 'href' => '/cuenta/ambito', 'label' => _("Ámbito")],
            ['nav' => 'cuenta-persona', 'href' => '/cuenta/persona', 'label' => _("Persona activa")],
            ['nav' => 'cuenta-tipo', 'href' => '/cuenta/tipo', 'label' => _("Tipo")],
            ['nav' => 'cuenta-copias', 'href' => '/cuenta/copias', 'label' => _("Copia personal")],
            ['nav' => 'cuenta-baja', 'href' => '/cuenta/baja', 'label' => _("Dar de baja la cuenta")],
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

    /**
     * Pantallas que un centro con plan Club puede abrir. El resto del menú
     * de casa (nombres, 613, libro P, remesas) no se muestra.
     *
     * @return list<string>
     */
    public static function navsClub(): array
    {
        return [
            'inicio',
            'configuracion',
            'copias',
            'ejercicios',
            'tesoreria',
            'entrada-g',
            'apuntes',
            'saldos',
            'arqueo',
            'listados',
            'conceptos-g',
            'plantillas-g',
            'traspasos',
            'ayuda',
        ];
    }

    /**
     * Pantallas de un centro sg (Excel Secretario sg): un libro, nombres, 613 y arqueo.
     *
     * @return list<string>
     */
    public static function navsCentroSg(): array
    {
        return [
            'inicio',
            'configuracion',
            'copias',
            'nombres',
            'ejercicios',
            'entrada-g',
            'entradas-periodicas',
            'ejecutar-entradas-periodicas',
            'apuntes',
            'presupuesto-g',
            '613-g',
            'aportaciones',
            'donativos-fundacion',
            'plantillas-g',
            'arqueo-p',
            'fecha-cierre',
            'conceptos-g',
            'ayuda',
        ];
    }

    /**
     * Menú propio del plan H16s. No es el de la casa con huecos.
     *
     * @return list<Grupo>
     */
    public static function gruposCentroSg(): array
    {
        $porNav = [];
        foreach (array_merge(self::items(), self::pantallasSinMenu()) as $item) {
            $porNav[$item['nav']] = $item;
        }
        $defs = [
            ['id' => 'centro', 'label' => _("Centro"), 'items' => [
                ['configuracion', null],
                ['nombres', null],
                ['copias', null],
            ]],
            ['id' => 'talonario', 'label' => _("Talonario"), 'items' => [
                ['entrada-g', _("Entrada")],
                ['entradas-periodicas', null],
                ['ejecutar-entradas-periodicas', null],
                ['apuntes', _("Listado de apuntes")],
                ['plantillas-g', _("Plantillas")],
            ]],
            ['id' => 'presupuesto-informes', 'label' => _("Presupuesto e informes"), 'items' => [
                ['presupuesto-g', _("Presupuesto")],
                ['613-g', _("613")],
                ['aportaciones', null],
                ['donativos-fundacion', null],
            ]],
            ['id' => 'cierre-arqueo', 'label' => _("Cierre y arqueo"), 'items' => [
                ['arqueo-p', _("Arqueo")],
                ['fecha-cierre', null],
            ]],
            ['id' => 'plan-periodo', 'label' => _("Plan y ejercicio"), 'items' => [
                ['conceptos-g', _("Conceptos")],
                ['ejercicios', null],
            ]],
            ['id' => 'ayuda', 'label' => _("Ayuda"), 'items' => [
                ['ayuda', null],
            ]],
        ];
        $out = [];
        foreach ($defs as $def) {
            $items = [];
            foreach ($def['items'] as [$nav, $etiqueta]) {
                $item = $porNav[$nav];
                if ($etiqueta !== null) {
                    $item['label'] = $etiqueta;
                }
                $items[] = $item;
            }
            $out[] = ['id' => $def['id'], 'label' => $def['label'], 'items' => $items];
        }

        return $out;
    }

    /**
     * @return list<Grupo>
     */
    public static function gruposPara(string $layout, bool $club, bool $centroSg = false): array
    {
        if ($centroSg) {
            return self::gruposCentroSg();
        }
        if (!$club) {
            return self::grupos($layout);
        }
        $grupos = self::gruposFiltrados($layout, self::navsClub(), [
            'entrada-g' => _("Entrada"),
            'conceptos-g' => _("Cuentas"),
            'plantillas-g' => _("Plantillas"),
        ], 'arqueo', _("Arqueo"));

        return self::inyectarNavsSoloClub($grupos, $layout);
    }

    /**
     * Pantallas que solo aparecen en el menú del plan Club (no en la casa n).
     *
     * @return list<string>
     */
    public static function navsSoloClubEnMenu(): array
    {
        return ['listados'];
    }

    /**
     * @param list<Grupo> $grupos
     * @return list<Grupo>
     */
    private static function inyectarNavsSoloClub(array $grupos, string $layout): array
    {
        $porNav = [];
        foreach (self::items() as $item) {
            $porNav[$item['nav']] = $item;
        }
        $grupoId = $layout === 'burger' ? 'movimientos' : 'generales';
        foreach ($grupos as $i => $grupo) {
            if ($grupo['id'] !== $grupoId) {
                continue;
            }
            $ya = array_column($grupo['items'], 'nav');
            $nuevos = $grupo['items'];
            foreach (self::navsSoloClubEnMenu() as $nav) {
                if (in_array($nav, $ya, true) || !isset($porNav[$nav])) {
                    continue;
                }
                $nuevos[] = $porNav[$nav];
            }
            $grupos[$i]['items'] = $nuevos;
            break;
        }

        return $grupos;
    }

    /**
     * @param list<string> $permitidos
     * @param array<string, string> $etiquetas
     * @return list<Grupo>
     */
    private static function gruposFiltrados(
        string $layout,
        array $permitidos,
        array $etiquetas,
        string $navTrasSaldos,
        string $etiquetaTrasSaldos,
    ): array {
        $permitidos = array_flip($permitidos);
        $hrefs = [];
        foreach (array_merge(self::items(), self::pantallasSinMenu()) as $item) {
            $hrefs[$item['nav']] = $item['href'];
        }
        $out = [];
        foreach (self::grupos($layout) as $grupo) {
            $items = [];
            foreach ($grupo['items'] as $item) {
                if (!isset($permitidos[$item['nav']])) {
                    continue;
                }
                if (isset($etiquetas[$item['nav']])) {
                    $item['label'] = $etiquetas[$item['nav']];
                }
                $items[] = $item;
            }
            if ($items === []) {
                continue;
            }
            $conExtra = [];
            foreach ($items as $item) {
                $conExtra[] = $item;
                if ($item['nav'] === 'saldos' && isset($permitidos[$navTrasSaldos])) {
                    $conExtra[] = [
                        'nav' => $navTrasSaldos,
                        'href' => $hrefs[$navTrasSaldos] ?? '/' . $navTrasSaldos,
                        'label' => $etiquetaTrasSaldos,
                    ];
                }
            }
            $out[] = [
                'id' => $grupo['id'],
                'label' => $grupo['label'],
                'items' => $conExtra,
            ];
        }

        return $out;
    }

    /** @return list<Item> */
    public static function itemsAdmin(): array
    {
        return [
            ['nav' => 'admin-planes', 'href' => '/admin/planes', 'label' => _("Planes contables")],
            ['nav' => 'admin-centros', 'href' => '/admin/centros', 'label' => _("Entidades")],
            ['nav' => 'admin-usuarios', 'href' => '/admin/usuarios', 'label' => _("Usuarios")],
            ['nav' => 'admin-legal', 'href' => '/admin/legal', 'label' => _("Legal")],
            ['nav' => 'admin-copias', 'href' => '/admin/copias', 'label' => _("Copias de la base")],
        ];
    }

    public static function grupoDe(string $layout, string $nav, bool $centroSg = false): string
    {
        if ($centroSg) {
            foreach (self::gruposCentroSg() as $grupo) {
                foreach ($grupo['items'] as $item) {
                    if ($item['nav'] === $nav) {
                        return $grupo['id'];
                    }
                }
            }

            return 'centro';
        }
        if ($nav === 'arqueo') {
            return $layout === 'burger' ? 'movimientos' : 'utilidades';
        }
        if ($nav === 'listados') {
            return $layout === 'burger' ? 'movimientos' : 'generales';
        }
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
                'items' => ['613-p', '613-g', 'e37', 'e37-resumen', 'por-concepto', 'arqueo-p', 'arqueo-g', 'fecha-cierre'],
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
                'items' => ['613-p', '613-g', 'e37', 'e37-resumen', 'por-concepto', 'arqueo-p', 'arqueo-g', 'fecha-cierre'],
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
