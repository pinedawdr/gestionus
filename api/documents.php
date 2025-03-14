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
        
        // Enviar headers para visualización o descarga
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        
        // Leer y enviar el archivo
        readfile($filePath);
        exit;
        
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