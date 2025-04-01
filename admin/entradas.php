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

// Obtener mensajes de la URL
$mensaje = $_GET['mensaje'] ?? '';
$error = $_GET['error'] ?? '';

// Obtener categorías y tags para el formulario
$categorias = $conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll();
$tags = $conexion->query("SELECT id, nombre FROM tags ORDER BY nombre")->fetchAll();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'crear':
        case 'editar':
            $titulo = limpiar_entrada($_POST['titulo'] ?? '');
            $contenido = $_POST['contenido'] ?? '';
            $extracto = limpiar_entrada($_POST['extracto'] ?? '');
            $estado = $_POST['estado'] ?? 'borrador';
            $categorias_seleccionadas = $_POST['categorias'] ?? [];
            $tags_seleccionados = $_POST['tags'] ?? [];

            if (empty($titulo) || empty($contenido)) {
                header('Location: entradas.php?error=' . urlencode('El título y el contenido son obligatorios'));
                exit;
            }

            try {
                $conexion->beginTransaction();

                if ($accion == 'crear') {
                    $stmt = $conexion->prepare("
                        INSERT INTO entradas (titulo, contenido, extracto, autor_id, estado, fecha_creacion) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$titulo, $contenido, $extracto, $_SESSION['admin_id'], $estado]);
                    $entrada_id = $conexion->lastInsertId();
                } else {
                    $entrada_id = $_POST['entrada_id'] ?? 0;
                    $stmt = $conexion->prepare("
                        UPDATE entradas 
                        SET titulo = ?, contenido = ?, extracto = ?, estado = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$titulo, $contenido, $extracto, $estado, $entrada_id]);
                }

                // Procesar categorías
                if ($accion == 'editar') {
                    $stmt = $conexion->prepare("DELETE FROM entradas_categorias WHERE entrada_id = ?");
                    $stmt->execute([$entrada_id]);
                }
                if (!empty($categorias_seleccionadas)) {
                    $stmt = $conexion->prepare("INSERT INTO entradas_categorias (entrada_id, categoria_id) VALUES (?, ?)");
                    foreach ($categorias_seleccionadas as $categoria_id) {
                        $stmt->execute([$entrada_id, $categoria_id]);
                    }
                }

                // Procesar tags
                if ($accion == 'editar') {
                    $stmt = $conexion->prepare("DELETE FROM entradas_tags WHERE entrada_id = ?");
                    $stmt->execute([$entrada_id]);
                }
                if (!empty($tags_seleccionados)) {
                    $stmt = $conexion->prepare("INSERT INTO entradas_tags (entrada_id, tag_id) VALUES (?, ?)");
                    foreach ($tags_seleccionados as $tag_id) {
                        $stmt->execute([$entrada_id, $tag_id]);
                    }
                }

                $conexion->commit();
                cache_clear();
                
                $mensaje = 'Entrada ' . ($accion == 'crear' ? 'creada' : 'actualizada') . ' correctamente';
                header('Location: entradas.php?mensaje=' . urlencode($mensaje));
                exit;

            } catch (Exception $e) {
                $conexion->rollBack();
                $error = 'Error al ' . ($accion == 'crear' ? 'crear' : 'actualizar') . ' la entrada';
                header('Location: entradas.php?error=' . urlencode($error));
                exit;
            }
            break;

        case 'eliminar':
            $entrada_id = $_POST['entrada_id'] ?? 0;
            try {
                $conexion->beginTransaction();
                
                // Eliminar relaciones
                $stmt = $conexion->prepare("DELETE FROM entradas_categorias WHERE entrada_id = ?");
                $stmt->execute([$entrada_id]);
                
                $stmt = $conexion->prepare("DELETE FROM entradas_tags WHERE entrada_id = ?");
                $stmt->execute([$entrada_id]);
                
                // Eliminar la entrada
                $stmt = $conexion->prepare("DELETE FROM entradas WHERE id = ?");
                $stmt->execute([$entrada_id]);
                
                $conexion->commit();
                cache_clear();
                
                header('Location: entradas.php?mensaje=' . urlencode('Entrada eliminada correctamente'));
                exit;
            } catch (Exception $e) {
                $conexion->rollBack();
                header('Location: entradas.php?error=' . urlencode('Error al eliminar la entrada'));
                exit;
            }
            break;
    }
}
// Obtener todas las entradas con sus categorías y tags
$entradas = $conexion->query("
    SELECT e.*, u.usuario as autor_nombre,
    GROUP_CONCAT(DISTINCT c.nombre) as categorias_nombres,
    GROUP_CONCAT(DISTINCT t.nombre) as tags_nombres
    FROM entradas e 
    LEFT JOIN usuarios u ON e.autor_id = u.id 
    LEFT JOIN entradas_categorias ec ON e.id = ec.entrada_id
    LEFT JOIN categorias c ON ec.categoria_id = c.id
    LEFT JOIN entradas_tags et ON e.id = et.entrada_id
    LEFT JOIN tags t ON et.tag_id = t.id
    GROUP BY e.id
    ORDER BY e.fecha_creacion DESC
")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="admin-container">
    <h1>Gestionar Entradas</h1>

    <?php if ($mensaje): ?>
        <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mensaje-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <button class="btn-accion" onclick="mostrarFormulario()">Nueva Entrada</button>
    <button class="btn-accion" onclick="limpiarCache()">Actualizar Caché</button>


    <div id="formulario" style="display: none;">
        <h2>Nueva Entrada</h2>
        <form method="POST" action="entradas.php" class="admin-form">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="entrada_id" value="">
            
            <div class="form-grupo">
                <label>Título:</label>
                <input type="text" name="titulo" required>
            </div>

            <div class="form-grupo">
                <label>Extracto:</label>
                <textarea name="extracto" rows="3"></textarea>
            </div>

            <div class="form-grupo">
                <label>Contenido:</label>
                <textarea name="contenido" required rows="10"></textarea>
            </div>

            <div class="form-grupo">
                <label>Estado:</label>
                <select name="estado">
                    <option value="borrador">Borrador</option>
                    <option value="publicado">Publicado</option>
                </select>
            </div>

            <div class="form-grupo">
                <label>Categorías:</label>
                <div class="categorias-lista">
                    <?php foreach ($categorias as $categoria): ?>
                        <div class="categoria-item">
                            <input type="checkbox" name="categorias[]" 
                                   id="cat_<?php echo $categoria['id']; ?>" 
                                   value="<?php echo $categoria['id']; ?>">
                            <label for="cat_<?php echo $categoria['id']; ?>">
                                <?php echo htmlspecialchars($categoria['nombre']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-grupo">
                <label>Etiquetas:</label>
                <div class="tags-lista">
                    <?php foreach ($tags as $tag): ?>
                        <div class="tag-item">
                            <input type="checkbox" name="tags[]" 
                                   id="tag_<?php echo $tag['id']; ?>" 
                                   value="<?php echo $tag['id']; ?>">
                            <label for="tag_<?php echo $tag['id']; ?>">
                                <?php echo htmlspecialchars($tag['nombre']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn-accion">Guardar Entrada</button>
        </form>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Título</th>
                <th>Autor</th>
                <th>Categorías</th>
                <th>Etiquetas</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entradas as $entrada): ?>
                <tr>
                    <td><?php echo htmlspecialchars($entrada['titulo']); ?></td>
                    <td><?php echo htmlspecialchars($entrada['autor_nombre']); ?></td>
                    <td><?php echo htmlspecialchars($entrada['categorias_nombres'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($entrada['tags_nombres'] ?? ''); ?></td>
                    <td><?php echo formato_fecha($entrada['fecha_creacion']); ?></td>
                    <td>
                        <span class="estado estado-<?php echo $entrada['estado']; ?>">
                            <?php echo ucfirst($entrada['estado']); ?>
                        </span>
                    </td>
                    <td class="acciones">
                        <button class="btn-accion" onclick="editarEntrada(<?php echo $entrada['id']; ?>)">Editar</button>
                        <form method="POST" action="entradas.php" style="display: inline;" 
                              onsubmit="return confirm('¿Estás seguro de querer eliminar esta entrada?');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="entrada_id" value="<?php echo $entrada['id']; ?>">
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

<script>
    function limpiarCache() {
    if (confirm('¿Deseas actualizar el caché del blog?')) {
    fetch('limpiar_cache.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Caché limpiado correctamente');
            } else {
                alert('Error al limpiar el caché');
            }
        })
        .catch(error => {
            alert('Error al limpiar el caché');
            console.error('Error:', error);
        });
    }
    }
    function mostrarFormulario() {
    // Limpiar formulario
    document.querySelector('input[name="titulo"]').value = '';
    document.querySelector('textarea[name="extracto"]').value = '';
    document.querySelector('textarea[name="contenido"]').value = '';
    document.querySelector('select[name="estado"]').value = 'borrador';
    document.querySelector('input[name="accion"]').value = 'crear';
    document.querySelector('input[name="entrada_id"]').value = '';
    
    // Desmarcar todas las categorías y tags
    document.querySelectorAll('input[name="categorias[]"], input[name="tags[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Cambiar el título del formulario
    document.querySelector('#formulario h2').textContent = 'Nueva Entrada';
    
    document.getElementById('formulario').style.display = 'block';
    window.scrollTo(0, document.getElementById('formulario').offsetTop);
}

function editarEntrada(id) {
    // Mostrar el formulario primero
    document.getElementById('formulario').style.display = 'block';
    window.scrollTo(0, document.getElementById('formulario').offsetTop);

    // Cambiar el título del formulario
    document.querySelector('#formulario h2').textContent = 'Editar Entrada';

    fetch('obtener_entrada.php?id=' + id)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al obtener los datos');
            }
            return response.json();
        })
        .then(data => {
            // Llenar el formulario con los datos
            document.querySelector('input[name="titulo"]').value = data.titulo;
            document.querySelector('textarea[name="extracto"]').value = data.extracto || '';
            document.querySelector('textarea[name="contenido"]').value = data.contenido;
            document.querySelector('select[name="estado"]').value = data.estado;
            document.querySelector('input[name="accion"]').value = 'editar';
            document.querySelector('input[name="entrada_id"]').value = id;
            
            // Desmarcar todas las categorías y tags
            document.querySelectorAll('input[name="categorias[]"], input[name="tags[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Marcar las categorías de la entrada
            if (data.categorias) {
                data.categorias.split(',').forEach(catId => {
                    const checkbox = document.querySelector(`input[name="categorias[]"][value="${catId}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }
            
            // Marcar los tags de la entrada
            if (data.tags) {
                data.tags.split(',').forEach(tagId => {
                    const checkbox = document.querySelector(`input[name="tags[]"][value="${tagId}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }
        })
        .catch(error => {
            alert('Error al cargar los datos de la entrada');
            console.error('Error:', error);
        });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>