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
                            INSERT INTO categorias (nombre, slug, descripcion) 
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$nombre, $slug, $descripcion]);
                        $mensaje = 'Categoría creada correctamente';
                    } else {
                        $categoria_id = $_POST['categoria_id'] ?? 0;
                        $stmt = $conexion->prepare("
                            UPDATE categorias 
                            SET nombre = ?, slug = ?, descripcion = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$nombre, $slug, $descripcion, $categoria_id]);
                        $mensaje = 'Categoría actualizada correctamente';
                    }
                    // Limpiar caché relacionado con categorías
                    cache_clear();
                } catch (Exception $e) {
                    $error = 'Error al ' . ($accion == 'crear' ? 'crear' : 'actualizar') . ' la categoría';
                }
            }
            break;

        case 'eliminar':
            $categoria_id = $_POST['categoria_id'] ?? 0;
            try {
                $stmt = $conexion->prepare("DELETE FROM categorias WHERE id = ?");
                $stmt->execute([$categoria_id]);
                $mensaje = 'Categoría eliminada correctamente';
                cache_clear();
            } catch (Exception $e) {
                $error = 'Error al eliminar la categoría';
            }
            break;
    }
}

// Obtener todas las categorías
$categorias = $conexion->query("
    SELECT c.*, COUNT(ec.entrada_id) as total_entradas
    FROM categorias c
    LEFT JOIN entradas_categorias ec ON c.id = ec.categoria_id
    GROUP BY c.id
    ORDER BY c.nombre
")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="admin-container">
    <h1>Gestionar Categorías</h1>

    <?php if ($mensaje): ?>
        <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mensaje-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <button class="btn-accion" onclick="mostrarFormulario()">Nueva Categoría</button>

    <div id="formulario" style="display: none;">
    <h2>Nueva Categoría</h2>
    <form method="POST" action="categorias.php" class="admin-form">
        <input type="hidden" name="accion" value="crear">
        
        <div class="form-grupo">
            <label>Nombre:</label>
            <input type="text" name="nombre" required>
        </div>

        <div class="form-grupo">
            <label>Descripción:</label>
            <textarea name="descripcion" rows="3"></textarea>
        </div>

        <button type="submit" class="btn-accion">Guardar Categoría</button>
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
            <?php foreach ($categorias as $categoria): ?>
                <tr>
                    <td><?php echo htmlspecialchars($categoria['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($categoria['slug']); ?></td>
                    <td><?php echo htmlspecialchars($categoria['descripcion']); ?></td>
                    <td><?php echo $categoria['total_entradas']; ?></td>
                    <td class="acciones">
                        <button class="btn-accion" onclick="editarCategoria(<?php echo $categoria['id']; ?>)">Editar</button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de querer eliminar esta categoría?');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="categoria_id" value="<?php echo $categoria['id']; ?>">
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

    function editarCategoria(id) {
        // Implementaremos la edición más adelante
        alert('Función de edición en desarrollo');
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>