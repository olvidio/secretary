<?php

declare(strict_types=1);

namespace src\acceso\application;

/**
 * Fuente de `rutas_acceso`. Lo que no está aquí se deniega (D7).
 *
 * @phpstan-type Fila array{clase: string, metodo: string, ambito: string}
 */
final class CatalogoRutas
{
    /**
     * @return list<Fila>
     */
    public static function todas(): array
    {
        $p = 'frontend\\shared\\http\\PageController';
        $a = 'src\\acceso\\infrastructure\\http\\AuthController';
        $filas = [
            [$p, 'login', 'publico'],
            [$p, 'registro', 'publico'],
            [$a, 'login', 'publico'],
            [$a, 'registro', 'publico'],
            [$a, 'csrf', 'publico'],
            [$a, 'logout', 'pendiente'],
            [$a, 'totpActivar', 'pendiente'],
            [$a, 'totpConfirmar', 'pendiente'],
            [$a, 'totpVerificar', 'pendiente'],
            [$a, 'elegirCentro', 'autenticado'],
            [$a, 'elegirPersona', 'autenticado'],
            ['src\\acceso\\infrastructure\\http\\PreferenciaController', 'get', 'autenticado'],
            ['src\\acceso\\infrastructure\\http\\PreferenciaController', 'guardarLayout', 'autenticado'],
            ['src\\acceso\\infrastructure\\http\\PreferenciaController', 'guardarMail', 'autenticado'],
            ['src\\acceso\\infrastructure\\http\\PreferenciaController', 'guardarPassword', 'autenticado'],
            ['src\\acceso\\infrastructure\\http\\PreferenciaController', 'totpPreparar', 'autenticado'],
            ['src\\acceso\\infrastructure\\http\\PreferenciaController', 'totpConfirmar', 'autenticado'],
            ['src\\acceso\\infrastructure\\http\\PreferenciaController', 'guardarIdioma', 'autenticado'],
            ['src\\acceso\\infrastructure\\http\\PreferenciaController', 'guardarCentro', 'autenticado'],
            ['src\\acceso\\infrastructure\\http\\PreferenciaController', 'guardarPersona', 'autenticado'],
            ['src\\acceso\\infrastructure\\http\\PreferenciaController', 'guardarTipo', 'autenticado'],
            [$p, 'cuenta', 'autenticado'],
            [$p, 'totpActivar', 'pendiente'],
            [$p, 'totpVerificar', 'pendiente'],
            [$p, 'elegirCentro', 'autenticado'],
            [$p, 'elegirPersona', 'autenticado'],
            [$p, 'totpCodigos', 'autenticado'],
            [$p, 'page', 'centro'],
            [$p, 'yo', 'persona'],
            [$p, 'yoMovimientos', 'persona'],
            [$p, 'yoCategorias', 'persona'],
            [$p, 'yoBanco', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'resumen', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'movimientos', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'crear', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'actualizarMovimiento', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'desdoblarMovimiento', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'borrarMovimiento', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'listarCategorias', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'listarConceptosGenerales', 'persona'],
            ['src\\personal\\infrastructure\\http\\CopiaPersonalController', 'list', 'persona'],
            ['src\\personal\\infrastructure\\http\\CopiaPersonalController', 'backup', 'persona'],
            ['src\\personal\\infrastructure\\http\\CopiaPersonalController', 'descargar', 'persona'],
            ['src\\personal\\infrastructure\\http\\CopiaPersonalController', 'restore', 'persona'],
            ['src\\personal\\infrastructure\\http\\CopiaPersonalController', 'borrar', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'crearCategoria', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'cierre', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'guardarCierreDefecto', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'guardarCierreMes', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'borrarCierreMes', 'persona'],
            ['src\\personal\\infrastructure\\http\\BancoPersonalController', 'bancos', 'persona'],
            ['src\\personal\\infrastructure\\http\\BancoPersonalController', 'pendientes', 'persona'],
            ['src\\personal\\infrastructure\\http\\BancoPersonalController', 'importar', 'persona'],
            ['src\\personal\\infrastructure\\http\\BancoPersonalController', 'categorizar', 'persona'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'previsualizar', 'persona'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'enviar', 'persona'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'verPersonal', 'persona'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'solicitudesPersona', 'persona'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'resolverSolicitud', 'persona'],
            [$p, 'yoRemesas', 'persona'],
            [$p, 'yoCierre', 'persona'],
            [$p, 'yoCentros', 'persona'],
            [$p, 'yoAyuda', 'persona'],
            ['src\\ayuda\\infrastructure\\http\\AyudaController', 'listarTemas', 'autenticado'],
            ['src\\ayuda\\infrastructure\\http\\AyudaController', 'preguntar', 'autenticado'],
            ['src\\configuracion\\infrastructure\\http\\ConfiguracionController', 'get', 'centro'],
            ['src\\configuracion\\infrastructure\\http\\ConfiguracionController', 'save', 'centro'],
            ['src\\ambito\\infrastructure\\http\\CentroController', 'get', 'centro'],
            ['src\\ambito\\infrastructure\\http\\CentroController', 'create', 'centro'],
            ['src\\ambito\\infrastructure\\http\\CentroController', 'import', 'centro'],
            ['src\\ambito\\infrastructure\\http\\CentroController', 'vaciar', 'centro'],
            ['src\\ambito\\infrastructure\\http\\CentroController', 'addUsuario', 'centro'],
            ['src\\plan\\infrastructure\\http\\PartidaLaboresController', 'list', 'centro'],
            ['src\\plan\\infrastructure\\http\\PartidaLaboresController', 'save', 'centro'],
            ['src\\personas\\infrastructure\\http\\PersonaController', 'list', 'centro'],
            ['src\\personas\\infrastructure\\http\\PersonaController', 'save', 'centro'],
            ['src\\personas\\infrastructure\\http\\PersonaController', 'delete', 'centro'],
            ['src\\personas\\infrastructure\\http\\VinculoCentroController', 'listarCentro', 'centro'],
            ['src\\personas\\infrastructure\\http\\VinculoCentroController', 'candidatos', 'centro'],
            ['src\\personas\\infrastructure\\http\\VinculoCentroController', 'aprobar', 'centro'],
            ['src\\personas\\infrastructure\\http\\VinculoCentroController', 'rechazar', 'centro'],
            ['src\\personas\\infrastructure\\http\\VinculoCentroController', 'listarYo', 'persona'],
            ['src\\personas\\infrastructure\\http\\VinculoCentroController', 'centrosDisponibles', 'persona'],
            ['src\\personas\\infrastructure\\http\\VinculoCentroController', 'solicitarYo', 'persona'],
            ['src\\conceptos\\infrastructure\\http\\ConceptoController', 'list', 'centro'],
            ['src\\apuntes\\infrastructure\\http\\ApunteController', 'list', 'centro'],
            ['src\\apuntes\\infrastructure\\http\\ApunteController', 'sugerencias', 'centro'],
            ['src\\apuntes\\infrastructure\\http\\ApunteController', 'cuadre', 'centro'],
            ['src\\apuntes\\infrastructure\\http\\ApunteController', 'create', 'centro'],
            ['src\\apuntes\\infrastructure\\http\\ApunteController', 'update', 'centro'],
            ['src\\apuntes\\infrastructure\\http\\ApunteController', 'delete', 'centro'],
            ['src\\apuntes\\infrastructure\\http\\PlantillaApunteController', 'list', 'centro'],
            ['src\\apuntes\\infrastructure\\http\\PlantillaApunteController', 'save', 'centro'],
            ['src\\apuntes\\infrastructure\\http\\PlantillaApunteController', 'delete', 'centro'],
            ['src\\cierre\\infrastructure\\http\\CierreController', 'preview', 'centro'],
            ['src\\cierre\\infrastructure\\http\\CierreController', 'run', 'centro'],
            ['src\\informes\\infrastructure\\http\\InformeController', 'resumen613', 'centro'],
            ['src\\informes\\infrastructure\\http\\InformeController', 'guardarManual613', 'centro'],
            ['src\\informes\\infrastructure\\http\\InformeController', 'e37', 'centro'],
            ['src\\informes\\infrastructure\\http\\InformeController', 'e37Resumen', 'centro'],
            ['src\\informes\\infrastructure\\http\\InformeController', 'saldos', 'centro'],
            ['src\\informes\\infrastructure\\http\\InformeController', 'comprobacionesSaldos', 'centro'],
            ['src\\informes\\infrastructure\\http\\InformeController', 'comprobaciones', 'centro'],
            ['src\\informes\\infrastructure\\http\\InformeController', 'tesoreria', 'centro'],
            ['src\\presupuestos\\infrastructure\\http\\PresupuestoController', 'get', 'centro'],
            ['src\\presupuestos\\infrastructure\\http\\PresupuestoController', 'save', 'centro'],
            ['src\\arqueo\\infrastructure\\http\\ArqueoController', 'get', 'centro'],
            ['src\\arqueo\\infrastructure\\http\\ArqueoController', 'save', 'centro'],
            ['src\\arqueo\\infrastructure\\http\\ArqueoController', 'capuchinos', 'centro'],
            ['src\\arqueo\\infrastructure\\http\\ArqueoController', 'getFisica', 'centro'],
            ['src\\arqueo\\infrastructure\\http\\ArqueoController', 'saveFisica', 'centro'],
            ['src\\ambito\\infrastructure\\http\\TesoreriaController', 'list', 'centro'],
            ['src\\ambito\\infrastructure\\http\\TesoreriaController', 'create', 'centro'],
            ['src\\ambito\\infrastructure\\http\\TesoreriaController', 'desactivar', 'centro'],
            ['src\\asientos\\infrastructure\\http\\TraspasoController', 'traspaso', 'centro'],
            ['src\\asientos\\infrastructure\\http\\TraspasoController', 'prestamo', 'centro'],
            ['src\\ambito\\infrastructure\\http\\EjercicioController', 'list', 'centro'],
            ['src\\ambito\\infrastructure\\http\\EjercicioController', 'create', 'centro'],
            ['src\\ambito\\infrastructure\\http\\EjercicioController', 'cerrar', 'centro'],
            ['src\\ambito\\infrastructure\\http\\EjercicioController', 'reabrir', 'centro'],
            ['src\\ambito\\infrastructure\\http\\EjercicioController', 'apertura', 'centro'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'listarCentro', 'centro'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'verCentro', 'centro'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'aceptar', 'centro'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'rechazar', 'centro'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'solicitarDetalle', 'centro'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'detalleLinea', 'centro'],
            ['src\\disponible\\infrastructure\\http\\DisponibleController', 'listar', 'centro'],
            ['src\\disponible\\infrastructure\\http\\DisponibleController', 'ajustar', 'centro'],
            ['src\\disponible\\infrastructure\\http\\DisponibleController', 'proponer', 'centro'],
            ['src\\disponible\\infrastructure\\http\\DisponibleController', 'confirmar', 'centro'],
            ['src\\disponible\\infrastructure\\http\\DisponibleController', 'tramos', 'centro'],
            ['src\\disponible\\infrastructure\\http\\DisponibleController', 'guardarTramos', 'centro'],
            ['src\\disponible\\infrastructure\\http\\DisponibleController', 'yoAsignaciones', 'persona'],
            ['src\\shared\\infrastructure\\http\\CopiaSeguridadController', 'list', 'centro'],
            ['src\\shared\\infrastructure\\http\\CopiaSeguridadController', 'backup', 'centro'],
            ['src\\shared\\infrastructure\\http\\CopiaSeguridadController', 'descargar', 'centro'],
            ['src\\shared\\infrastructure\\http\\CopiaSeguridadController', 'restore', 'centro'],
            ['src\\shared\\infrastructure\\http\\CopiaSeguridadController', 'borrar', 'centro'],
        ];
        $out = [];
        foreach ($filas as $fila) {
            $out[] = ['clase' => $fila[0], 'metodo' => $fila[1], 'ambito' => $fila[2]];
        }

        return $out;
    }

    public static function ambitoDe(string $clase, string $metodoPhp): ?string
    {
        foreach (self::todas() as $fila) {
            if ($fila['clase'] === $clase && $fila['metodo'] === $metodoPhp) {
                return $fila['ambito'];
            }
        }

        return null;
    }
}
