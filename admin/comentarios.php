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
    $comentario_id = (int)($_POST['comentario_id'] ?? 0);
    
    try {
        switch ($accion) {
            case 'aprobar':
                $stmt = $conexion->prepare("UPDATE comentarios SET estado = 'aprobado' WHERE id = ?");
                $stmt->execute([$comentario_id]);
                $mensaje = "Comentario aprobado correctamente";
                break;
                
            case 'rechazar':
                $stmt = $conexion->prepare("UPDATE comentarios SET estado = 'spam' WHERE id = ?");
                $stmt->execute([$comentario_id]);
                $mensaje = "Comentario marcado como spam";
                break;
                
            case 'eliminar':
                $stmt = $conexion->prepare("DELETE FROM comentarios WHERE id = ?");
                $stmt->execute([$comentario_id]);
                $mensaje = "Comentario eliminado correctamente";
                break;
        }
    } catch (Exception $e) {
        $error = "Error al procesar la acción";
    }
}

// Obtener comentarios con información de las entradas
$comentarios = $conexion->query("
    SELECT c.*, e.titulo as entrada_titulo, e.id as entrada_id
    FROM comentarios c
    JOIN entradas e ON c.entrada_id = e.id
    ORDER BY 
        CASE c.estado
            WHEN 'pendiente' THEN 1
            WHEN 'aprobado' THEN 2
            ELSE 3
        END,
        c.fecha_creacion DESC
")->fetchAll();

// Incluir el header específico del admin
require_once __DIR__ . '/header.php';
?>

<div class="admin-container">
    <h1>Gestionar Comentarios</h1>

    <?php if ($mensaje): ?>
        <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mensaje-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Autor</th>
                <th>Comentario</th>
                <th>Entrada</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($comentarios as $comentario): ?>
                <tr class="<?php echo $comentario['estado']; ?>">
                    <td><?php echo formato_fecha($comentario['fecha_creacion']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($comentario['nombre']); ?><br>
                        <small><?php echo htmlspecialchars($comentario['email']); ?></small>
                    </td>
                    <td class="comentario-contenido">
                        <?php echo htmlspecialchars($comentario['contenido']); ?>
                    </td>
                    <td>
                        <a href="../entrada.php?id=<?php echo $comentario['entrada_id']; ?>" target="_blank" class="retro-link">
                            <?php echo htmlspecialchars($comentario['entrada_titulo']); ?>
                        </a>
                    </td>
                    <td>
                        <span class="estado estado-<?php echo $comentario['estado']; ?>">
                            <?php echo ucfirst($comentario['estado']); ?>
                        </span>
                    </td>
                    <td class="acciones">
                        <?php if ($comentario['estado'] != 'aprobado'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="accion" value="aprobar">
                                <input type="hidden" name="comentario_id" value="<?php echo $comentario['id']; ?>">
                                <button type="submit" class="btn-accion btn-aprobar">Aprobar</button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($comentario['estado'] != 'spam'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="accion" value="rechazar">
                                <input type="hidden" name="comentario_id" value="<?php echo $comentario['id']; ?>">
                                <button type="submit" class="btn-accion btn-rechazar">Spam</button>
                            </form>
                        <?php endif; ?>
                        
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de querer eliminar este comentario?');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="comentario_id" value="<?php echo $comentario['id']; ?>">
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

</body>
</html>