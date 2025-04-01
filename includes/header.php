<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/funciones.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo SITE_NAME; ?> - Exploración espacial</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE): ?>
        <!-- Versión de desarrollo -->
        <link rel="stylesheet" href="<?php echo obtener_url_base(); ?>/assets/css/main.css">
        <link rel="stylesheet" href="<?php echo obtener_url_base(); ?>/assets/css/retro.css">
    <?php else: ?>
        <!-- Versión de producción -->
        <link rel="stylesheet" href="<?php echo obtener_url_base(); ?>/assets/css/combined.min.css">
    <?php endif; ?>
    
    <?php if (es_admin()): ?>
        <link rel="stylesheet" href="<?php echo obtener_url_base(); ?>/assets/css/<?php echo DEVELOPMENT_MODE ? 'admin.css' : 'admin.min.css'; ?>">
    <?php endif; ?>
</head>
<body>