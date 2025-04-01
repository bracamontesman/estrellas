<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/Cache.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navegacion.php';

$db = Database::getInstance();
$conexion = $db->getConexion();

// Registrar visita
try {
    $fecha = date('Y-m-d');
    $conexion->prepare("
        INSERT INTO visitas_diarias (fecha, total)
        VALUES (?, 1)
        ON DUPLICATE KEY UPDATE total = total + 1
    ")->execute([$fecha]);
} catch (Exception $e) {
    error_log("Error al registrar visita: " . $e->getMessage());
}

// Configuración de paginación
$entradas_por_pagina = 5;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $entradas_por_pagina;

// Obtener total de entradas
$cache_key = get_cache_key('total_entradas_blog');
$total_entradas = cache_get($cache_key);

if ($total_entradas === false) {
    $total_entradas = $conexion->query("
        SELECT COUNT(*) FROM entradas WHERE estado = 'publicado'
    ")->fetchColumn();
    
    cache_set($cache_key, $total_entradas, 300); // 5 minutos
}

// Obtener entradas para la página actual
$cache_key = get_cache_key('entradas_blog_pagina', ['pagina' => $pagina_actual]);
$entradas = cache_get($cache_key);

if ($entradas === false) {
    // Consulta principal de entradas con sus relaciones
    $entradas = $conexion->prepare("
        SELECT e.*, 
               u.usuario as autor_nombre,
               GROUP_CONCAT(DISTINCT c.nombre) as categorias,
               GROUP_CONCAT(DISTINCT c.slug) as categoria_slugs,
               GROUP_CONCAT(DISTINCT t.nombre) as tags
        FROM entradas e
        LEFT JOIN usuarios u ON e.autor_id = u.id
        LEFT JOIN entradas_categorias ec ON e.id = ec.entrada_id
        LEFT JOIN categorias c ON ec.categoria_id = c.id
        LEFT JOIN entradas_tags et ON e.id = et.entrada_id
        LEFT JOIN tags t ON et.tag_id = t.id
        WHERE e.estado = 'publicado'
        GROUP BY e.id
        ORDER BY e.fecha_creacion DESC
        LIMIT ? OFFSET ?
    ");
    
    $entradas->execute([$entradas_por_pagina, $offset]);
    $entradas = $entradas->fetchAll();
    
    cache_set($cache_key, $entradas, 300); // 5 minutos
}
?>

<div class="content">
    <table class="content-table">
        <tr>
            <td class="sidebar">
                <div class="sidebar-content">
                    <!-- Formulario de búsqueda -->
                    <div class="search-box">
                        <h3>Buscar</h3>
                        <form action="buscar.php" method="GET" class="search-form">
                            <div class="search-input">
                                <input type="text" name="q" placeholder="Buscar..." 
                                       value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                            </div>
                            <button type="submit" class="search-button">Buscar</button>
                        </form>
                    </div>

                    <!-- Categorías -->
                    <h3>Categorías</h3>
                    <?php
                    $cache_key = get_cache_key('categorias_sidebar');
                    $categorias = cache_get($cache_key);

                    if ($categorias === false) {
                        $categorias = $conexion->query("
                            SELECT c.*, COUNT(DISTINCT e.id) as total
                            FROM categorias c
                            LEFT JOIN entradas_categorias ec ON c.id = ec.categoria_id
                            LEFT JOIN entradas e ON ec.entrada_id = e.id AND e.estado = 'publicado'
                            GROUP BY c.id
                            HAVING total > 0
                            ORDER BY c.nombre
                        ")->fetchAll();
                        
                        cache_set($cache_key, $categorias, 300);
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

                    <!-- Tags populares -->
                    <h3>Etiquetas Populares</h3>
                    <?php
                    $cache_key = get_cache_key('tags_populares');
                    $tags_populares = cache_get($cache_key);

                    if ($tags_populares === false) {
                        $tags_populares = $conexion->query("
                            SELECT t.*, COUNT(DISTINCT e.id) as total
                            FROM tags t
                            LEFT JOIN entradas_tags et ON t.id = et.tag_id
                            LEFT JOIN entradas e ON et.entrada_id = e.id AND e.estado = 'publicado'
                            GROUP BY t.id
                            HAVING total > 0
                            ORDER BY total DESC, t.nombre ASC
                            LIMIT 20
                        ")->fetchAll();
                        
                        cache_set($cache_key, $tags_populares, 300);
                    }
                    ?>
                    <div class="tags-cloud">
                        <?php foreach ($tags_populares as $tag): ?>
                            <a href="tag.php?slug=<?php echo urlencode($tag['slug']); ?>" 
                               class="tag-link">
                                <?php echo htmlspecialchars($tag['nombre']); ?>
                                (<?php echo $tag['total']; ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Archivo por meses -->
                    <h3>Archivo</h3>
                    <?php
                    $cache_key = get_cache_key('archivo_meses');
                    $archivo = cache_get($cache_key);

                    if ($archivo === false) {
                        $archivo = $conexion->query("
                            SELECT 
                                DATE_FORMAT(fecha_creacion, '%Y-%m') as mes,
                                DATE_FORMAT(fecha_creacion, '%M %Y') as mes_nombre,
                                COUNT(*) as total
                            FROM entradas
                            WHERE estado = 'publicado'
                            GROUP BY mes
                            ORDER BY mes DESC
                        ")->fetchAll();
                        
                        cache_set($cache_key, $archivo, 300);
                    }
                    ?>
                    <ul>
                        <?php foreach ($archivo as $mes): ?>
                            <li>
                                <a href="blog.php?mes=<?php echo urlencode($mes['mes']); ?>">
                                    <?php echo $mes['mes_nombre']; ?>
                                    (<?php echo $mes['total']; ?>)
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </td>
            <td class="main-content">
                <?php if (empty($entradas)): ?>
                    <p>No hay entradas publicadas.</p>
                <?php else: ?>
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
                                <br>
                                <?php if ($entrada['categorias']): ?>
                                    Categoría: 
                                    <?php
                                    $cats = explode(',', $entrada['categorias']);
                                    $slugs = explode(',', $entrada['categoria_slugs']);
                                    $links = array();
                                    foreach ($cats as $i => $cat) {
                                        $links[] = '<a href="categoria.php?slug=' . urlencode($slugs[$i]) . '">' . 
                                                 htmlspecialchars($cat) . '</a>';
                                    }
                                    echo implode(', ', $links);
                                    ?>
                                <?php endif; ?>
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
                                <a href="?pagina=<?php echo $i; ?>" 
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