<?php
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
    $error = $_FILES['file']['error'] ?? 'desconocido';
    echo json_encode(['success' => false, 'message' => 'Error al subir archivo: ' . $error]);
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
    echo json_encode(['success' => false, 'message' => 'Error al guardar el archivo']);
    exit;
}

// Registrar en la base de datos
$db = Database::getInstance();
$sql = "INSERT INTO documents (user_id, type, file_name, original_name, file_path, file_size, description, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

$params = [
    $userId,
    $type,
    $newFilename,
    $originalName,
    str_replace(BASE_PATH, '', $filePath), // Guardar ruta relativa
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
        'file_name' => $originalName
    ]);
} catch (Exception $e) {
    // Si hay error, intentar eliminar el archivo subido
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    echo json_encode(['success' => false, 'message' => 'Error al registrar el documento: ' . $e->getMessage()]);
}