<div class="container">
    <div class="header">
        <h1><?php echo SITE_NAME; ?></h1>
        <span class="blink">Temas de exploración espacial por Túnez Borja-Soler</span>
    </div>

    <div class="navigation">
        <a href="<?php echo obtener_url_base(); ?>">Inicio</a>
        <a href="<?php echo obtener_url_base(); ?>/blog.php">Blog</a>
        <a href="<?php echo obtener_url_base(); ?>/libro-visitas.php">Libro de Visitas</a>
        <?php if (es_admin()): ?>
            <a href="<?php echo obtener_url_base(); ?>/admin/">Admin</a>
        <?php endif; ?>
    </div>