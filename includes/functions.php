<?php
require_once 'config.php';
require_once 'db.php';

// Función para sanitizar input
function sanitize($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitize($value);
        }
    } else {
        $input = htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    return $input;
}

// Función para validar token JWT de Firebase
function validateFirebaseToken($token) {
    error_log("Validando token de Firebase...");
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=" . FIREBASE_API_KEY,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode(["idToken" => $token]),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $info = curl_getinfo($curl);
    $err = curl_error($curl);
    
    error_log("Respuesta de Firebase: código " . $info['http_code']);
    error_log("Respuesta body: " . substr($response, 0, 200) . "...");
    
    curl_close($curl);
    
    if ($err) {
        error_log("Error CURL: " . $err);
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['users']) && !empty($data['users'])) {
        error_log("Usuario de Firebase encontrado");
        return $data['users'][0];
    }
    
    error_log("Usuario de Firebase no encontrado: " . print_r($data, true));
    return false;
}

// Función para verificar si el usuario está autenticado
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Función para verificar si el usuario es administrador
function isAdmin() {
    return isAuthenticated() && $_SESSION['role'] === 'admin';
}

// Función para redireccionar si no está autenticado
function requireAuth() {
    if (!isAuthenticated()) {
        header("Location: " . BASE_URL);
        exit;
    }
}

// Función para redireccionar si no es administrador
function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        header("Location: " . BASE_URL . "user/index.php");
        exit;
    }
}

// Función para crear carpetas de usuario
function createUserFolders($userId) {
    $year = date('Y');
    $month = date('m');
    
    $folders = [
        UPLOAD_PATH . "/backups/{$year}/{$month}/{$userId}",
        UPLOAD_PATH . "/evidencias/{$year}/{$month}/{$userId}",
        UPLOAD_PATH . "/reportes_cnv/{$year}/{$month}/{$userId}",
        UPLOAD_PATH . "/otros/{$year}/{$month}/{$userId}"
    ];
    
    foreach ($folders as $folder) {
        if (!file_exists($folder)) {
            mkdir($folder, 0755, true);
        }
    }
    
    return true;
}

// Función para verificar si es día de entrega obligatoria
function isRequiredUploadDay($type) {
    $dayOfWeek = date('N'); // 1 (lunes) a 7 (domingo)
    
    switch ($type) {
        case 'backup':
        case 'evidencia':
            return $dayOfWeek == 5; // Viernes
        case 'reporte_cnv':
            return $dayOfWeek == 2; // Martes
        default:
            return false;
    }
}

// Función para enviar notificación por correo
function sendEmailNotification($to, $subject, $message) {
    $curl = curl_init();
    
    $data = [
        'service_id' => EMAILJS_SERVICE_ID,
        'template_id' => EMAILJS_TEMPLATE_ID,
        'user_id' => EMAILJS_USER_ID,
        'template_params' => [
            'to_email' => $to,
            'subject' => $subject,
            'message' => $message
        ]
    ];
    
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.emailjs.com/api/v1.0/email/send",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        error_log("Error al enviar correo: " . $err);
        return false;
    }
    
    return true;
}

// Función para formatear fecha
function formatDate($date, $format = 'd/m/Y H:i:s') {
    return date($format, strtotime($date));
}

// Función para obtener el tipo de archivo basado en la extensión
function getFileType($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'];
    $documentTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'csv'];
    $compressedTypes = ['zip', 'rar', '7z', 'tar', 'gz'];
    
    if (in_array($extension, $imageTypes)) {
        return 'image';
    } elseif (in_array($extension, $documentTypes)) {
        return 'document';
    } elseif (in_array($extension, $compressedTypes)) {
        return 'compressed';
    } else {
        return 'other';
    }
}

// Función para generar URL segura para archivos
function getSecureFileUrl($path) {
    // Generar un token temporal para acceso al archivo
    $token = md5(session_id() . time() . $path);
    $_SESSION['file_tokens'][$token] = [
        'path' => $path,
        'expires' => time() + 3600 // Expira en 1 hora
    ];
    
    return BASE_URL . "api/documents.php?action=view&token={$token}";
}

// Función para verificar si hay entregas pendientes
function checkPendingUploads($userId) {
    $db = Database::getInstance();
    $today = date('Y-m-d');
    $pendingTypes = [];
    
    // Verificar si es viernes y no hay backups
    if (date('N') == 5) {
        $sql = "SELECT COUNT(*) as count FROM documents 
                WHERE user_id = ? AND type = 'backup' 
                AND DATE(created_at) = ?";
        $result = $db->fetchOne($sql, [$userId, $today]);
        
        if ($result['count'] == 0) {
            $pendingTypes[] = 'backup';
        }
        
        // Verificar evidencias
        $sql = "SELECT COUNT(*) as count FROM documents 
                WHERE user_id = ? AND type = 'evidencia' 
                AND DATE(created_at) = ?";
        $result = $db->fetchOne($sql, [$userId, $today]);
        
        if ($result['count'] == 0) {
            $pendingTypes[] = 'evidencia';
        }
    }
    
    // Verificar si es martes y no hay reportes CNV
    if (date('N') == 2) {
        $sql = "SELECT COUNT(*) as count FROM documents 
                WHERE user_id = ? AND type = 'reporte_cnv' 
                AND DATE(created_at) = ?";
        $result = $db->fetchOne($sql, [$userId, $today]);
        
        if ($result['count'] == 0) {
            $pendingTypes[] = 'reporte_cnv';
        }
    }
    
    return $pendingTypes;
}