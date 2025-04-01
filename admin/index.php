<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/funciones.php';

session_start();

// Verificar si el usuario está logueado
if (!es_admin()) {
    redirigir('admin/login.php');
}

// Incluir el header específico del admin
require_once __DIR__ . '/header.php';

// Obtener estadísticas básicas
try {
    $db = Database::getInstance();
    $conexion = $db->getConexion();
    
    $stats = [
        'entradas' => $conexion->query("SELECT COUNT(*) FROM entradas")->fetchColumn(),
        'comentarios' => $conexion->query("SELECT COUNT(*) FROM comentarios")->fetchColumn(),
        'visitas' => $conexion->query("SELECT COUNT(*) FROM libro_visitas")->fetchColumn(),
        'categorias' => $conexion->query("SELECT COUNT(*) FROM categorias")->fetchColumn()
    ];
} catch (PDOException $e) {
    $error = 'Error al obtener estadísticas.';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Panel de Administración - <?php echo SITE_NAME; ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="admin-container">
        <div class="logout-button">
            <a href="logout.php" style="color: #FF6666; text-decoration: none;">Cerrar Sesión</a>
        </div>

        <h1>Panel de Administración</h1>
        <h2><?php echo SITE_NAME; ?></h2>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-number"><?php echo $stats['entradas']; ?></div>
                <div>Entradas</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $stats['comentarios']; ?></div>
                <div>Comentarios</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $stats['visitas']; ?></div>
                <div>Visitas</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $stats['categorias']; ?></div>
                <div>Categorías</div>
            </div>
        </div>

        <div class="admin-menu">
            <a href="entradas.php">Gestionar Entradas</a>
            <a href="categorias.php">Gestionar Categorías</a>
            <a href="tags.php">Etiquetas</a>
            <a href="comentarios.php">Moderar Comentarios</a>
            <a href="libro-visitas.php">Libro de Visitas</a>
            <a href="estadisticas.php">Estadísticas</a>
            <a href="limpiar_cache.php">Mantenimiento de Caché</a>
            <a href="minificar_css.php">Minificar CSS</a>
        </div>

        <div class="back-link">
            <a href="<?php echo obtener_url_base(); ?>">← Volver al sitio</a>
        </div>
    </div>
</body>
</html>