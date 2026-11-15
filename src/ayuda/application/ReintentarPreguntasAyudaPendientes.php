<?php

declare(strict_types=1);

namespace src\ayuda\application;

use RuntimeException;
use src\ayuda\domain\contracts\ProveedorRespuestaIA;
use src\ayuda\domain\contracts\RegistroConsultasAyuda;
use src\ayuda\domain\contracts\RepositorioDocumentacion;
use src\ayuda\domain\entity\DocumentoAyuda;
use src\ayuda\domain\services\ConstructorPromptAyuda;
use src\ayuda\domain\services\InterpreteRespuestaIA;
use src\ayuda\domain\services\ManualPorAmbito;
use src\ayuda\domain\value_objects\AmbitoManual;
use src\ayuda\domain\value_objects\EstadoRepasoAyuda;
use src\ayuda\domain\value_objects\PreguntaAyudaPendiente;

/**
 * Tras desplegar un manual nuevo, vuelve a hacer a la IA las preguntas que se
 * quedaron sin respuesta. Las que ahora contesta (o sobre las que ya sabe pedir
 * que se concrete) se cierran solas como cubiertas; las demás siguen pendientes,
 * que es la señal de que el manual todavía no las cubre.
 *
 * Las de «no sirvió» no se tocan: que la respuesta nueva sea mejor solo lo
 * puede juzgar una persona. Las consultas de este repaso no se registran ni
 * cuentan para el límite diario de nadie.
 */
final class ReintentarPreguntasAyudaPendientes
{
    public function __construct(
        private readonly RepositorioDocumentacion $documentacion,
        private readonly RegistroConsultasAyuda $registro,
        private readonly ConstructorPromptAyuda $constructor,
        private readonly InterpreteRespuestaIA $interprete,
        private readonly ?ProveedorRespuestaIA $proveedor = null,
    ) {
    }

    /**
     * @param int $maximo tope de preguntas por ejecución (cada una es al menos una consulta al modelo)
     * @return array{
     *     sin_proveedor: bool,
     *     cubiertas: list<string>,
     *     siguen: list<string>,
     *     no_sirvio: int,
     *     sin_probar: int,
     *     error: ?string
     * }
     */
    public function ejecutar(int $maximo = 50): array
    {
        $out = [
            'sin_proveedor' => $this->proveedor === null,
            'cubiertas' => [],
            'siguen' => [],
            'no_sirvio' => 0,
            'sin_probar' => 0,
            'error' => null,
        ];
        $porProbar = [];
        foreach ($this->registro->listarPendientes() as $pendiente) {
            if ($pendiente->motivo === PreguntaAyudaPendiente::NO_SIRVIO) {
                $out['no_sirvio']++;
            } else {
                $porProbar[] = $pendiente;
            }
        }
        if ($this->proveedor === null) {
            $out['sin_probar'] = count($porProbar);

            return $out;
        }
        $todos = $this->documentacion->todos();
        foreach ($porProbar as $i => $pendiente) {
            if ($i >= $maximo) {
                $out['sin_probar'] = count($porProbar) - $i;
                break;
            }
            try {
                $cubierta = $this->laContestaAhora($pendiente, $todos);
            } catch (RuntimeException $e) {
                // Proveedor caído o sin cuota: no tiene sentido seguir; lo no probado queda pendiente.
                $out['error'] = $e->getMessage();
                $out['sin_probar'] = count($porProbar) - $i;
                break;
            }
            if ($cubierta) {
                $this->registro->marcarRepasada($pendiente->pregunta, EstadoRepasoAyuda::Cubierta);
                $out['cubiertas'][] = $pendiente->pregunta;
            } else {
                $out['siguen'][] = $pendiente->pregunta;
            }
        }

        return $out;
    }

    /**
     * Tiene que contestarla en todos los ámbitos desde los que se preguntó.
     *
     * @param list<DocumentoAyuda> $todos
     */
    private function laContestaAhora(PreguntaAyudaPendiente $pendiente, array $todos): bool
    {
        $ambitos = array_values(array_intersect($pendiente->ambitos, AmbitoManual::TODOS));
        if ($ambitos === []) {
            $ambitos = [AmbitoManual::CENTRO_N];
        }
        foreach ($ambitos as $codigo) {
            $ambito = new AmbitoManual($codigo);
            $documentos = (new ManualPorAmbito())->aplicar($todos, $ambito);
            if ($documentos === []) {
                return false;
            }
            $crudo = $this->proveedor->responder(
                $this->constructor->instruccion($documentos, 'es', $ambito),
                $pendiente->pregunta,
            );
            $claves = array_map(static fn (DocumentoAyuda $d): string => $d->clave, $documentos);
            if (!$this->interprete->interpretar($crudo, $claves)->resuelta) {
                return false;
            }
        }

        return true;
    }
}
