<?php
function limpiar_entrada($datos) {
    $datos = trim($datos);
    $datos = stripslashes($datos);
    $datos = htmlspecialchars($datos);
    return $datos;
}

function formato_fecha($fecha) {
    if (!$fecha) return 'N/A';
    
    // Convertir la fecha UTC a la zona horaria local
    $datetime = new DateTime($fecha, new DateTimeZone('UTC'));
    $datetime->setTimezone(new DateTimeZone('America/Mexico_City'));
    
    $meses = array(
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre'
    );
    
    $dia = $datetime->format('d');
    $mes = $meses[(int)$datetime->format('n')];
    $anio = $datetime->format('Y');
    $hora = $datetime->format('H:i');
    
    return "$dia de $mes de $anio a las $hora";
}

function es_admin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function obtener_url_base() {
    return SITE_URL; // Esto debería ser 'http://estrellasyplanetas.space' según tu config/db.php
}

function redirigir($pagina) {
    header('Location: ' . obtener_url_base() . '/' . $pagina);
    exit;
}

function mostrar_mensaje($mensaje, $tipo = 'info') {
    return "<div class='mensaje $tipo'>$mensaje</div>";
}

// Función para el contador de visitas (implementaremos más tarde)
// function actualizar_contador() {
    // Por ahora retorna un número estático
//    return '1,337';
// }

function crear_slug($texto) {
    // Transliterar caracteres especiales
    $texto = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $texto);
    // Reemplazar espacios con guiones
    $texto = str_replace(' ', '-', $texto);
    // Eliminar caracteres que no sean alfanuméricos o guiones
    $texto = preg_replace('/[^a-z0-9-]/', '', $texto);
    // Eliminar guiones múltiples
    $texto = preg_replace('/-+/', '-', $texto);
    // Eliminar guiones al principio y final
    return trim($texto, '-');
}

// Añade estas funciones al archivo existente

function cache_get($key) {
    return Cache::getInstance()->get($key);
}

function cache_set($key, $content, $time = null) {
    return Cache::getInstance()->set($key, $content, $time);
}

function cache_clear() {
    return Cache::getInstance()->clear();
}

function get_cache_key($prefix, $params = []) {
    return $prefix . '_' . md5(serialize($params));
}