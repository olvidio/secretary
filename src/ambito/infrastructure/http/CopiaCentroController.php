<?php

declare(strict_types=1);

namespace src\ambito\infrastructure\http;

use InvalidArgumentException;
use RuntimeException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\infrastructure\persistence\AlmacenCopiasCentro;
use src\ambito\infrastructure\persistence\PdoCopiaCentro;
use src\shared\application\AsegurarHuecoCopias;
use src\shared\domain\exceptions\LimiteCopiasAlcanzado;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class CopiaCentroController
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly CentroRepository $centros,
        private readonly PdoCopiaCentro $copias,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        try {
            $centro = $this->centro();
            $almacen = new AlmacenCopiasCentro((int) $centro->id);

            return ContestarJson::ok([
                'copias' => $almacen->listar(),
                'sigla' => $centro->codigo,
                'nombre' => $centro->nombre,
            ]);
        } catch (RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function backup(Request $request, array $vars = []): Response
    {
        $borrarMasAntigua = !empty($request->json()['borrar_mas_antigua']);
        try {
            $centro = $this->centro();
            $id = (int) $centro->id;
            $almacen = new AlmacenCopiasCentro($id);
            AsegurarHuecoCopias::ejecutar(
                $almacen->listar(),
                fn (string $nombre) => $almacen->borrarPorNombre($nombre),
                $borrarMasAntigua,
            );
            $fila = $almacen->crear($this->copias->exportar($id), $centro->codigo);

            return ContestarJson::ok($fila + ['sigla' => $centro->codigo]);
        } catch (LimiteCopiasAlcanzado $e) {
            return ContestarJson::error($e->getMessage(), 400, ['codigo' => LimiteCopiasAlcanzado::CODIGO]);
        } catch (RuntimeException | InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function descargar(Request $request, array $vars = []): Response
    {
        $nombre = trim((string) ($request->query('fichero') ?? ''));
        if ($nombre === '') {
            return ContestarJson::error(_('Indique el fichero a descargar'), 400);
        }
        try {
            $ruta = (new AlmacenCopiasCentro($this->centroId()))->rutaDeNombre($nombre);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }

        return new Response(
            (string) file_get_contents($ruta),
            200,
            [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . basename($ruta) . '"',
                'Content-Length' => (string) filesize($ruta),
            ],
        );
    }

    public function restore(Request $request, array $vars = []): Response
    {
        try {
            if (empty($request->input('confirmar'))) {
                throw new InvalidArgumentException(_('Confirme la restauración'));
            }
            $centroId = $this->centroId();
            $snapshot = $this->snapshot($request, $centroId);
            $n = $this->copias->restaurar($centroId, $snapshot);

            return ContestarJson::ok([
                'mensaje' => sprintf(_('Restauración de este centro completada (%d asientos).'), $n),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function borrar(Request $request, array $vars = []): Response
    {
        try {
            $nombre = trim((string) ($request->json()['fichero'] ?? ''));
            (new AlmacenCopiasCentro($this->centroId()))->borrarPorNombre($nombre);

            return ContestarJson::ok(['mensaje' => _('Copia borrada del servidor.')]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }
    }

    private function centro(): \src\ambito\domain\entity\Centro
    {
        $centro = $this->centros->porId($this->centroId());
        if ($centro === null || $centro->id === null) {
            throw new RuntimeException(_('Centro no encontrado'));
        }

        return $centro;
    }

    private function centroId(): int
    {
        return $this->ambito->ejecutar()->centroId;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Request $request, int $centroId): array
    {
        $file = $request->file('dump');
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string) ($file['tmp_name'] ?? '');
            $raw = $tmp !== '' ? file_get_contents($tmp) : false;
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($decoded)) {
                throw new InvalidArgumentException(_('El fichero no es una copia de centro (.json)'));
            }

            return $decoded;
        }
        $nombre = trim((string) $request->input('fichero', ''));
        if ($nombre === '') {
            throw new InvalidArgumentException(_('Indique una copia de este centro o súbala'));
        }

        return (new AlmacenCopiasCentro($centroId))->leer($nombre);
    }
}
