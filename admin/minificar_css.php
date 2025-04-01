<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/funciones.php';

session_start();

if (!es_admin()) {
    redirigir('admin/login.php');
}

class CSSMinifier {
    private $css;
    
    public function __construct($css) {
        $this->css = $css;
    }
    
    public function minify() {
        // Eliminar comentarios
        $this->css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $this->css);
        
        // Eliminar espacios alrededor de {, }, :, ;
        $this->css = preg_replace('/\s*({\s*|\s*}\s*|\s*:\s*|\s*;\s*)\s*/', '$1', $this->css);
        
        // Eliminar punto y coma final innecesario
        $this->css = preg_replace('/;}/', '}', $this->css);
        
        // Eliminar espacios en blanco innecesarios
        $this->css = preg_replace('/\s+/', ' ', $this->css);
        
        // Eliminar espacios antes y después del CSS
        $this->css = trim($this->css);
        
        return $this->css;
    }
}

function minificar_archivo($ruta_origen, $ruta_destino) {
    if (!file_exists($ruta_origen)) {
        return "El archivo $ruta_origen no existe.";
    }
    
    $css = file_get_contents($ruta_origen);
    $minifier = new CSSMinifier($css);
    $css_minificado = $minifier->minify();
    
    if (file_put_contents($ruta_destino, $css_minificado)) {
        return "Archivo minificado guardado en $ruta_destino";
    } else {
        return "Error al guardar el archivo minificado.";
    }
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $archivos_css = [
        '../assets/css/main.css' => '../assets/css/main.min.css',
        '../assets/css/retro.css' => '../assets/css/retro.min.css',
        '../assets/css/admin.css' => '../assets/css/admin.min.css'
    ];
    
    foreach ($archivos_css as $origen => $destino) {
        $resultado = minificar_archivo($origen, $destino);
        $mensaje .= $resultado . "<br>";
    }
    
    // Crear archivo combinado para el frontend
    $css_combinado = '';
    foreach (['../assets/css/main.min.css', '../assets/css/retro.min.css'] as $archivo) {
        if (file_exists($archivo)) {
            $css_combinado .= file_get_contents($archivo) . "\n";
        }
    }
    file_put_contents('../assets/css/combined.min.css', $css_combinado);
    $mensaje .= "Archivos CSS combinados guardados en combined.min.css";
}

require_once __DIR__ . '/header.php';
?>

<div class="admin-container">
    <h1>Minificación de CSS</h1>

    <?php if ($mensaje): ?>
        <div class="mensaje-exito"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mensaje-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="info-section">
        <p>Este proceso minificará los archivos CSS del sitio para mejorar el rendimiento:</p>
        <ul>
            <li>Elimina comentarios y espacios en blanco innecesarios</li>
            <li>Combina archivos CSS relacionados</li>
            <li>Crea versiones .min.css de cada archivo</li>
        </ul>
    </div>

    <form method="POST" class="admin-form">
        <button type="submit" class="btn-accion">Minificar CSS</button>
    </form>

    <div class="archivos-section">
        <h3>Archivos CSS actuales:</h3>
        <ul>
            <?php
            $archivos = glob('../assets/css/*.css');
            foreach ($archivos as $archivo) {
                $tamano = filesize($archivo);
                echo "<li>" . basename($archivo) . " (" . round($tamano/1024, 2) . " KB)</li>";
            }
            ?>
        </ul>
    </div>

    <div class="admin-menu">
        <a href="index.php">← Volver al Panel</a>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>