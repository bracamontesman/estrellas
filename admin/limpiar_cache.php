<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/Cache.php';

session_start();

if (!es_admin()) {
    redirigir('admin/login.php');
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    cache_clear();
    $mensaje = 'Caché limpiado correctamente';
}

require_once __DIR__ . '/header.php';
?>

<div class="admin-container">
    <h1>Mantenimiento de Caché</h1>

    <?php if ($mensaje): ?>
        <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <div class="cache-info">
        <p>El sistema de caché almacena temporalmente los resultados de las consultas más frecuentes para mejorar el rendimiento del sitio.</p>
        <p>Usa esta función si notas que los cambios no se reflejan inmediatamente en el sitio.</p>
    </div>

    <form method="POST" class="admin-form">
        <button type="submit" class="btn-accion">Limpiar Caché</button>
    </form>

    <div class="admin-menu">
        <a href="index.php">← Volver al Panel</a>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>