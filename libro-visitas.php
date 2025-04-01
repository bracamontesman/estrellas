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
$mensajes_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $mensajes_por_pagina;

// Procesar el formulario
$mensaje_exito = '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = limpiar_entrada($_POST['nombre'] ?? '');
    $email = limpiar_entrada($_POST['email'] ?? '');
    $mensaje = limpiar_entrada($_POST['mensaje'] ?? '');
    $website = limpiar_entrada($_POST['website'] ?? '');
    
    $errores = [];
    
    if (empty($nombre)) $errores[] = "El nombre es obligatorio";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "Email inválido";
    if (empty($mensaje)) $errores[] = "El mensaje no puede estar vacío";
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) $errores[] = "URL del sitio web inválida";
    
    if (empty($errores)) {
        try {
            $stmt = $conexion->prepare("
                INSERT INTO libro_visitas (nombre, email, mensaje, website, estado)
                VALUES (?, ?, ?, ?, 'pendiente')
            ");
            $stmt->execute([$nombre, $email, $mensaje, $website]);
            $mensaje_exito = "¡Gracias por firmar nuestro libro de visitas! Tu mensaje será revisado antes de ser publicado.";
            
            // Limpiar caché
            cache_clear();
        } catch (Exception $e) {
            $mensaje_error = "Error al guardar el mensaje";
        }
    } else {
        $mensaje_error = implode("<br>", $errores);
    }
}

// Obtener total de mensajes desde caché
$cache_key = get_cache_key('total_firmas_publicas');
$total_mensajes = cache_get($cache_key);

if ($total_mensajes === false) {
    $total_mensajes = $conexion->query("
        SELECT COUNT(*) FROM libro_visitas 
        WHERE estado = 'aprobado'
    ")->fetchColumn();
    
    cache_set($cache_key, $total_mensajes, 1800);
}

// Obtener mensajes para la página actual desde caché
$cache_key = get_cache_key('firmas_pagina', ['pagina' => $pagina_actual]);
$mensajes = cache_get($cache_key);

if ($mensajes === false) {
    $mensajes = $conexion->prepare("
        SELECT * FROM libro_visitas 
        WHERE estado = 'aprobado'
        ORDER BY fecha_creacion DESC
        LIMIT ? OFFSET ?
    ");
    $mensajes->execute([$mensajes_por_pagina, $offset]);
    $mensajes = $mensajes->fetchAll();
    
    cache_set($cache_key, $mensajes, 1800);
}
?>

<div class="content">
    <table class="content-table">
        <tr>
            <td class="sidebar">
                <div class="sidebar-content">
                    <h3>Estadísticas</h3>
                    <ul>
                        <li>Total de firmas: <?php echo $total_mensajes; ?></li>
                        <?php
                        $ultima_firma = $conexion->query("
                            SELECT fecha_creacion 
                            FROM libro_visitas 
                            WHERE estado = 'aprobado' 
                            ORDER BY fecha_creacion DESC 
                            LIMIT 1
                        ")->fetchColumn();
                        ?>
                        <li>Última firma: <?php echo $ultima_firma ? formato_fecha($ultima_firma) : 'N/A'; ?></li>
                    </ul>
                    <img src="assets/images/guestbook.gif" alt="Libro de visitas" style="max-width: 100%;">
                </div>
            </td>
            <td class="main-content">
                <div class="guestbook-section">
                    <h1>Libro de Visitas</h1>
                    <p>¡Déjanos tu mensaje! Comparte tus pensamientos sobre astronomía y el cosmos.</p>

                    <?php if ($mensaje_exito): ?>
                        <div class="mensaje-exito"><?php echo $mensaje_exito; ?></div>
                    <?php endif; ?>

                    <?php if ($mensaje_error): ?>
                        <div class="mensaje-error"><?php echo $mensaje_error; ?></div>
                    <?php endif; ?>

                    <div class="firma-form">
                        <h3>Firmar el Libro</h3>
                        <form method="POST" class="guestbook-form">
                            <div class="form-grupo">
                                <label for="nombre">Nombre:</label>
                                <div class="campo-input">
                                    <input type="text" name="nombre" id="nombre" required>
                                </div>
                            </div>

                            <div class="form-grupo">
                                <label for="email">Email:</label>
                                <div class="campo-input">
                                    <input type="email" name="email" id="email" required>
                                    <small>(No será publicado)</small>
                                </div>
                            </div>

                            <div class="form-grupo">
                                <label for="website">Sitio Web:</label>
                                <div class="campo-input">
                                    <input type="url" name="website" id="website">
                                    <small>(Opcional)</small>
                                </div>
                            </div>

                            <div class="form-grupo">
                                <label for="mensaje">Mensaje:</label>
                                <div class="campo-input">
                                    <textarea name="mensaje" id="mensaje" rows="5" required></textarea>
                                </div>
                            </div>

                            <div class="form-grupo-botones">
                                <button type="submit" class="btn-firmar">Firmar Libro</button>
                            </div>
                        </form>
                    </div>

                    <div class="firmas-lista">
                        <h3>Firmas</h3>
                        <?php if ($mensajes): ?>
                            <?php foreach ($mensajes as $mensaje): ?>
                                <div class="firma">
                                    <div class="firma-meta">
                                        <strong>
                                            <?php if (!empty($mensaje['website'])): ?>
                                                <a href="<?php echo htmlspecialchars($mensaje['website']); ?>" 
                                                   target="_blank" rel="nofollow">
                                                    <?php echo htmlspecialchars($mensaje['nombre']); ?>
                                                </a>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($mensaje['nombre']); ?>
                                            <?php endif; ?>
                                        </strong>
                                        escribió el <?php echo formato_fecha($mensaje['fecha_creacion']); ?>:
                                    </div>
                                    <div class="firma-contenido">
                                        <?php echo nl2br(htmlspecialchars($mensaje['mensaje'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($total_mensajes > $mensajes_por_pagina): ?>
                                <div class="paginacion">
                                    <?php
                                    $total_paginas = ceil($total_mensajes / $mensajes_por_pagina);
                                    for ($i = 1; $i <= $total_paginas; $i++):
                                    ?>
                                        <a href="?pagina=<?php echo $i; ?>" 
                                           class="<?php echo $i === $pagina_actual ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>No hay firmas aún. ¡Sé el primero en firmar!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>