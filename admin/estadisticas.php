<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/contador.php';

session_start();

if (!es_admin()) {
    redirigir('admin/login.php');
}

$estadisticas = obtener_estadisticas_visitas();
require_once __DIR__ . '/header.php';
?>

<div class="admin-container">
    <h1>Estadísticas de Visitas</h1>

    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-number"><?php echo number_format(obtener_visitas_hoy()); ?></div>
            <div>Visitas Hoy</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo number_format(obtener_visitas_totales()); ?></div>
            <div>Visitas Totales</div>
        </div>
    </div>

    <div class="stats-section">
        <h2>Últimos 7 días</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Visitas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($estadisticas['ultimos_dias'] as $dia): ?>
                    <tr>
                        <td><?php echo formato_fecha($dia['fecha']); ?></td>
                        <td><?php echo number_format($dia['total']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="stats-section">
        <h2>Páginas más visitadas</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Página</th>
                    <th>Visitas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($estadisticas['paginas_populares'] as $pagina): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($pagina['pagina']); ?></td>
                        <td><?php echo number_format($pagina['total']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="admin-menu">
        <a href="index.php">← Volver al Panel</a>
    </div>
</div>

</body>
</html>