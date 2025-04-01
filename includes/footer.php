<?php
// Asegurarnos de tener acceso a la base de datos
if (!isset($conexion)) {
    $db = Database::getInstance();
    $conexion = $db->getConexion();
}

// Obtener contadores desde caché
$cache_key = 'contador_visitas_total';
$total_visitas = cache_get($cache_key);

if ($total_visitas === false) {
    try {
        $total_visitas = $conexion->query("
            SELECT SUM(total) FROM visitas_diarias
        ")->fetchColumn() ?: 0;
        cache_set($cache_key, $total_visitas, 300);
    } catch (Exception $e) {
        $total_visitas = 0;
        error_log("Error al obtener total de visitas: " . $e->getMessage());
    }
}

$cache_key = 'contador_visitas_hoy';
$visitas_hoy = cache_get($cache_key);

if ($visitas_hoy === false) {
    try {
        $visitas_hoy = $conexion->query("
            SELECT total FROM visitas_diarias 
            WHERE fecha = CURRENT_DATE
        ")->fetchColumn() ?: 0;
        cache_set($cache_key, $visitas_hoy, 300);
    } catch (Exception $e) {
        $visitas_hoy = 0;
        error_log("Error al obtener visitas de hoy: " . $e->getMessage());
    }
}
?>

<div class="footer">
    <p>© <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - Mejor visualizado en navegador Falkon 23.08.5</p>
    <p>Última actualización: <?php echo formato_fecha(date('Y-m-d')); ?></p>
    <div class="visitor-counter">
        <div>Total de visitas: <?php echo number_format($total_visitas); ?></div>
    </div>
    <p>
        <a href="<?php echo obtener_url_base(); ?>/sitemap.php">Mapa del Sitio</a> |
        <a href="<?php echo obtener_url_base(); ?>/rss.php">RSS</a>
    </p>
</div>
</div><!-- fin .container -->
</body>
</html>