<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navegacion.php';

$db = Database::getInstance();
$conexion = $db->getConexion();

// Obtener todas las secciones principales
$secciones = [
    'Inicio' => 'index.php',
    'Blog' => 'blog.php',
    'Libro de Visitas' => 'libro-visitas.php',
    'RSS Feed' => 'rss.php'
];

// Obtener categorías
$categorias = $conexion->query("
    SELECT c.*, COUNT(ec.entrada_id) as total
    FROM categorias c
    LEFT JOIN entradas_categorias ec ON c.id = ec.categoria_id
    LEFT JOIN entradas e ON ec.entrada_id = e.id AND e.estado = 'publicado'
    GROUP BY c.id
    HAVING total > 0
    ORDER BY c.nombre
")->fetchAll();

// Obtener tags
$tags = $conexion->query("
    SELECT t.*, COUNT(et.entrada_id) as total
    FROM tags t
    JOIN entradas_tags et ON t.id = et.tag_id
    JOIN entradas e ON et.entrada_id = e.id
    WHERE e.estado = 'publicado'
    GROUP BY t.id
    HAVING total > 0
    ORDER BY t.nombre
")->fetchAll();

// Obtener entradas por mes
$entradas_por_mes = $conexion->query("
    SELECT 
        DATE_FORMAT(fecha_creacion, '%Y-%m') as mes,
        DATE_FORMAT(fecha_creacion, '%M %Y') as mes_nombre,
        GROUP_CONCAT(
            CONCAT(id, ':::', titulo) 
            ORDER BY fecha_creacion DESC 
            SEPARATOR '|||'
        ) as entradas
    FROM entradas
    WHERE estado = 'publicado'
    GROUP BY mes
    ORDER BY mes DESC
")->fetchAll();
?>

<div class="content">
    <div class="sitemap-container">
        <h1>Mapa del Sitio</h1>
        
        <!-- Secciones Principales -->
        <div class="sitemap-section">
            <h2>Secciones Principales</h2>
            <ul class="sitemap-list">
                <?php foreach ($secciones as $nombre => $url): ?>
                    <li>
                        <a href="<?php echo obtener_url_base() . '/' . $url; ?>">
                            <?php echo htmlspecialchars($nombre); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Categorías -->
        <div class="sitemap-section">
            <h2>Categorías</h2>
            <ul class="sitemap-list">
                <?php foreach ($categorias as $categoria): ?>
                    <li>
                        <a href="blog.php?categoria=<?php echo urlencode($categoria['slug']); ?>">
                            <?php echo htmlspecialchars($categoria['nombre']); ?>
                        </a>
                        <span class="count">(<?php echo $categoria['total']; ?> entradas)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Tags -->
        <div class="sitemap-section">
            <h2>Etiquetas</h2>
            <div class="sitemap-tags">
                <?php foreach ($tags as $tag): ?>
                    <a href="tag.php?slug=<?php echo urlencode($tag['slug']); ?>" 
                       class="tag-link">
                        <?php echo htmlspecialchars($tag['nombre']); ?>
                        <span class="count">(<?php echo $tag['total']; ?>)</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Archivo por Mes -->
        <div class="sitemap-section">
            <h2>Archivo</h2>
            <?php foreach ($entradas_por_mes as $mes): ?>
                <div class="month-section">
                    <h3><?php echo htmlspecialchars($mes['mes_nombre']); ?></h3>
                    <ul class="sitemap-list">
                        <?php
                        $entradas = explode('|||', $mes['entradas']);
                        foreach ($entradas as $entrada) {
                            list($id, $titulo) = explode(':::', $entrada);
                            ?>
                            <li>
                                <a href="entrada.php?id=<?php echo $id; ?>">
                                    <?php echo htmlspecialchars($titulo); ?>
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.sitemap-container {
    padding: 20px;
    background-color: var(--color-bg-dark);
    border: 1px solid var(--color-border);
}

.sitemap-section {
    margin-bottom: 30px;
}

.sitemap-section h2 {
    color: var(--color-link);
    border-bottom: 1px solid var(--color-border);
    padding-bottom: 10px;
    margin-bottom: 15px;
}

.sitemap-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sitemap-list li {
    margin-bottom: 10px;
    padding-left: 20px;
    position: relative;
}

.sitemap-list li:before {
    content: '→';
    position: absolute;
    left: 0;
    color: var(--color-link);
}

.sitemap-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.month-section {
    margin-bottom: 20px;
}

.month-section h3 {
    color: var(--color-text-bright);
    margin-bottom: 10px;
}

.count {
    color: var(--color-link);
    font-size: 0.9em;
}

@media screen and (max-width: 768px) {
    .sitemap-container {
        padding: 10px;
    }

    .sitemap-tags {
        gap: 5px;
    }
}
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>