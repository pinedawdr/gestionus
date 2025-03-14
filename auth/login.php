<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Asegurar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.0 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener y verificar el token de autenticación
$headers = getallheaders();
$response = ['success' => false, 'message' => 'Error de autenticación'];

if (!isset($headers['Authorization'])) {
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode($response);
    exit;
}

$auth_header = $headers['Authorization'];
$token = str_replace('Bearer ', '', $auth_header);

// Validar token con Firebase
$firebase_user = validateFirebaseToken($token);

if (!$firebase_user) {
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode($response);
    exit;
}

// Obtener datos del cuerpo de la solicitud
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$uid = $input['uid'] ?? '';
$role = $input['role'] ?? '';

// Verificar que los datos coincidan con el token validado
if ($email !== $firebase_user['email'] || $uid !== $firebase_user['localId']) {
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Datos de usuario inconsistentes']);
    exit;
}

// Comprobar si el usuario existe en nuestra base de datos
$db = Database::getInstance();
$sql = "SELECT * FROM users WHERE email = ? AND firebase_uid = ? LIMIT 1";
$user = $db->fetchOne($sql, [$email, $uid]);

if (!$user) {
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Usuario no registrado en el sistema']);
    exit;
}

// Crear la sesión PHP
$_SESSION['user_id'] = $user['id'];
$_SESSION['email'] = $user['email'];
$_SESSION['name'] = $user['name'];
$_SESSION['role'] = $user['role'];
$_SESSION['firebase_uid'] = $user['firebase_uid'];
$_SESSION['last_activity'] = time();

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