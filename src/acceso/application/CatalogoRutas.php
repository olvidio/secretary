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
            [$a, 'login', 'publico'],
            [$a, 'csrf', 'publico'],
            [$a, 'logout', 'pendiente'],
            [$a, 'totpActivar', 'pendiente'],
            [$a, 'totpConfirmar', 'pendiente'],
            [$a, 'totpVerificar', 'pendiente'],
            [$a, 'elegirCentro', 'autenticado'],
            [$p, 'totpActivar', 'pendiente'],
            [$p, 'totpVerificar', 'pendiente'],
            [$p, 'elegirCentro', 'autenticado'],
            [$p, 'totpCodigos', 'autenticado'],
            [$p, 'page', 'centro'],
            [$p, 'yo', 'persona'],
            [$p, 'yoMovimientos', 'persona'],
            [$p, 'yoCategorias', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'resumen', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'movimientos', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'crear', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'borrarMovimiento', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'listarCategorias', 'persona'],
            ['src\\personal\\infrastructure\\http\\PersonalController', 'crearCategoria', 'persona'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'previsualizar', 'persona'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'enviar', 'persona'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'verPersonal', 'persona'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'solicitudesPersona', 'persona'],
            ['src\\remesas\\infrastructure\\http\\RemesaController', 'resolverSolicitud', 'persona'],
            [$p, 'yoRemesas', 'persona'],
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
        ];
        $out = [];
        foreach ($filas as $fila) {
            $out[] = ['clase' => $fila[0], 'metodo' => $fila[1], 'ambito' => $fila[2]];
        }

        return $out;
    }
}
