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
?>

<div class="content">
    <table class="content-table">
        <tr>
            <td class="sidebar">
                <div class="sidebar-content">
                <h3>Categorías</h3>
                    <?php
                    $cache_key = get_cache_key('categorias_sidebar');
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
                        
                        cache_set($cache_key, $categorias, 3600); // 1 hora
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
                    <img src="assets/images/construction.gif" alt="En construcción" style="max-width: 100%;">
                </div>
            </td>
            <td class="main-content">
                <div class="welcome-section">
                    <h1>Bienvenidos a Estrellas y Planetas</h1>
                    <p>Viaja con nosotros a través del espacio y el tiempo en este pequeño rincón del internet, donde la astronomía y la historia del cosmos se encuentran. En Estrellas y Planetas, podrás descubrir viejos archivos, imágenes históricas de la NASA y todo lo relacionado con las maravillas del universo.</p>
                    <p>Si eres de los que se pierden observando las estrellas, curiosos por conocer los secretos del espacio, este es tu lugar. Únete a nuestra aventura y exploremos juntos los misterios de las estrellas, los planetas y todo lo que habita en la inmensidad del cielo.</p>
                    <div class="latest-updates">
                        <h2>Últimas Actualizaciones 22/03/2025</h2>
                        <ul>
                            <li>Comencé a trabajar en este sitio el 20 de Marzo, 2025</li>
                            <li>La mayoría (si no es que todas) de las funciones desplegadas ya sirven</li>
                            <li>El backend está siendo remodelado, pronto estará listo</li>
                            <li>Los cambios al frontend se irán desplegando poco a poco a partir de ahora</li>
                            <li>ETA: 04/01</li>
                        </ul>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>