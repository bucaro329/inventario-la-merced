<?php
/**
 * Configuración de rutas base del proyecto
 * Facilita la redirección y enlaces entre archivos
 */

// Ruta base del proyecto (ajustar si está en subcarpeta)
define('BASE_PATH', '/inventario-la-merced');
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . BASE_PATH);

// Rutas de carpetas
define('ASSETS_PATH', BASE_PATH . '/assets');
define('CONFIG_PATH', __DIR__);
define('INCLUDES_PATH', dirname(__DIR__) . '/includes');
define('EXPORTS_PATH', dirname(__DIR__) . '/exports');
define('IMPORTS_PATH', dirname(__DIR__) . '/imports');

// Función helper para generar URLs
function url($path = '') {
    $path = ltrim($path, '/');
    return BASE_PATH . '/' . $path;
}

// Función helper para incluir archivos
function include_path($file) {
    return dirname(__DIR__) . '/' . $file;
}
?>

