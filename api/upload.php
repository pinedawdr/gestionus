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

// Verificar restricciones horarias
$dayOfWeek = date('N'); // 1 (lunes) a 7 (domingo)
$currentHour = (int)date('G'); // 0-23 formato 24h

// Validar según el tipo
if ($type === 'reporte_cnv') {
    // CNV: Solo martes de 00:00 hasta 12:00
    if ($dayOfWeek != 2 || $currentHour >= 12) {
        echo json_encode(['success' => false, 'message' => 'Los reportes CNV solo pueden subirse los martes de 00:00 a 12:00']);
        exit;
    }
} elseif ($type === 'backup' || $type === 'evidencia') {
    // Backups y Evidencias: Solo viernes de 00:00 hasta 18:00
    if ($dayOfWeek != 5 || $currentHour >= 18) {
        echo json_encode(['success' => false, 'message' => 'Los backups y evidencias solo pueden subirse los viernes de 00:00 a 18:00']);
        exit;
    }
}

// Procesamiento según tipo de documento
if ($type === 'evidencia') {
    // Manejo de múltiples archivos para evidencias
    if (empty($_FILES['evidencia_files']['name'][0])) {
        echo json_encode(['success' => false, 'message' => 'Debe seleccionar al menos una imagen de evidencia']);
        exit;
    }
    
    // Verificar que no hay más de 3 archivos
    if (count($_FILES['evidencia_files']['name']) > 3) {
        echo json_encode(['success' => false, 'message' => 'No puede subir más de 3 imágenes de evidencia']);
        exit;
    }
    
    // Verificar que todos son imágenes
    for ($i = 0; $i < count($_FILES['evidencia_files']['name']); $i++) {
        if (!empty($_FILES['evidencia_files']['name'][$i])) {
            $fileType = $_FILES['evidencia_files']['type'][$i];
            if (strpos($fileType, 'image/') !== 0) {
                echo json_encode(['success' => false, 'message' => 'Solo se permiten archivos de imagen para evidencias']);
                exit;
            }
            
            // Verificar tamaño (10MB)
            if ($_FILES['evidencia_files']['size'][$i] > 10 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'El archivo excede el tamaño máximo permitido (10MB)']);
                exit;
            }
        }
    }
    
    // Procesar cada archivo de evidencia
    $userId = $_SESSION['user_id'];
    $year = date('Y');
    $month = date('m');
    $folderPath = UPLOAD_PATH . "/evidencias/{$year}/{$month}/{$userId}";
    
    // Crear carpeta si no existe
    if (!file_exists($folderPath)) {
        mkdir($folderPath, 0755, true);
    }
    
    $uploadedFiles = [];
    $db = Database::getInstance();
    
    // Iniciar transacción
    $db->beginTransaction();
    
    try {
        for ($i = 0; $i < count($_FILES['evidencia_files']['name']); $i++) {
            if (!empty($_FILES['evidencia_files']['name'][$i])) {
                $originalName = $_FILES['evidencia_files']['name'][$i];
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $newFilename = date('Ymd_His') . '_' . $i . '_' . uniqid() . '.' . $extension;
                $filePath = $folderPath . '/' . $newFilename;
                
                // Mover archivo
                if (move_uploaded_file($_FILES['evidencia_files']['tmp_name'][$i], $filePath)) {
                    $relativePath = str_replace(BASE_PATH, '', $filePath);
                    
                    // Guardar en la BD
                    $sql = "INSERT INTO documents (user_id, type, file_name, original_name, file_path, file_size, description, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                    $params = [
                        $userId,
                        $type,
                        $newFilename,
                        $originalName,
                        $relativePath,
                        $_FILES['evidencia_files']['size'][$i],
                        "Evidencia " . ($i + 1) . " de " . date('d/m/Y')
                    ];
                    
                    $db->execute($sql, $params);
                    $uploadedFiles[] = [
                        'id' => $db->lastInsertId(),
                        'name' => $originalName
                    ];
                } else {
                    // Error al mover archivo, revertir cambios
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Error al guardar una de las imágenes']);
                    exit;
                }
            }
        }
        
        // Confirmar transacción
        $db->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Evidencias subidas correctamente', 
            'files' => $uploadedFiles
        ]);
        
    } catch (Exception $e) {
        // Si hay error, revertir cambios
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al registrar documentos: ' . $e->getMessage()]);
    }
    
} else {
    // Para tipos de documento individuales (backup, reporte_cnv, otro)
    
    // Verificar que hay un archivo subido
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
    
    // Verificar tamaño máximo (10MB = 10485760 bytes)
    if ($_FILES['file']['size'] > 10485760) {
        echo json_encode(['success' => false, 'message' => 'El archivo excede el tamaño máximo permitido (10MB).']);
        exit;
    }
    
    // Validaciones específicas según tipo
    if ($type === 'backup') {
        // Verificar que sea ZIP o RAR
        $fileName = strtolower($_FILES['file']['name']);
        if (!preg_match('/\.(zip|rar)$/i', $fileName)) {
            echo json_encode(['success' => false, 'message' => 'Para backups solo se permiten archivos ZIP o RAR.']);
            exit;
        }
    }
    
    if ($type === 'otro' && empty($description)) {
        echo json_encode(['success' => false, 'message' => 'Debe proporcionar una descripción para este tipo de documento.']);
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
}

// Función helper para formatear tamaño de archivo
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}