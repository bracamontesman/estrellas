<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/Cache.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navegacion.php';

$db = Database::getInstance();
$conexion = $db->getConexion();

// Obtener slug de la categoría
$slug = isset($_GET['slug']) ? limpiar_entrada($_GET['slug']) : '';

try {
    // Obtener información de la categoría
    $cache_key = get_cache_key('categoria_info', ['slug' => $slug]);
    $categoria = cache_get($cache_key);

    if ($categoria === false) {
        $stmt = $conexion->prepare("
            SELECT id, nombre, descripcion
            FROM categorias
            WHERE slug = ?
        ");
        $stmt->execute([$slug]);
        $categoria = $stmt->fetch();
        
        if ($categoria) {
            cache_set($cache_key, $categoria, 3600);
        }
    }

    if (!$categoria) {
        throw new Exception('Categoría no encontrada');
    }

    // Configuración de paginación
    $entradas_por_pagina = 5;
    $pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $offset = ($pagina_actual - 1) * $entradas_por_pagina;

    // Obtener total de entradas
    $cache_key = get_cache_key('categoria_total_entradas', ['id' => $categoria['id']]);
    $total_entradas = cache_get($cache_key);

    if ($total_entradas === false) {
        $stmt = $conexion->prepare("
            SELECT COUNT(DISTINCT e.id)
            FROM entradas e
            JOIN entradas_categorias ec ON e.id = ec.entrada_id
            WHERE ec.categoria_id = ? AND e.estado = 'publicado'
        ");
        $stmt->execute([$categoria['id']]);
        $total_entradas = $stmt->fetchColumn();
        
        cache_set($cache_key, $total_entradas, 1800);
    }

    // Obtener entradas para esta página
    $cache_key = get_cache_key('categoria_entradas', [
        'id' => $categoria['id'],
        'pagina' => $pagina_actual
    ]);
    $entradas = cache_get($cache_key);

    if ($entradas === false) {
        $stmt = $conexion->prepare("
            SELECT e.*, u.usuario as autor_nombre
            FROM entradas e
            JOIN entradas_categorias ec ON e.id = ec.entrada_id
            LEFT JOIN usuarios u ON e.autor_id = u.id
            WHERE ec.categoria_id = ? AND e.estado = 'publicado'
            ORDER BY e.fecha_creacion DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$categoria['id'], $entradas_por_pagina, $offset]);
        $entradas = $stmt->fetchAll();
        
        cache_set($cache_key, $entradas, 1800);
    }

} catch (Exception $e) {
    $error = 'No se pudo encontrar la categoría solicitada';
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
                    $cache_key = get_cache_key('categorias_sidebar');
                    $categorias_sidebar = cache_get($cache_key);

                    if ($categorias_sidebar === false) {
                        $categorias_sidebar = $conexion->query("
                            SELECT c.*, COUNT(ec.entrada_id) as total
                            FROM categorias c
                            LEFT JOIN entradas_categorias ec ON c.id = ec.categoria_id
                            LEFT JOIN entradas e ON ec.entrada_id = e.id AND e.estado = 'publicado'
                            GROUP BY c.id
                            HAVING total > 0
                            ORDER BY c.nombre
                        ")->fetchAll();
                        
                        cache_set($cache_key, $categorias_sidebar, 3600);
                    }
                    ?>
                    <ul>
                        <?php foreach ($categorias_sidebar as $cat): ?>
                            <li>
                                <a href="categoria.php?slug=<?php echo urlencode($cat['slug']); ?>"
                                   class="<?php echo $cat['slug'] === $slug ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                    (<?php echo $cat['total']; ?>)
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </td>
            <td class="main-content">
                <?php if (isset($error)): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php else: ?>
                    <div class="categoria-header">
                        <h1>
                            <?php echo htmlspecialchars($categoria['nombre']); ?>
                        </h1>
                        <?php if ($categoria['descripcion']): ?>
                            <p class="categoria-description">
                                <?php echo htmlspecialchars($categoria['descripcion']); ?>
                            </p>
                        <?php endif; ?>
                        <p>Se encontraron <?php echo $total_entradas; ?> entrada(s)</p>
                    </div>

                    <?php foreach ($entradas as $entrada): ?>
                        <div class="blog-entry">
                            <h2>
                                <a href="entrada.php?id=<?php echo $entrada['id']; ?>">
                                    <?php echo htmlspecialchars($entrada['titulo']); ?>
                                </a>
                            </h2>
                            <div class="entry-meta">
                                Publicado el <?php echo formato_fecha($entrada['fecha_creacion']); ?>
                                por <?php echo htmlspecialchars($entrada['autor_nombre']); ?>
                            </div>
                            <br>
                            <div class="entry-excerpt">
                                <?php 
                                if ($entrada['extracto']) {
                                    echo nl2br(htmlspecialchars($entrada['extracto']));
                                } else {
                                    echo nl2br(htmlspecialchars(substr($entrada['contenido'], 0, 200) . '...'));
                                }
                                ?>
                            </div>
                            <div class="entry-footer">
                                <a href="entrada.php?id=<?php echo $entrada['id']; ?>" class="read-more">
                                    Leer más...
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($total_entradas > $entradas_por_pagina): ?>
                        <div class="paginacion">
                            <?php
                            $total_paginas = ceil($total_entradas / $entradas_por_pagina);
                            for ($i = 1; $i <= $total_paginas; $i++):
                            ?>
                                <a href="?slug=<?php echo urlencode($slug); ?>&pagina=<?php echo $i; ?>" 
                                   class="<?php echo $i === $pagina_actual ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>