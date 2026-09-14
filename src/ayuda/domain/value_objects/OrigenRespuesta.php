<?php

declare(strict_types=1);

namespace src\ayuda\domain\value_objects;

enum OrigenRespuesta: string
{
    /** Contestada por el modelo en esta petición. */
    case Ia = 'ia';
    /** Ya se había preguntado lo mismo con este mismo manual. */
    case Cache = 'cache';
    /** El modelo no estaba disponible: se devuelven los apartados que encajan. */
    case Busqueda = 'busqueda';
}
