<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Asegurar autenticación excepto para la acción 'view' con token
$action = $_REQUEST['action'] ?? '';
if ($action !== 'view' || !isset($_GET['token'])) {
    requireAuth();
}

// Manejar diferentes acciones
switch ($action) {
    case 'list':
        // Listar documentos del usuario o todos si es admin
        $userId = isset($_GET['user_id']) && isAdmin() ? (int)$_GET['user_id'] : $_SESSION['user_id'];
        $type = isset($_GET['type']) ? sanitize($_GET['type']) : null;
        $startDate = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : null;
        $endDate = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : null;
        
        $db = Database::getInstance();
        $params = [];
        
        $sql = "SELECT d.*, u.name as user_name 
                FROM documents d 
                JOIN users u ON d.user_id = u.id 
                WHERE 1=1";
        
        if (!isAdmin()) {
            $sql .= " AND d.user_id = ?";
            $params[] = $userId;
        } else if (isset($_GET['user_id'])) {
            $sql .= " AND d.user_id = ?";
            $params[] = $userId;
        }
        
        if ($type) {
            $sql .= " AND d.type = ?";
            $params[] = $type;
        }
        
        if ($startDate) {
            $sql .= " AND DATE(d.created_at) >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND DATE(d.created_at) <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY d.created_at DESC";
        
        try {
            $documents = $db->fetchAll($sql, $params);
            
            // Preparar URLs seguras para los archivos
            foreach ($documents as &$doc) {
                $doc['secure_url'] = getSecureFileUrl($doc['file_path']);
                $doc['file_type'] = getFileType($doc['original_name']);
                $doc['created_at_formatted'] = formatDate($doc['created_at']);
            }
            
            echo json_encode(['success' => true, 'documents' => $documents]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al obtener documentos: ' . $e->getMessage()]);
        }
        break;
        
    case 'view':
        // Ver archivo con token de seguridad
        if (!isset($_GET['token']) || !isset($_SESSION['file_tokens'][$_GET['token']])) {
            header('HTTP/1.0 403 Forbidden');
            echo "Acceso denegado";
            exit;
        }
        
        $token = $_GET['token'];
        $tokenData = $_SESSION['file_tokens'][$token];
        
        // Verificar que el token no haya expirado
        if ($tokenData['expires'] < time()) {
            unset($_SESSION['file_tokens'][$token]);
            header('HTTP/1.0 403 Forbidden');
            echo "El enlace ha expirado";
            exit;
        }
        
        $filePath = BASE_PATH . $tokenData['path'];
        
        if (!file_exists($filePath)) {
            header('HTTP/1.0 404 Not Found');
            echo "Archivo no encontrado";
            exit;
        }
        
        // Obtener tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        // Verificar si es una descarga
        $download = isset($_GET['download']) && $_GET['download'] == '1';
        $disposition = $download ? 'attachment' : 'inline';
        
        // Enviar headers para visualización o descarga
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        
        // Leer y enviar el archivo
        readfile($filePath);
        exit;
        
    case 'download_batch':
        // Descargar múltiples archivos como ZIP
        if (!isset($_POST['file_ids']) || !is_array($_POST['file_ids']) || empty($_POST['file_ids'])) {
            header('HTTP/1.0 400 Bad Request');
            echo json_encode(['success' => false, 'message' => 'No se han seleccionado archivos']);
            exit;
        }
        
        $fileIds = array_map('intval', $_POST['file_ids']);
        $db = Database::getInstance();
        
        // Obtener información de los archivos
        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $sql = "SELECT * FROM documents WHERE id IN ($placeholders)";
        
        // Verificar permisos
        if (!isAdmin()) {
            $sql .= " AND user_id = ?";
            $fileIds[] = $_SESSION['user_id'];
        }
        
        $documents = $db->fetchAll($sql, $fileIds);
        
        if (empty($documents)) {
            header('HTTP/1.0 404 Not Found');
            echo json_encode(['success' => false, 'message' => 'No se encontraron archivos']);
            exit;
        }
        
        // Crear archivo ZIP temporal
        $zipName = 'documentos_' . date('Ymd_His') . '.zip';
        $tempDir = sys_get_temp_dir();
        
        // Asegurarse de que el directorio temporal existe y es escribible
        if (!is_dir($tempDir) || !is_writable($tempDir)) {
            $tempDir = dirname(__FILE__) . '/../temp';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
        }
        
        $zipPath = $tempDir . '/' . $zipName;
        
        // Verificar si ZipArchive está disponible
        if (!class_exists('ZipArchive')) {
            header('HTTP/1.0 500 Internal Server Error');
            echo json_encode(['success' => false, 'message' => 'La extensión ZipArchive no está disponible en el servidor']);
            exit;
        }
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            header('HTTP/1.0 500 Internal Server Error');
            echo json_encode(['success' => false, 'message' => 'Error al crear archivo ZIP: ' . $zipPath]);
            exit;
        }
        
        // Agregar archivos al ZIP
        $filesAdded = 0;
        foreach ($documents as $doc) {
            $filePath = BASE_PATH . $doc['file_path'];
            if (file_exists($filePath) && is_readable($filePath)) {
                // Usar el nombre original del archivo
                $zip->addFile($filePath, $doc['original_name']);
                $filesAdded++;
            }
        }
        
        if ($filesAdded === 0) {
            $zip->close();
            unlink($zipPath);
            header('HTTP/1.0 404 Not Found');
            echo json_encode(['success' => false, 'message' => 'No se pudieron agregar archivos al ZIP']);
            exit;
        }
        
        $zip->close();
        
        // Verificar que el archivo ZIP se creó correctamente
        if (!file_exists($zipPath) || filesize($zipPath) === 0) {
            header('HTTP/1.0 500 Internal Server Error');
            echo json_encode(['success' => false, 'message' => 'Error al crear el archivo ZIP']);
            exit;
        }
        
        // Enviar el archivo ZIP al cliente
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Pragma: no-cache');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        
        readfile($zipPath);
        
        // Eliminar el archivo temporal
        unlink($zipPath);
        exit;
        
    case 'share':
        // Compartir archivos con otros usuarios
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['email']) || !isset($data['file_ids']) || empty($data['file_ids'])) {
            header('HTTP/1.0 400 Bad Request');
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }
        
        $email = sanitize($data['email']);
        $message = isset($data['message']) ? sanitize($data['message']) : '';
        $expiry = isset($data['expiry']) ? (int)$data['expiry'] : 7; // Días de validez
        $fileIds = array_map('intval', $data['file_ids']);
        
        $db = Database::getInstance();
        
        // Obtener información de los archivos
        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $sql = "SELECT * FROM documents WHERE id IN ($placeholders)";
        
        // Verificar permisos
        if (!isAdmin()) {
            $sql .= " AND user_id = ?";
            $fileIds[] = $_SESSION['user_id'];
        }
        
        $documents = $db->fetchAll($sql, $fileIds);
        
        if (empty($documents)) {
            header('HTTP/1.0 404 Not Found');
            echo json_encode(['success' => false, 'message' => 'No se encontraron archivos']);
            exit;
        }
        
        // Generar token único para compartir
        $shareToken = md5(uniqid(rand(), true));
        $expiryDate = date('Y-m-d H:i:s', strtotime("+$expiry days"));
        
        // Guardar información de compartición en la base de datos
        $shareId = null;
        try {
            // Verificar si existe la tabla shares
            $checkTable = $db->fetchOne("SHOW TABLES LIKE 'shares'");
            if (!$checkTable) {
                // Crear tabla shares si no existe
                $db->execute("CREATE TABLE shares (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    token VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    message TEXT,
                    created_by INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    FOREIGN KEY (created_by) REFERENCES users(id)
                )");
            }
            
            // Verificar si existe la tabla share_documents
            $checkTable = $db->fetchOne("SHOW TABLES LIKE 'share_documents'");
            if (!$checkTable) {
                // Crear tabla share_documents si no existe
                $db->execute("CREATE TABLE share_documents (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    share_id INT NOT NULL,
                    document_id INT NOT NULL,
                    FOREIGN KEY (share_id) REFERENCES shares(id),
                    FOREIGN KEY (document_id) REFERENCES documents(id)
                )");
            }
            
            // Crear registro de compartición
            $sql = "INSERT INTO shares (token, email, message, created_by, expires_at) VALUES (?, ?, ?, ?, ?)";
            $db->execute($sql, [$shareToken, $email, $message, $_SESSION['user_id'], $expiryDate]);
            $shareId = $db->lastInsertId();
            
            // Asociar archivos a la compartición
            foreach ($documents as $doc) {
                $sql = "INSERT INTO share_documents (share_id, document_id) VALUES (?, ?)";
                $db->execute($sql, [$shareId, $doc['id']]);
            }
            
            // Generar URL de compartición
            $shareUrl = BASE_URL . "shared.php?token=" . $shareToken;
            
            // Enviar correo electrónico
            $userName = $_SESSION['name'] ?? 'Un usuario';
            $subject = "Archivos compartidos por $userName";
            $emailBody = "Hola,\n\n$userName ha compartido archivos contigo.\n\n";
            
            if (!empty($message)) {
                $emailBody .= "Mensaje: $message\n\n";
            }
            
            $emailBody .= "Puedes acceder a los archivos a través del siguiente enlace:\n$shareUrl\n\n";
            $emailBody .= "Este enlace expirará el " . date('d/m/Y', strtotime($expiryDate)) . ".\n\n";
            $emailBody .= "Saludos,\nEquipo de GestionUS";
            
            // Enviar correo usando la función existente
            sendEmailNotification($email, $subject, $emailBody);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Archivos compartidos correctamente',
                'share_url' => $shareUrl
            ]);
            
        } catch (Exception $e) {
            // Si hay error y se creó el registro de compartición, eliminarlo
            if ($shareId) {
                $db->execute("DELETE FROM share_documents WHERE share_id = ?", [$shareId]);
                $db->execute("DELETE FROM shares WHERE id = ?", [$shareId]);
            }
            
            echo json_encode(['success' => false, 'message' => 'Error al compartir archivos: ' . $e->getMessage()]);
        }
        break;
        
    case 'delete':
        // Eliminar documento (solo admin)
        if (!isAdmin()) {
            header('HTTP/1.0 403 Forbidden');
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit;
        }
        
        $documentId = isset($_POST['document_id']) ? (int)$_POST['document_id'] : 0;
        
        if ($documentId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de documento no válido']);
            exit;
        }
        
        $db = Database::getInstance();
        
        // Obtener información del documento
        $sql = "SELECT * FROM documents WHERE id = ? LIMIT 1";
        $document = $db->fetchOne($sql, [$documentId]);
        
        if (!$document) {
            echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
            exit;
        }
        
        // Eliminar archivo físico
        $filePath = BASE_PATH . $document['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Eliminar registro de base de datos
        $sql = "DELETE FROM documents WHERE id = ?";
        
        try {
            $db->execute($sql, [$documentId]);
            echo json_encode(['success' => true, 'message' => 'Documento eliminado correctamente']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar documento: ' . $e->getMessage()]);
        }
        break;
        
    default:
        header('HTTP/1.0 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}