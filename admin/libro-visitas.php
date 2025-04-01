<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/funciones.php';

session_start();

// Verificar si el usuario está logueado
if (!es_admin()) {
    redirigir('admin/login.php');
}

$db = Database::getInstance();
$conexion = $db->getConexion();

// Procesar acciones
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? '';
    $firma_id = (int)($_POST['firma_id'] ?? 0);
    
    try {
        switch ($accion) {
            case 'aprobar':
                $stmt = $conexion->prepare("UPDATE libro_visitas SET estado = 'aprobado' WHERE id = ?");
                $stmt->execute([$firma_id]);
                $mensaje = "Firma aprobada correctamente";
                // Limpiar caché relacionado con el libro de visitas
                cache_clear();
                break;
                
            case 'rechazar':
                $stmt = $conexion->prepare("UPDATE libro_visitas SET estado = 'spam' WHERE id = ?");
                $stmt->execute([$firma_id]);
                $mensaje = "Firma marcada como spam";
                cache_clear();
                break;
                
            case 'eliminar':
                $stmt = $conexion->prepare("DELETE FROM libro_visitas WHERE id = ?");
                $stmt->execute([$firma_id]);
                $mensaje = "Firma eliminada correctamente";
                cache_clear();
                break;
        }
    } catch (Exception $e) {
        $error = "Error al procesar la acción";
    }
}

// Obtener firmas
$firmas = $conexion->query("
    SELECT *
    FROM libro_visitas
    ORDER BY 
        CASE estado
            WHEN 'pendiente' THEN 1
            WHEN 'aprobado' THEN 2
            ELSE 3
        END,
        fecha_creacion DESC
")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="admin-container">
    <h1>Gestionar Libro de Visitas</h1>

    <?php if ($mensaje): ?>
        <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mensaje-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-number">
                <?php 
                echo $conexion->query("SELECT COUNT(*) FROM libro_visitas WHERE estado = 'aprobado'")->fetchColumn();
                ?>
            </div>
            <div>Firmas Aprobadas</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">
                <?php 
                echo $conexion->query("SELECT COUNT(*) FROM libro_visitas WHERE estado = 'pendiente'")->fetchColumn();
                ?>
            </div>
            <div>Firmas Pendientes</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">
                <?php 
                echo $conexion->query("SELECT COUNT(*) FROM libro_visitas WHERE estado = 'spam'")->fetchColumn();
                ?>
            </div>
            <div>Spam</div>
        </div>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Autor</th>
                <th>Mensaje</th>
                <th>Website</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($firmas as $firma): ?>
                <tr class="<?php echo $firma['estado']; ?>">
                    <td><?php echo formato_fecha($firma['fecha_creacion']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($firma['nombre']); ?><br>
                        <small><?php echo htmlspecialchars($firma['email']); ?></small>
                    </td>
                    <td class="firma-contenido">
                        <?php echo htmlspecialchars($firma['mensaje']); ?>
                    </td>
                    <td>
                        <?php if ($firma['website']): ?>
                            <a href="<?php echo htmlspecialchars($firma['website']); ?>" 
                               target="_blank"
                               class="retro-link">
                                <?php echo htmlspecialchars($firma['website']); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="estado estado-<?php echo $firma['estado']; ?>">
                            <?php echo ucfirst($firma['estado']); ?>
                        </span>
                    </td>
                    <td class="acciones">
                        <?php if ($firma['estado'] != 'aprobado'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="accion" value="aprobar">
                                <input type="hidden" name="firma_id" value="<?php echo $firma['id']; ?>">
                                <button type="submit" class="btn-accion btn-aprobar">Aprobar</button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($firma['estado'] != 'spam'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="accion" value="rechazar">
                                <input type="hidden" name="firma_id" value="<?php echo $firma['id']; ?>">
                                <button type="submit" class="btn-accion btn-rechazar">Spam</button>
                            </form>
                        <?php endif; ?>
                        
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de querer eliminar esta firma?');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="firma_id" value="<?php echo $firma['id']; ?>">
                            <button type="submit" class="btn-accion btn-eliminar">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="admin-menu">
        <a href="index.php">← Volver al Panel</a>
        <a href="<?php echo obtener_url_base(); ?>">Ir al Sitio</a>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>