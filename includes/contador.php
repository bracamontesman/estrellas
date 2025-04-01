<?php
function registrar_visita($pagina) {
    $db = Database::getInstance();
    $conexion = $db->getConexion();
    
    $fecha = date('Y-m-d');
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    try {
        // Registrar visita detallada
        $stmt = $conexion->prepare("
            INSERT INTO visitas (fecha, pagina, ip, user_agent)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$fecha, $pagina, $ip, $user_agent]);

        // Actualizar contador diario
        $conexion->prepare("
            INSERT INTO visitas_diarias (fecha, total)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE total = total + 1
        ")->execute([$fecha]);

    } catch (Exception $e) {
        error_log("Error en contador de visitas: " . $e->getMessage());
    }
}

function obtener_visitas_totales() {
    $db = Database::getInstance();
    $conexion = $db->getConexion();
    
    try {
        return $conexion->query("
            SELECT SUM(total) 
            FROM visitas_diarias
        ")->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function obtener_visitas_hoy() {
    $db = Database::getInstance();
    $conexion = $db->getConexion();
    $fecha = date('Y-m-d');
    
    try {
        return $conexion->query("
            SELECT total 
            FROM visitas_diarias 
            WHERE fecha = '$fecha'
        ")->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function obtener_estadisticas_visitas() {
    $db = Database::getInstance();
    $conexion = $db->getConexion();
    
    try {
        // Últimos 7 días
        $ultimos_dias = $conexion->query("
            SELECT fecha, total
            FROM visitas_diarias
            ORDER BY fecha DESC
            LIMIT 7
        ")->fetchAll();

        // Páginas más visitadas
        $paginas_populares = $conexion->query("
            SELECT pagina, COUNT(*) as total
            FROM visitas
            GROUP BY pagina
            ORDER BY total DESC
            LIMIT 5
        ")->fetchAll();

        return [
            'ultimos_dias' => $ultimos_dias,
            'paginas_populares' => $paginas_populares
        ];
    } catch (Exception $e) {
        return [
            'ultimos_dias' => [],
            'paginas_populares' => []
        ];
    }
}