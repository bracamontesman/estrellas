<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/funciones.php';

session_start();

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
    
    switch ($accion) {
        case 'crear':
        case 'editar':
            $nombre = limpiar_entrada($_POST['nombre'] ?? '');
            $descripcion = limpiar_entrada($_POST['descripcion'] ?? '');
            $slug = crear_slug($nombre);

            if (empty($nombre)) {
                $error = 'El nombre es obligatorio';
            } else {
                try {
                    if ($accion == 'crear') {
                        $stmt = $conexion->prepare("
                            INSERT INTO tags (nombre, slug, descripcion) 
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$nombre, $slug, $descripcion]);
                        $mensaje = 'Etiqueta creada correctamente';
                    } else {
                        $tag_id = $_POST['tag_id'] ?? 0;
                        $stmt = $conexion->prepare("
                            UPDATE tags 
                            SET nombre = ?, slug = ?, descripcion = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$nombre, $slug, $descripcion, $tag_id]);
                        $mensaje = 'Etiqueta actualizada correctamente';
                    }
                } catch (Exception $e) {
                    $error = 'Error al ' . ($accion == 'crear' ? 'crear' : 'actualizar') . ' la etiqueta';
                }
            }
            break;

        case 'eliminar':
            $tag_id = $_POST['tag_id'] ?? 0;
            try {
                $stmt = $conexion->prepare("DELETE FROM tags WHERE id = ?");
                $stmt->execute([$tag_id]);
                $mensaje = 'Etiqueta eliminada correctamente';
            } catch (Exception $e) {
                $error = 'Error al eliminar la etiqueta';
            }
            break;
    }
}

// Obtener todas las etiquetas
$tags = $conexion->query("
    SELECT t.*, COUNT(et.entrada_id) as total_entradas
    FROM tags t
    LEFT JOIN entradas_tags et ON t.id = et.tag_id
    GROUP BY t.id
    ORDER BY t.nombre
")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="admin-container">
    <h1>Gestionar Etiquetas</h1>

    <?php if ($mensaje): ?>
        <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mensaje-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <button class="btn-accion" onclick="mostrarFormulario()">Nueva Etiqueta</button>

    <div id="formulario" style="display: none;">
        <h2>Nueva Etiqueta</h2>
        <form method="POST" class="admin-form">
            <input type="hidden" name="accion" value="crear">
            
            <div class="form-grupo">
                <label>Nombre:</label>
                <input type="text" name="nombre" required>
            </div>

            <div class="form-grupo">
                <label>Descripción:</label>
                <textarea name="descripcion" rows="3"></textarea>
            </div>

            <button type="submit" class="btn-accion">Guardar Etiqueta</button>
        </form>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Slug</th>
                <th>Descripción</th>
                <th>Total Entradas</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tags as $tag): ?>
                <tr>
                    <td><?php echo htmlspecialchars($tag['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($tag['slug']); ?></td>
                    <td><?php echo htmlspecialchars($tag['descripcion']); ?></td>
                    <td><?php echo $tag['total_entradas']; ?></td>
                    <td class="acciones">
                        <button class="btn-accion" onclick="editarTag(<?php echo $tag['id']; ?>)">Editar</button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de querer eliminar esta etiqueta?');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="tag_id" value="<?php echo $tag['id']; ?>">
                            <button type="submit" class="btn-accion btn-eliminar">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="admin-menu">
        <a href="index.php">← Volver al Panel</a>
    </div>
</div>

<script>
    function mostrarFormulario() {
        document.getElementById('formulario').style.display = 'block';
    }

    function editarTag(id) {
        // Implementaremos la edición más adelante
        alert('Función de edición en desarrollo');
    }
</script>

</body>
</html>