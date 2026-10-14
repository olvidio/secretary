<h1><?= _("Mensajes") ?></h1>
<p class="muted"><?= _("Avisos del centro: destinos de labores y peticiones de detalle de remesa. Desaparecen al aceptar una remesa con esas partidas 7. Quitar retira la instrucción (también en Remesa) y devuelve el disponible en el centro.") ?></p>
<p id="mensajes-err" class="error" hidden></p>
<ul id="mensajes-lista" class="mensajes-lista"></ul>
<p id="mensajes-vacio" class="muted" hidden><?= _("No hay mensajes.") ?></p>
<script src="/js/mensajes.js?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/js/mensajes.js') ?: 0) ?>"></script>
