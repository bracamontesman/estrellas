<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/Cache.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navegacion.php';

// Obtener ID de la entrada
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $db = Database::getInstance();
    $conexion = $db->getConexion();
    
    // Intentar obtener la entrada desde el caché
    $cache_key = get_cache_key('entrada_completa', ['id' => $id]);
    $entrada = cache_get($cache_key);

    if ($entrada === false) {
        // Si no está en caché, obtener de la base de datos
        $stmt = $conexion->prepare("
            SELECT e.*, u.usuario as autor_nombre,
            GROUP_CONCAT(DISTINCT c.nombre) as categorias,
            GROUP_CONCAT(DISTINCT c.slug) as categoria_slugs
            FROM entradas e
            LEFT JOIN usuarios u ON e.autor_id = u.id
            LEFT JOIN entradas_categorias ec ON e.id = ec.entrada_id
            LEFT JOIN categorias c ON ec.categoria_id = c.id
            WHERE e.id = ? AND e.estado = 'publicado'
            GROUP BY e.id
        ");
        $stmt->execute([$id]);
        $entrada = $stmt->fetch();
        
        if ($entrada) {
            cache_set($cache_key, $entrada, 3600); // Caché por 1 hora
        }
    }

    if (!$entrada) {
        throw new Exception('Entrada no encontrada');
    }

    // Obtener tags de la entrada
    $cache_key = get_cache_key('entrada_tags', ['id' => $id]);
    $tags = cache_get($cache_key);

    if ($tags === false) {
        $stmt = $conexion->prepare("
            SELECT t.nombre, t.slug
            FROM tags t
            JOIN entradas_tags et ON t.id = et.tag_id
            WHERE et.entrada_id = ?
            ORDER BY t.nombre
        ");
        $stmt->execute([$id]);
        $tags = $stmt->fetchAll();
        
        cache_set($cache_key, $tags, 3600);
    }

    // Obtener entradas relacionadas
    $cache_key = get_cache_key('entradas_relacionadas', ['id' => $id]);
    $entradas_relacionadas = cache_get($cache_key);

    if ($entradas_relacionadas === false) {
        $categorias_array = explode(',', $entrada['categorias']);
        if (!empty($categorias_array)) {
            $placeholders = str_repeat('?,', count($categorias_array) - 1) . '?';
            
            $stmt = $conexion->prepare("
                SELECT DISTINCT e.id, e.titulo
                FROM entradas e
                JOIN entradas_categorias ec ON e.id = ec.entrada_id
                JOIN categorias c ON ec.categoria_id = c.id
                WHERE c.nombre IN ($placeholders)
                AND e.id != ?
                AND e.estado = 'publicado'
                LIMIT 5
            ");
            
            $params = array_merge($categorias_array, [$entrada['id']]);
            $stmt->execute($params);
            $entradas_relacionadas = $stmt->fetchAll();
            
            cache_set($cache_key, $entradas_relacionadas, 3600);
        }
    }

    // Obtener comentarios aprobados
    $cache_key = get_cache_key('comentarios_entrada', ['id' => $id]);
    $comentarios = cache_get($cache_key);

    if ($comentarios === false) {
        $stmt = $conexion->prepare("
            SELECT * FROM comentarios 
            WHERE entrada_id = ? AND estado = 'aprobado'
            ORDER BY fecha_creacion DESC
        ");
        $stmt->execute([$id]);
        $comentarios = $stmt->fetchAll();
        
        cache_set($cache_key, $comentarios, 1800); // Caché por 30 minutos
    }

} catch (Exception $e) {
    $error = 'No se pudo encontrar la entrada solicitada';
}

// Procesar nuevo comentario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comentar'])) {
    $nombre = limpiar_entrada($_POST['nombre'] ?? '');
    $email = limpiar_entrada($_POST['email'] ?? '');
    $contenido = limpiar_entrada($_POST['contenido'] ?? '');
    
    $errores = [];
    
    if (empty($nombre)) $errores[] = "El nombre es obligatorio";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "Email inválido";
    if (empty($contenido)) $errores[] = "El comentario no puede estar vacío";
    
    if (empty($errores)) {
        try {
            $stmt = $conexion->prepare("
                INSERT INTO comentarios (entrada_id, nombre, email, contenido, estado)
                VALUES (?, ?, ?, ?, 'pendiente')
            ");
            $stmt->execute([$entrada['id'], $nombre, $email, $contenido]);
            $mensaje_exito = "¡Gracias por tu comentario! Será revisado antes de ser publicado.";
            
            // Limpiar caché de comentarios
            cache_delete(get_cache_key('comentarios_entrada', ['id' => $id]));
        } catch (Exception $e) {
            $errores[] = "Error al guardar el comentario";
        }
    }
}
?>

<div class="content">
    <table class="content-table">
        <tr>
            <td class="sidebar">
                <div class="sidebar-content">
                    <!-- Categorías -->
                    <h3>Categorías</h3>
                    <?php
                    // Categorías
                    $cache_key = get_cache_key('categorias_sidebar');
                    $categorias = cache_get($cache_key);

                    if ($categorias === false) {
                        $categorias = $conexion->query("
                            SELECT c.*, COUNT(DISTINCT e.id) as total
                            FROM categorias c
                            LEFT JOIN entradas_categorias ec ON c.id = ec.categoria_id
                            LEFT JOIN entradas e ON ec.entrada_id = e.id 
                            WHERE e.estado = 'publicado' OR e.estado IS NULL
                            GROUP BY c.id
                            ORDER BY c.nombre
                        ")->fetchAll();
                        
                        cache_set($cache_key, $categorias, 300); // 5 minutos
                    }
                    ?>
                    <ul>
                        <?php foreach ($categorias as $categoria): ?>
                            <li>
                                <a href="categoria.php?slug=<?php echo urlencode($categoria['slug']); ?>">
                                    <?php echo htmlspecialchars($categoria['nombre']); ?>
                                    (<?php echo $categoria['total']; ?>)
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (!empty($entradas_relacionadas)): ?>
                        <h3>Entradas Relacionadas</h3>
                        <ul>
                            <?php foreach ($entradas_relacionadas as $relacionada): ?>
                                <li>
                                    <a href="entrada.php?id=<?php echo $relacionada['id']; ?>">
                                        <?php echo htmlspecialchars($relacionada['titulo']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </td>
            <td class="main-content">
                <?php if (isset($error)): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php else: ?>
                    <article class="entrada-completa">
                        <h1><?php echo htmlspecialchars($entrada['titulo']); ?></h1>
                        
                        <div class="entrada-meta">
                            Publicado el <?php echo formato_fecha($entrada['fecha_creacion']); ?>
                            por <?php echo htmlspecialchars($entrada['autor_nombre']); ?>
                            <br>
                            <?php if ($entrada['categorias']): ?>
                                Categoría: 
                                <?php
                                $cats = explode(',', $entrada['categorias']);
                                $slugs = explode(',', $entrada['categoria_slugs']);
                                $links = array();
                                foreach ($cats as $i => $cat) {
                                    $links[] = '<a href="blog.php?categoria=' . urlencode($slugs[$i]) . '">' . 
                                             htmlspecialchars($cat) . '</a>';
                                }
                                echo implode(', ', $links);
                                ?>
                            <?php endif; ?>
                        </div>
                        <br>
                        <div class="entrada-contenido">
                            <?php echo nl2br(htmlspecialchars($entrada['contenido'])); ?>
                        </div>

                        <?php if (!empty($tags)): ?>
                            <div class="entrada-tags">
                                <span>Etiquetas:</span>
                                <?php foreach ($tags as $tag): ?>
                                    <a href="tag.php?slug=<?php echo urlencode($tag['slug']); ?>" 
                                       class="tag-link">
                                        <?php echo htmlspecialchars($tag['nombre']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Sistema de comentarios -->
                        <div class="comentarios-seccion">
                            <h3>Comentarios</h3>
                            
                            <?php if ($comentarios): ?>
                                <div class="comentarios-lista">
                                    <?php foreach ($comentarios as $comentario): ?>
                                        <div class="comentario">
                                            <div class="comentario-meta">
                                                <strong><?php echo htmlspecialchars($comentario['nombre']); ?></strong>
                                                escribió el <?php echo formato_fecha($comentario['fecha_creacion']); ?>:
                                            </div>
                                            <div class="comentario-contenido">
                                                <?php echo nl2br(htmlspecialchars($comentario['contenido'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p>No hay comentarios aún. ¡Sé el primero en comentar!</p>
                            <?php endif; ?>

                            <div class="formulario-comentario">
                                <h4>Deja un comentario</h4>
                                
                                <?php if (isset($mensaje_exito)): ?>
                                    <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje_exito); ?></div>
                                <?php endif; ?>

                                <?php if (!empty($errores)): ?>
                                    <div class="mensaje-error">
                                        <?php echo implode("<br>", array_map('htmlspecialchars', $errores)); ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" class="comentario-form">
                                    <div class="form-grupo">
                                        <label for="nombre">Nombre:</label>
                                        <div class="campo-input">
                                            <input type="text" name="nombre" id="nombre" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-grupo">
                                        <label for="email">Email:</label>
                                        <div class="campo-input">
                                            <input type="email" name="email" id="email" required>
                                            <small>(No será publicado)</small>
                                        </div>
                                    </div>
                                    
                                    <div class="form-grupo">
                                        <label for="contenido">Comentario:</label>
                                        <div class="campo-input">
                                            <textarea name="contenido" id="contenido" rows="5" required></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="form-grupo-botones">
                                        <button type="submit" name="comentar" class="btn-comentar">
                                            Enviar Comentario
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="entrada-navegacion">
                            <a href="blog.php" class="btn-volver">← Volver al Blog</a>
                        </div>
                    </article>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>