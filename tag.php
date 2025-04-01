<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/Cache.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navegacion.php';

$db = Database::getInstance();
$conexion = $db->getConexion();

// Obtener slug del tag
$slug = isset($_GET['slug']) ? limpiar_entrada($_GET['slug']) : '';

try {
    // Obtener información del tag
    $cache_key = get_cache_key('tag_info', ['slug' => $slug]);
    $tag = cache_get($cache_key);

    if ($tag === false) {
        $stmt = $conexion->prepare("SELECT id, nombre, descripcion FROM tags WHERE slug = ?");
        $stmt->execute([$slug]);
        $tag = $stmt->fetch();
        
        if ($tag) {
            cache_set($cache_key, $tag, 3600);
        }
    }

    if (!$tag) {
        throw new Exception('Etiqueta no encontrada');
    }

    // Configuración de paginación
    $entradas_por_pagina = 5;
    $pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $offset = ($pagina_actual - 1) * $entradas_por_pagina;

    // Obtener total de entradas
    $cache_key = get_cache_key('tag_total_entradas', ['id' => $tag['id']]);
    $total_entradas = cache_get($cache_key);

    if ($total_entradas === false) {
        $stmt = $conexion->prepare(
            "SELECT COUNT(DISTINCT e.id) FROM entradas e
             JOIN entradas_tags et ON e.id = et.entrada_id
             WHERE et.tag_id = ? AND e.estado = 'publicado'"
        );
        $stmt->execute([$tag['id']]);
        $total_entradas = $stmt->fetchColumn();
        
        cache_set($cache_key, $total_entradas, 1800);
    }

    // Obtener entradas para esta página
    $cache_key = get_cache_key('tag_entradas', ['id' => $tag['id'], 'pagina' => $pagina_actual]);
    $entradas = cache_get($cache_key);

    if ($entradas === false) {
        $stmt = $conexion->prepare(
            "SELECT e.*, u.usuario as autor_nombre FROM entradas e
             JOIN entradas_tags et ON e.id = et.entrada_id
             LEFT JOIN usuarios u ON e.autor_id = u.id
             WHERE et.tag_id = ? AND e.estado = 'publicado'
             ORDER BY e.fecha_creacion DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$tag['id'], $entradas_por_pagina, $offset]);
        $entradas = $stmt->fetchAll();
        
        cache_set($cache_key, $entradas, 1800);
    }
} catch (Exception $e) {
    $error = 'No se pudo encontrar la etiqueta solicitada';
}
?>

<div class="content">
    <table class="content-table">
        <tr>
            <td class="sidebar">
                <div class="sidebar-content">
                    <h3>Etiquetas</h3>
                    <?php
                    $cache_key = get_cache_key('tags_sidebar');
                    $tags_sidebar = cache_get($cache_key);

                    if ($tags_sidebar === false) {
                        $tags_sidebar = $conexion->query(
                            "SELECT t.*, COUNT(et.entrada_id) as total FROM tags t
                             LEFT JOIN entradas_tags et ON t.id = et.tag_id
                             LEFT JOIN entradas e ON et.entrada_id = e.id AND e.estado = 'publicado'
                             GROUP BY t.id
                             HAVING total > 0
                             ORDER BY t.nombre"
                        )->fetchAll();
                        
                        cache_set($cache_key, $tags_sidebar, 3600);
                    }
                    ?>
                    <ul>
                        <?php foreach ($tags_sidebar as $t): ?>
                            <li>
                                <a href="tag.php?slug=<?php echo urlencode($t['slug']); ?>"
                                   class="<?php echo $t['slug'] === $slug ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($t['nombre']); ?>
                                    (<?php echo $t['total']; ?>)
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
                    <div class="tag-header">
                        <h1><?php echo htmlspecialchars($tag['nombre']); ?></h1>
                        <?php if ($tag['descripcion']): ?>
                            <p class="tag-description">
                                <?php echo htmlspecialchars($tag['descripcion']); ?>
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
                                <a href="entrada.php?id=<?php echo $entrada['id']; ?>" class="read-more">Leer más...</a>
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
