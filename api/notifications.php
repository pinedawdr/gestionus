<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar autenticación
requireAuth();

// Manejar diferentes acciones
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'check':
        // Verificar si hay entregas pendientes para el usuario
        $userId = $_SESSION['user_id'];
        $pendingTypes = checkPendingUploads($userId);
        
        $notifications = [];
        
        // Crear notificaciones basadas en los tipos pendientes
        if (in_array('backup', $pendingTypes)) {
            $notifications[] = [
                'type' => 'warning',
                'message' => 'Tienes pendiente la subida del backup de hoy (viernes)',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        if (in_array('evidencia', $pendingTypes)) {
            $notifications[] = [
                'type' => 'warning',
                'message' => 'Tienes pendiente la subida de evidencias de envío de hoy (viernes)',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        if (in_array('reporte_cnv', $pendingTypes)) {
            $notifications[] = [
                'type' => 'warning',
                'message' => 'Tienes pendiente la subida del reporte CNV de hoy (martes)',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Si es admin, verificar entregas pendientes de todos los usuarios
        if (isAdmin()) {
            $db = Database::getInstance();
            
            // Obtener usuarios activos
            $sql = "SELECT id, name, email FROM users WHERE active = 1 AND role = 'user'";
            $users = $db->fetchAll($sql);
            
            foreach ($users as $user) {
                $userPendingTypes = checkPendingUploads($user['id']);
                
                if (!empty($userPendingTypes)) {
                    $pendingList = implode(', ', array_map(function($type) {
                        switch ($type) {
                            case 'backup': return 'backup';
                            case 'evidencia': return 'evidencias de envío';
                            case 'reporte_cnv': return 'reporte CNV';
                            default: return $type;
                        }
                    }, $userPendingTypes));
                    
                    $notifications[] = [
                        'type' => 'info',
                        'message' => "El usuario {$user['name']} tiene pendiente: {$pendingList}",
                        'created_at' => date('Y-m-d H:i:s'),
                        'user_id' => $user['id']
                    ];
                }
            }
        }
        
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        break;
        
    case 'send':
        // Enviar notificación por correo
        if (!isAdmin()) {
            header('HTTP/1.0 403 Forbidden');
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('HTTP/1.0 405 Method Not Allowed');
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit;
        }
        
        // Obtener datos del formulario
        $input = json_decode(file_get_contents('php://input'), true);
        
        $userId = (int)($input['user_id'] ?? 0);
        $subject = sanitize($input['subject'] ?? '');
        $message = sanitize($input['message'] ?? '');
        
        if ($userId <= 0 || empty($subject) || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }
        
        // Obtener información del usuario
        $db = Database::getInstance();
        $sql = "SELECT name, email FROM users WHERE id = ? LIMIT 1";
        $user = $db->fetchOne($sql, [$userId]);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit;
        }
        
        // Enviar notificación por correo
        $success = sendEmailNotification($user['email'], $subject, $message);
        
        if ($success) {
            // Registrar notificación en la base de datos
            $sql = "INSERT INTO notifications (user_id, subject, message, created_at) 
                    VALUES (?, ?, ?, NOW())";
            
            try {
                $db->execute($sql, [$userId, $subject, $message]);
                $notificationId = $db->lastInsertId();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Notificación enviada correctamente',
                    'notification_id' => $notificationId
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error al registrar notificación: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al enviar notificación por correo']);
        }
        break;
        
    default:
        header('HTTP/1.0 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}