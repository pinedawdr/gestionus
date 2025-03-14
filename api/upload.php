<?php
// Configurar límites para subida de archivos grandes (50MB)
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);

session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar que el usuario esté autenticado
if (!isAuthenticated()) {
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.0 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener tipo de documento
$type = sanitize($_POST['type'] ?? '');
$description = sanitize($_POST['description'] ?? '');

if (empty($type) || !in_array($type, ['backup', 'evidencia', 'reporte_cnv', 'otro'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo de documento no válido']);
    exit;
}

// Verificar si hay un archivo subido
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = '';
    if (isset($_FILES['file'])) {
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $errorMsg = 'El archivo excede el tamaño máximo permitido por el servidor (upload_max_filesize).';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg = 'El archivo excede el tamaño máximo permitido por el formulario (MAX_FILE_SIZE).';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMsg = 'El archivo fue subido parcialmente.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg = 'No se seleccionó ningún archivo para subir.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMsg = 'No se encontró la carpeta temporal del servidor.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMsg = 'Error al escribir el archivo en el disco.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMsg = 'La subida fue detenida por una extensión de PHP.';
                break;
            default:
                $errorMsg = 'Error desconocido al subir el archivo.';
        }
    } else {
        $errorMsg = 'No se recibió ningún archivo.';
    }
    
    echo json_encode(['success' => false, 'message' => 'Error al subir archivo: ' . $errorMsg]);
    exit;
}

// Verificar tamaño máximo (50MB = 52428800 bytes)
if ($_FILES['file']['size'] > 52428800) {
    echo json_encode(['success' => false, 'message' => 'El archivo excede el tamaño máximo permitido (50MB).']);
    exit;
}

// Crear carpetas si no existen
$userId = $_SESSION['user_id'];
$year = date('Y');
$month = date('m');
$folderPath = '';

switch ($type) {
    case 'backup':
        $folderPath = UPLOAD_PATH . "/backups/{$year}/{$month}/{$userId}";
        break;
    case 'evidencia':
        $folderPath = UPLOAD_PATH . "/evidencias/{$year}/{$month}/{$userId}";
        break;
    case 'reporte_cnv':
        $folderPath = UPLOAD_PATH . "/reportes_cnv/{$year}/{$month}/{$userId}";
        break;
    case 'otro':
        $folderPath = UPLOAD_PATH . "/otros/{$year}/{$month}/{$userId}";
        break;
}

if (!file_exists($folderPath)) {
    mkdir($folderPath, 0755, true);
}

// Preparar nombre de archivo
$originalName = $_FILES['file']['name'];
$extension = pathinfo($originalName, PATHINFO_EXTENSION);
$newFilename = date('Ymd_His') . '_' . uniqid() . '.' . $extension;
$filePath = $folderPath . '/' . $newFilename;

// Mover archivo
if (!move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar el archivo en el servidor. Verifica permisos de escritura.']);
    exit;
}

// Registrar en la base de datos
$db = Database::getInstance();
$sql = "INSERT INTO documents (user_id, type, file_name, original_name, file_path, file_size, description, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

$relativePath = str_replace(BASE_PATH, '', $filePath); // Guardar ruta relativa
$params = [
    $userId,
    $type,
    $newFilename,
    $originalName,
    $relativePath,
    $_FILES['file']['size'],
    $description
];

try {
    $db->execute($sql, $params);
    $documentId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Archivo subido correctamente', 
        'document_id' => $documentId,
        'file_name' => $originalName,
        'file_size' => formatFileSize($_FILES['file']['size'])
    ]);
} catch (Exception $e) {
    // Si hay error, intentar eliminar el archivo subido
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    echo json_encode(['success' => false, 'message' => 'Error al registrar el documento: ' . $e->getMessage()]);
}

// Función helper para formatear tamaño de archivo (por si no está disponible en functions.php)
if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes) {
        if ($bytes === 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}