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
    
    // Verificar que el token no esté vacío
    if (empty($token)) {
        error_log("Token vacío");
        return false;
    }
    
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
    if ($response) {
        error_log("Respuesta body: " . substr($response, 0, 200) . "...");
    } else {
        error_log("No hay respuesta de Firebase");
    }
    
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

// Función para verificar si es día de entrega obligatoria usando tiempo del servidor
function isRequiredUploadDay($type) {
    $serverDate = new DateTime('now', new DateTimeZone('America/Lima'));
    $dayOfWeek = $serverDate->format('N'); // 1 (lunes) a 7 (domingo)
    $currentHour = (int)$serverDate->format('G'); // 0-23 formato 24h
    
    switch ($type) {
        case 'backup':
        case 'evidencia':
            return $dayOfWeek == 5 && $currentHour < 18; // Viernes antes de las 18:00
        case 'reporte_cnv':
            return $dayOfWeek == 2 && $currentHour < 12; // Martes antes de las 12:00
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
    
    // Verificar si existe la columna server_timestamp
    try {
        $columnExists = $db->fetchOne("SHOW COLUMNS FROM documents LIKE 'server_timestamp'");
    } catch (Exception $e) {
        $columnExists = false;
        error_log("Error al verificar columna server_timestamp: " . $e->getMessage());
    }
    
    // Usar tiempo del servidor para la verificación
    $serverDate = new DateTime('now', new DateTimeZone('America/Lima'));
    $today = $serverDate->format('Y-m-d');
    $dayOfWeek = $serverDate->format('N');
    $pendingTypes = [];
    
    // Usar la columna adecuada según exista o no server_timestamp
    $dateColumn = $columnExists ? 'server_timestamp' : 'created_at';
    
    // Verificar si es viernes y no hay backups
    if ($dayOfWeek == 5) {
        $sql = "SELECT COUNT(*) as count FROM documents 
                WHERE user_id = ? AND type = 'backup' 
                AND DATE({$dateColumn}) = ?";
        $result = $db->fetchOne($sql, [$userId, $today]);
        
        if ($result['count'] == 0) {
            $pendingTypes[] = 'backup';
        }
        
        // Verificar evidencias
        $sql = "SELECT COUNT(*) as count FROM documents 
                WHERE user_id = ? AND type = 'evidencia' 
                AND DATE({$dateColumn}) = ?";
        $result = $db->fetchOne($sql, [$userId, $today]);
        
        if ($result['count'] == 0) {
            $pendingTypes[] = 'evidencia';
        }
    }
    
    // Verificar si es martes y no hay reportes CNV
    if ($dayOfWeek == 2) {
        $sql = "SELECT COUNT(*) as count FROM documents 
                WHERE user_id = ? AND type = 'reporte_cnv' 
                AND DATE({$dateColumn}) = ?";
        $result = $db->fetchOne($sql, [$userId, $today]);
        
        if ($result['count'] == 0) {
            $pendingTypes[] = 'reporte_cnv';
        }
    }
    
    return $pendingTypes;
}

// Función para formatear tamaño de archivo
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

/**
 * Obtiene el tiempo del servidor
 * @return DateTime Objeto DateTime con el tiempo actual del servidor
 */
function getServerTime() {
    return new DateTime('now', new DateTimeZone('America/Lima'));
}

/**
 * Registra un evento de seguridad en el sistema
 * @param int $userId ID del usuario
 * @param string $action Acción realizada
 * @param string $details Detalles del evento
 * @return bool Éxito de la operación
 */
function logSecurityEvent($userId, $action, $details) {
    try {
        $db = Database::getInstance();
        
        // Verificar si existe la tabla security_logs
        $checkTable = $db->fetchOne("SHOW TABLES LIKE 'security_logs'");
        if (!$checkTable) {
            // Crear tabla security_logs si no existe
            $db->execute("CREATE TABLE security_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action VARCHAR(50) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                server_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )");
        }
        
        $sql = "INSERT INTO security_logs (user_id, action, details, ip_address, user_agent, server_time) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $db->execute($sql, [
            $userId, 
            $action, 
            $details, 
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido'
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error al registrar evento de seguridad: " . $e->getMessage());
        return false;
    }
}

/**
 * Registra un intento de subida de archivos
 * @param int $userId ID del usuario
 * @param string $documentType Tipo de documento
 * @param string $serverTime Tiempo del servidor
 * @return bool Éxito de la operación
 */
function logUploadAttempt($userId, $documentType, $serverTime) {
    try {
        $db = Database::getInstance();
        
        // Verificar si existe la tabla upload_attempts
        $checkTable = $db->fetchOne("SHOW TABLES LIKE 'upload_attempts'");
        if (!$checkTable) {
            // Crear tabla upload_attempts si no existe
            $db->execute("CREATE TABLE upload_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                document_type VARCHAR(20) NOT NULL,
                server_time DATETIME NOT NULL,
                client_time DATETIME, 
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )");
        }
        
        $sql = "INSERT INTO upload_attempts (user_id, document_type, server_time, ip_address, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        $db->execute($sql, [
            $userId, 
            $documentType, 
            $serverTime, 
            $_SERVER['REMOTE_ADDR']
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error al registrar intento de subida: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica si el usuario está intentando manipular el tiempo
 * @param int $userId ID del usuario
 * @return bool true si hay posible manipulación
 */
function detectTimeTampering($userId) {
    try {
        $db = Database::getInstance();
        
        // Obtener el último intento de subida
        $sql = "SELECT * FROM upload_attempts WHERE user_id = ? ORDER BY id DESC LIMIT 1";
        $lastAttempt = $db->fetchOne($sql, [$userId]);
        
        if (!$lastAttempt) {
            return false;
        }
        
        // Verificar si hay múltiples intentos en horarios sospechosos
        $sql = "SELECT COUNT(*) as count FROM security_logs 
                WHERE user_id = ? AND action = 'TIME_RESTRICTION_VIOLATION' 
                AND server_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = $db->fetchOne($sql, [$userId]);
        
        // Si hay más de 3 intentos en las últimas 24 horas, es sospechoso
        if ($result['count'] > 3) {
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error al detectar manipulación de tiempo: " . $e->getMessage());
        return false;
    }
}

/**
 * Envía una alerta al administrador si se detecta manipulación de tiempo
 * @param int $userId ID del usuario
 * @param string $evidence Evidencia de la manipulación
 * @return bool Éxito de la operación
 */
function alertAdminOfTimeTampering($userId, $evidence) {
    try {
        $db = Database::getInstance();
        
        // Obtener información del usuario
        $sql = "SELECT name, email FROM users WHERE id = ? LIMIT 1";
        $user = $db->fetchOne($sql, [$userId]);
        
        if (!$user) {
            return false;
        }
        
        // Obtener administradores
        $sql = "SELECT email FROM users WHERE role = 'admin' AND active = 1";
        $admins = $db->fetchAll($sql);
        
        if (empty($admins)) {
            return false;
        }
        
        // Preparar mensaje
        $subject = "ALERTA: Posible manipulación de tiempo por " . $user['name'];
        $message = "Se ha detectado una posible manipulación de tiempo por parte del usuario {$user['name']} ({$user['email']}).\n\n";
        $message .= "Evidencia: $evidence\n\n";
        $message .= "IP del usuario: " . $_SERVER['REMOTE_ADDR'] . "\n";
        $message .= "Fecha y hora del servidor: " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "Este es un mensaje automático generado por el sistema Gestionus.";
        
        // Enviar correo a cada administrador
        foreach ($admins as $admin) {
            sendEmailNotification($admin['email'], $subject, $message);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar alerta de manipulación de tiempo: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica las restricciones horarias para los tipos de documentos
 * @param string $type Tipo de documento
 * @return array [success: bool, message: string]
 */
function checkTimeRestrictions($type) {
    // Obtener tiempo del servidor
    $serverDate = new DateTime('now', new DateTimeZone('America/Lima'));
    $dayOfWeek = $serverDate->format('N'); // 1 (lunes) a 7 (domingo)
    $currentHour = (int)$serverDate->format('G'); // 0-23 formato 24h
    
    if ($type === 'reporte_cnv') {
        // CNV: Solo martes de 00:00 hasta 12:00
        if ($dayOfWeek != 2 || ($dayOfWeek == 2 && $currentHour >= 12)) {
            return [
                'success' => false,
                'message' => 'Los reportes CNV solo pueden subirse los martes de 00:00 a 12:00'
            ];
        }
    } elseif ($type === 'backup' || $type === 'evidencia') {
        // Backups y Evidencias: Solo viernes de 00:00 hasta 18:00
        if ($dayOfWeek != 5 || ($dayOfWeek == 5 && $currentHour >= 18)) {
            return [
                'success' => false,
                'message' => 'Los backups y evidencias solo pueden subirse los viernes de 00:00 a 18:00'
            ];
        }
    }
    
    return ['success' => true, 'message' => ''];
}