<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/database.php';

// Configurar headers correctamente
header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

// Obtener las últimas entradas
$db = Database::getInstance();
$conexion = $db->getConexion();

$entradas = $conexion->query("
    SELECT e.*, u.usuario as autor_nombre,
    GROUP_CONCAT(c.nombre) as categorias
    FROM entradas e
    LEFT JOIN usuarios u ON e.autor_id = u.id
    LEFT JOIN entradas_categorias ec ON e.id = ec.entrada_id
    LEFT JOIN categorias c ON ec.categoria_id = c.id
    WHERE e.estado = 'publicado'
    GROUP BY e.id
    ORDER BY e.fecha_creacion DESC
    LIMIT 10
")->fetchAll();

// Generar el RSS
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title><?php echo htmlspecialchars(SITE_NAME); ?></title>
        <link><?php echo htmlspecialchars(SITE_URL); ?></link>
        <description>Blog sobre astronomía y el cosmos</description>
        <language>es-ES</language>
        <lastBuildDate><?php echo date('r'); ?></lastBuildDate>
        <atom:link href="<?php echo SITE_URL; ?>/rss.php" rel="self" type="application/rss+xml" />
        
        <?php foreach ($entradas as $entrada): ?>
            <item>
                <title><?php echo htmlspecialchars($entrada['titulo']); ?></title>
                <link><?php echo htmlspecialchars(SITE_URL . '/entrada.php?id=' . $entrada['id']); ?></link>
                <guid><?php echo htmlspecialchars(SITE_URL . '/entrada.php?id=' . $entrada['id']); ?></guid>
                <pubDate><?php echo date('r', strtotime($entrada['fecha_creacion'])); ?></pubDate>
                <description><![CDATA[
                    <?php 
                    if ($entrada['extracto']) {
                        echo nl2br(htmlspecialchars($entrada['extracto']));
                    } else {
                        echo nl2br(htmlspecialchars(substr($entrada['contenido'], 0, 300) . '...'));
                    }
                    ?>
                ]]></description>
                <author><?php echo htmlspecialchars($entrada['autor_nombre']); ?></author>
                <?php if ($entrada['categorias']): ?>
                    <category><?php echo htmlspecialchars($entrada['categorias']); ?></category>
                <?php endif; ?>
            </item>
        <?php endforeach; ?>
    </channel>
</rss>