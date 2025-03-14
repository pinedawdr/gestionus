<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Configuración de registros
ini_set('log_errors', 1);
ini_set('error_log', '../logs/auth_errors.log');
error_log("Inicio del proceso de login - " . date('Y-m-d H:i:s'));

// Habilitar depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Asegurar que siempre devuelve JSON
header('Content-Type: application/json');

// Asegurar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Método no permitido: " . $_SERVER['REQUEST_METHOD']);
    // header('HTTP/1.0 405 Method Not Allowed'); - Comentada
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener y verificar el token de autenticación
$headers = getallheaders();
$response = ['success' => false, 'message' => 'Error de autenticación'];

error_log("Headers recibidos: " . print_r($headers, true));

if (!isset($headers['Authorization'])) {
    error_log("No se encontró el header Authorization");
    // header('HTTP/1.0 401 Unauthorized'); - Comentada
    echo json_encode(['success' => false, 'message' => 'No se encontró el header de autorización']);
    exit;
}

$auth_header = $headers['Authorization'];
$token = str_replace('Bearer ', '', $auth_header);

error_log("Token recibido: " . substr($token, 0, 20) . "...");

// Validar token con Firebase
$firebase_user = validateFirebaseToken($token);

if (!$firebase_user) {
    error_log("Token de Firebase inválido");
    // header('HTTP/1.0 401 Unauthorized'); - Comentada
    echo json_encode(['success' => false, 'message' => 'Token inválido o expirado']);
    exit;
}

error_log("Usuario de Firebase: " . print_r($firebase_user, true));

// Obtener datos del cuerpo de la solicitud
$input = json_decode(file_get_contents('php://input'), true);
error_log("Datos de entrada: " . print_r($input, true));

$email = $input['email'] ?? '';
$uid = $input['uid'] ?? '';
$role = $input['role'] ?? '';

// Comprobar si el usuario existe en nuestra base de datos
try {
    $db = Database::getInstance();
    error_log("Conexión a la BD establecida");
    
    $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
    error_log("Consultando usuario con email: " . $email);
    
    $user = $db->fetchOne($sql, [$email]);
    
    if (!$user) {
        error_log("Usuario no encontrado en la BD");
        echo json_encode(['success' => false, 'message' => 'Usuario no registrado en el sistema']);
        exit;
    }
    
    error_log("Usuario encontrado: " . print_r($user, true));
    
    // Verificar si el firebase_uid coincide
    if ($user['firebase_uid'] != $uid) {
        error_log("El firebase_uid no coincide. Actualizando...");
        // Actualizar el UID de Firebase si no coincide
        $sql = "UPDATE users SET firebase_uid = ? WHERE id = ?";
        $db->execute($sql, [$uid, $user['id']]);
        error_log("Firebase UID actualizado");
    }
    
    // Crear la sesión PHP
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['firebase_uid'] = $uid;
    $_SESSION['last_activity'] = time();
    
    error_log("Sesión creada: " . print_r($_SESSION, true));
    
    // Registrar inicio de sesión
    $sql = "UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?";
    $db->execute($sql, [$user['id']]);
    
    // Devolver respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Inicio de sesión exitoso',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
    
    error_log("Login completado exitosamente");
} catch (Exception $e) {
    error_log("Error en el proceso de login: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}