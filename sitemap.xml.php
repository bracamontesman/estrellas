<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/database.php';

header('Content-Type: application/xml; charset=utf-8');

$db = Database::getInstance();
$conexion = $db->getConexion();

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Páginas principales -->
    <url>
        <loc><?php echo SITE_URL; ?>/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?php echo SITE_URL; ?>/blog.php</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?php echo SITE_URL; ?>/libro-visitas.php</loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>

    <!-- Entradas del blog -->
    <?php
    $entradas = $conexion->query("
        SELECT id, fecha_modificacion
        FROM entradas
        WHERE estado = 'publicado'
        ORDER BY fecha_creacion DESC
    ")->fetchAll();

    foreach ($entradas as $entrada):
    ?>
    <url>
        <loc><?php echo SITE_URL; ?>/entrada.php?id=<?php echo $entrada['id']; ?></loc>
        <lastmod><?php echo date('Y-m-d', strtotime($entrada['fecha_modificacion'])); ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    <?php endforeach; ?>

    <!-- Categorías -->
    <?php
    $categorias = $conexion->query("
        SELECT slug
        FROM categorias
        WHERE EXISTS (
            SELECT 1 FROM entradas_categorias ec
            JOIN entradas e ON ec.entrada_id = e.id
            WHERE ec.categoria_id = categorias.id AND e.estado = 'publicado'
        )
    ")->fetchAll();

    foreach ($categorias as $categoria):
    ?>
    <url>
        <loc><?php echo SITE_URL; ?>/blog.php?categoria=<?php echo urlencode($categoria['slug']); ?></loc>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
    <?php endforeach; ?>

    <!-- Tags -->
    <?php
    $tags = $conexion->query("
        SELECT slug
        FROM tags
        WHERE EXISTS (
            SELECT 1 FROM entradas_tags et
            JOIN entradas e ON et.entrada_id = e.id
            WHERE et.tag_id = tags.id AND e.estado = 'publicado'
        )
    ")->fetchAll();

    foreach ($tags as $tag):
    ?>
    <url>
        <loc><?php echo SITE_URL; ?>/tag.php?slug=<?php echo urlencode($tag['slug']); ?></loc>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
    <?php endforeach; ?>
</urlset>