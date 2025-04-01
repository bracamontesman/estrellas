<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/funciones.php';

session_start();

// Si ya está logueado, redirigir al panel
if (es_admin()) {
    redirigir('admin/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = limpiar_entrada($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($usuario) || empty($password)) {
        $error = 'Por favor, complete todos los campos.';
    } else {
        try {
            $db = Database::getInstance();
            $conexion = $db->getConexion();
            
            $stmt = $conexion->prepare("SELECT id, usuario, password FROM usuarios WHERE usuario = ? AND estado = 'activo'");
            $stmt->execute([$usuario]);
            $usuario_db = $stmt->fetch();

            if ($usuario_db && password_verify($password, $usuario_db['password'])) {
                $_SESSION['admin'] = true;
                $_SESSION['admin_id'] = $usuario_db['id'];
                $_SESSION['admin_usuario'] = $usuario_db['usuario'];

                // Actualizar último login
                $stmt = $conexion->prepare("UPDATE usuarios SET ultimo_login = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$usuario_db['id']]);

                redirigir('admin/index.php');
            } else {
                $error = 'Usuario o contraseña incorrectos.';
            }
        } catch (PDOException $e) {
            $error = 'Error al intentar iniciar sesión.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - <?php echo SITE_NAME; ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo obtener_url_base(); ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?php echo obtener_url_base(); ?>/assets/css/admin.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1>Panel de Administración</h1>
            <h2><?php echo SITE_NAME; ?></h2>
            
            <?php if ($error): ?>
                <div class="mensaje-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form class="login-form" method="POST" action="login.php">
                <div class="form-grupo">
                    <label for="usuario">Usuario:</label>
                    <input type="text" id="usuario" name="usuario" required>
                </div>

                <div class="form-grupo">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-grupo">
                    <button type="submit" class="btn-accion">Iniciar Sesión</button>
                </div>
            </form>

            <div class="back-link">
                <a href="<?php echo obtener_url_base(); ?>">← Volver al sitio</a>
            </div>
        </div>
    </div>
</body>
</html>