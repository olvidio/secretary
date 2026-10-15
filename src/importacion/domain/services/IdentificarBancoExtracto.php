<?php

declare(strict_types=1);

namespace src\importacion\domain\services;

use InvalidArgumentException;
use src\importacion\domain\contracts\ReconocedorExtractoBanco;

/** Elige el lector según la cabecera del fichero (orden: firmas más específicas primero). */
final class IdentificarBancoExtracto
{
    use TablaExtractoCsv;

    /** @var list<string> */
    private const ORDEN = ['santander', 'n26', 'bbva', 'sabadell', 'caixabank'];

    public function desdeCsv(string $contenido): string
    {
        $contenido = $this->aUtf8($this->sinBom($contenido));
        if (trim($contenido) === '') {
            throw new InvalidArgumentException(_('El fichero CSV está vacío'));
        }
        $filas = $this->filasCsv($contenido, $this->delimitador($contenido));
        if ($filas === []) {
            throw new InvalidArgumentException(_('El fichero CSV está vacío'));
        }

        return $this->desdeFilas($filas);
    }

    /**
     * @param list<list<string>> $filas
     */
    public function desdeFilas(array $filas): string
    {
        if ($filas === []) {
            throw new InvalidArgumentException(_('El extracto está vacío'));
        }
        foreach (self::ORDEN as $id) {
            $lector = CatalogoBancosCsv::lector($id);
            if ($lector instanceof ReconocedorExtractoBanco && $lector->reconoceFilas($filas)) {
                return $id;
            }
        }

        throw new InvalidArgumentException(
            _('No se reconoce el formato del extracto. Use un CSV o Excel de N26, CaixaBank, BBVA, Banco Santander o Banco Sabadell.')
        );
    }
}
