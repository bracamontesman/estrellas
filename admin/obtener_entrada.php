<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/funciones.php';

session_start();

if (!es_admin()) {
    http_response_code(403);
    exit('Acceso denegado');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $db = Database::getInstance();
    $conexion = $db->getConexion();
    
    // Obtener datos de la entrada con categorías y tags
    $stmt = $conexion->prepare("
        SELECT e.*,
               GROUP_CONCAT(DISTINCT ec.categoria_id) as categorias,
               GROUP_CONCAT(DISTINCT et.tag_id) as tags
        FROM entradas e
        LEFT JOIN entradas_categorias ec ON e.id = ec.entrada_id
        LEFT JOIN entradas_tags et ON e.id = et.entrada_id
        WHERE e.id = ?
        GROUP BY e.id
    ");
    $stmt->execute([$id]);
    $entrada = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($entrada) {
        // Asegurar que los campos existan aunque estén vacíos
        $entrada['categorias'] = $entrada['categorias'] ?: '';
        $entrada['tags'] = $entrada['tags'] ?: '';
        
        header('Content-Type: application/json');
        echo json_encode($entrada);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Entrada no encontrada']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener la entrada: ' . $e->getMessage()]);
}