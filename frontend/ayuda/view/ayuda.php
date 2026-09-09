<h1>Ayuda</h1>
<div class="ayuda">
<?php
$f = dirname(__DIR__, 3) . '/docs/manual/ayuda_schema.md';
echo nl2br(htmlspecialchars(is_file($f) ? (string) file_get_contents($f) : '', ENT_QUOTES));
?>
</div>
