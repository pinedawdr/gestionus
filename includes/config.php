<?php
// Configuración de la aplicación
define('APP_NAME', 'GestiónUS');
define('APP_VERSION', '1.0.0');

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Reemplazar con tu usuario de base de datos
define('DB_PASS', 'root'); // Reemplazar con tu contraseña de base de datos
define('DB_NAME', 'gestionus'); // Reemplazar con el nombre de tu base de datos

// Configuración de rutas
define('BASE_URL', 'http://localhost:8888/gestionus/admin/settings.php'); // Reemplazar con tu dominio
define('BASE_PATH', dirname(dirname(__FILE__)));
define('UPLOAD_PATH', BASE_PATH . '/uploads');

// Configuración de Firebase Admin
define('FIREBASE_API_KEY', 'AIzaSyCPUsXqvdtbU8lGhcH-qD86d7bZHrkaaao'); // API Key completa

// Configuración de correo electrónico (EmailJS)
define('EMAILJS_USER_ID', 'tu_user_id_emailjs'); // Reemplazar con tu User ID de EmailJS
define('EMAILJS_SERVICE_ID', 'tu_service_id_emailjs'); // Reemplazar con tu Service ID de EmailJS
define('EMAILJS_TEMPLATE_ID', 'tu_template_id_emailjs'); // Reemplazar con tu Template ID de EmailJS

// Zona horaria
date_default_timezone_set('America/Lima'); // Ajustar a tu zona horaria si es necesario

// Habilitar reporte de errores en desarrollo, deshabilitar en producción
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Función para manejar errores
function handleError($errno, $errstr, $errfile, $errline) {
    $error_message = "Error [$errno] $errstr - $errfile:$errline";
    error_log($error_message);
    
    if (ini_get('display_errors')) {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb; border-radius: 5px;'>";
        echo "<strong>Error:</strong> $errstr<br>";
        echo "Archivo: $errfile, Línea: $errline";
        echo "</div>";
    }
    
    return true;
}
set_error_handler('handleError');

// Función para manejar excepciones
function handleException($exception) {
    $error_message = "Excepción: " . $exception->getMessage() . " en " . $exception->getFile() . ":" . $exception->getLine();
    error_log($error_message);
    
    if (ini_get('display_errors')) {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb; border-radius: 5px;'>";
        echo "<strong>Excepción:</strong> " . $exception->getMessage() . "<br>";
        echo "Archivo: " . $exception->getFile() . ", Línea: " . $exception->getLine();
        echo "</div>";
    } else {
        echo "<div style='text-align: center; padding: 20px;'>";
        echo "<h2>Ha ocurrido un error inesperado</h2>";
        echo "<p>Por favor, inténtelo de nuevo más tarde o contacte al administrador del sistema.</p>";
        echo "</div>";
    }
    
    exit;
}
set_exception_handler('handleException');