<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/Cache.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navegacion.php';

$db = Database::getInstance();
$conexion = $db->getConexion();

// Parámetros de búsqueda
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
$mes = isset($_GET['mes']) ? trim($_GET['mes']) : '';

// Configuración de paginación
$entradas_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $entradas_por_pagina;

// Construir la consulta base
$where_conditions = ["e.estado = 'publicado'"];
$params = [];

if ($busqueda) {
    $where_conditions[] = "(e.titulo LIKE ? OR e.contenido LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if ($categoria) {
    $where_conditions[] = "c.slug = ?";
    $params[] = $categoria;
}

if ($mes) {
    $where_conditions[] = "DATE_FORMAT(e.fecha_creacion, '%Y-%m') = ?";
    $params[] = $mes;
}

$where_clause = implode(' AND ', $where_conditions);

// Generar clave de caché única para esta búsqueda
$cache_key = get_cache_key('busqueda_resultados', [
    'q' => $busqueda,
    'categoria' => $categoria,
    'mes' => $mes,
    'pagina' => $pagina_actual
]);

// Intentar obtener resultados del caché
$resultados = cache_get($cache_key);

if ($resultados === false) {
    // Obtener total de resultados
    $sql_count = "
        SELECT COUNT(DISTINCT e.id)
        FROM entradas e
        LEFT JOIN entradas_categorias ec ON e.id = ec.entrada_id
        LEFT JOIN categorias c ON ec.categoria_id = c.id
        WHERE $where_clause
    ";
    
    $stmt = $conexion->prepare($sql_count);
    $stmt->execute($params);
    $total_resultados = $stmt->fetchColumn();

    // Obtener resultados para esta página
    $sql = "
        SELECT e.*, u.usuario as autor_nombre,
        GROUP_CONCAT(DISTINCT c.nombre) as categorias,
        GROUP_CONCAT(DISTINCT t.nombre) as tags
        FROM entradas e
        LEFT JOIN usuarios u ON e.autor_id = u.id
        LEFT JOIN entradas_categorias ec ON e.id = ec.entrada_id
        LEFT JOIN categorias c ON ec.categoria_id = c.id
        LEFT JOIN entradas_tags et ON e.id = et.entrada_id
        LEFT JOIN tags t ON et.tag_id = t.id
        WHERE $where_clause
        GROUP BY e.id
        ORDER BY e.fecha_creacion DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conexion->prepare($sql);
    $params[] = $entradas_por_pagina;
    $params[] = $offset;
    $stmt->execute($params);
    $entradas = $stmt->fetchAll();

    $resultados = [
        'total' => $total_resultados,
        'entradas' => $entradas
    ];

    // Cachear resultados por un tiempo corto (5 minutos)
    cache_set($cache_key, $resultados, 300);
}

// Obtener categorías para el sidebar (usando caché)
$cache_key = 'categorias_sidebar';
$categorias = cache_get($cache_key);

if ($categorias === false) {
    $categorias = $conexion->query("
        SELECT c.*, COUNT(ec.entrada_id) as total
        FROM categorias c
        LEFT JOIN entradas_categorias ec ON c.id = ec.categoria_id
        LEFT JOIN entradas e ON ec.entrada_id = e.id AND e.estado = 'publicado'
        GROUP BY c.id
        HAVING total > 0
        ORDER BY c.nombre
    ")->fetchAll();
    
    cache_set($cache_key, $categorias, 3600);
}

// Obtener archivo por meses (usando caché)
$cache_key = 'archivo_meses';
$archivo = cache_get($cache_key);

if ($archivo === false) {
    $archivo = $conexion->query("
        SELECT DATE_FORMAT(fecha_creacion, '%Y-%m') as mes,
        DATE_FORMAT(fecha_creacion, '%M %Y') as mes_nombre,
        COUNT(*) as total
        FROM entradas
        WHERE estado = 'publicado'
        GROUP BY mes
        ORDER BY mes DESC
    ")->fetchAll();
    
    cache_set($cache_key, $archivo, 3600);
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
                                       value="<?php echo htmlspecialchars($busqueda); ?>">
                            </div>
                            <div class="search-filters">
                                <select name="categoria">
                                    <option value="">Todas las categorías</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?php echo $cat['slug']; ?>"
                                            <?php echo ($categoria == $cat['slug']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <select name="mes">
                                    <option value="">Cualquier fecha</option>
                                    <?php foreach ($archivo as $m): ?>
                                        <option value="<?php echo $m['mes']; ?>"
                                            <?php echo ($mes == $m['mes']) ? 'selected' : ''; ?>>
                                            <?php echo $m['mes_nombre']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="search-button">Buscar</button>
                        </form>
                    </div>

                    <!-- Categorías -->
                    <h3>Categorías</h3>
                    <ul>
                        <?php foreach ($categorias as $cat): ?>
                            <li>
                                <a href="?categoria=<?php echo urlencode($cat['slug']); ?>">
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                    (<?php echo $cat['total']; ?>)
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </td>
            <td class="main-content">
                <h2>Resultados de búsqueda</h2>
                
                <div class="search-params">
                    <?php
                    $params_busqueda = [];
                    if ($busqueda) $params_busqueda[] = 'Término: "' . htmlspecialchars($busqueda) . '"';
                    if ($categoria) {
                        foreach ($categorias as $cat) {
                            if ($cat['slug'] == $categoria) {
                                $params_busqueda[] = 'Categoría: ' . htmlspecialchars($cat['nombre']);
                                break;
                            }
                        }
                    }
                    if ($mes) {
                        foreach ($archivo as $m) {
                            if ($m['mes'] == $mes) {
                                $params_busqueda[] = 'Fecha: ' . $m['mes_nombre'];
                                break;
                            }
                        }
                    }
                    if ($params_busqueda): ?>
                        <p>Buscando: <?php echo implode(' | ', $params_busqueda); ?></p>
                    <?php endif; ?>
                </div>

                <?php if (empty($resultados['entradas'])): ?>
                    <p>No se encontraron resultados para tu búsqueda.</p>
                <?php else: ?>
                    <p>Se encontraron <?php echo $resultados['total']; ?> resultado(s).</p>
                    
                    <?php foreach ($resultados['entradas'] as $entrada): ?>
                        <div class="blog-entry">
                            <h2>
                                <a href="entrada.php?id=<?php echo $entrada['id']; ?>">
                                    <?php echo htmlspecialchars($entrada['titulo']); ?>
                                </a>
                            </h2>
                            <div class="entry-meta">
                                Publicado el <?php echo formato_fecha($entrada['fecha_creacion']); ?>
                                por <?php echo htmlspecialchars($entrada['autor_nombre']); ?>
                                <?php if ($entrada['categorias']): ?>
                                    en <?php echo htmlspecialchars($entrada['categorias']); ?>
                                <?php endif; ?>
                            </div>
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

                    <?php
                    // Paginación
                    $total_paginas = ceil($resultados['total'] / $entradas_por_pagina);
                    if ($total_paginas > 1):
                    ?>
                        <div class="paginacion">
                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                <a href="?q=<?php echo urlencode($busqueda); ?>&categoria=<?php echo urlencode($categoria); ?>&mes=<?php echo urlencode($mes); ?>&pagina=<?php echo $i; ?>" 
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