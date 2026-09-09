<?php

declare(strict_types=1);

namespace src\personas\domain\contracts;

use src\personas\domain\entity\Persona;

interface PersonaRepository
{
    /** @return list<Persona> */
    public function listar(): array;

    public function porId(int $id): ?Persona;

    public function porIniciales(string $iniciales): ?Persona;

    public function guardar(Persona $persona): Persona;

    public function borrar(int $id): void;

    public function borrarTodos(): void;

    /**
     * Fase 2b: sincroniza `activo` con la presencia en `$inicialesPresentes` (las
     * iniciales que sí aparecen en la importación actual). Reactiva
     * (`activo = true`) las que están presentes y desactiva (`activo = false`) las
     * que no. Es el reverso no destructivo de `borrarTodos()` que necesita el
     * importador para no romper la FK `cuentas.persona_id` (ver
     * ImportarExcelSecretario): la persona nunca se borra, así que las cuentas que
     * la referencian (p. ej. `CC.<INICIALES>`) siguen intactas y su histórico de
     * apuntes no se pierde. Deliberadamente independiente de `guardar()`: una
     * edición manual desde el formulario no debe reactivar ni desactivar a nadie
     * por sorpresa.
     *
     * Por seguridad, una lista vacía no toca a nadie (protege contra un fallo de
     * parseo del origen que deje la lista vacía por error: no tiene sentido
     * desactivar a todo el mundo por eso).
     *
     * @param list<string> $inicialesPresentes
     * @return array{activadas:int, desactivadas:int}
     */
    public function sincronizarActivos(array $inicialesPresentes): array;
}
