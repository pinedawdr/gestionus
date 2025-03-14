<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si es acción de verificación de rol (para login)
$action = $_REQUEST['action'] ?? '';
if ($action !== 'check_role') {
    requireAuth();
    requireAdmin(); // Solo admin puede gestionar usuarios
}

// Manejar diferentes acciones
switch ($action) {
    case 'check_role':
        // Verificar rol del usuario para proceso de login
        $headers = getallheaders();
        if (!isset($headers['Authorization'])) {
            header('HTTP/1.0 401 Unauthorized');
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit;
        }
        
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        $userData = validateFirebaseToken($token);
        
        if (!$userData) {
            header('HTTP/1.0 401 Unauthorized');
            echo json_encode(['success' => false, 'message' => 'Token inválido']);
            exit;
        }
        
        $email = $userData['email'];
        
        $db = Database::getInstance();
        $sql = "SELECT role FROM users WHERE email = ? LIMIT 1";
        $user = $db->fetchOne($sql, [$email]);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit;
        }
        
        echo json_encode(['success' => true, 'role' => $user['role']]);
        break;
        
    case 'list':
        // Listar usuarios
        $db = Database::getInstance();
        $sql = "SELECT id, name, email, dni, punto_digitacion, fecha_nacimiento, role, active, created_at, last_login 
                FROM users ORDER BY name";
                
        try {
            $users = $db->fetchAll($sql);
            
            // Formatear fechas
            foreach ($users as &$user) {
                $user['created_at_formatted'] = formatDate($user['created_at']);
                $user['last_login_formatted'] = $user['last_login'] ? formatDate($user['last_login']) : 'Nunca';
                $user['fecha_nacimiento_formatted'] = $user['fecha_nacimiento'] ? formatDate($user['fecha_nacimiento'], 'd/m/Y') : '';
            }
            
            echo json_encode(['success' => true, 'users' => $users]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al obtener usuarios: ' . $e->getMessage()]);
        }
        break;
        
    case 'create':
        // Verificar método POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('HTTP/1.0 405 Method Not Allowed');
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit;
        }
        
        // Obtener datos del formulario
        $input = json_decode(file_get_contents('php://input'), true);
        
        $name = sanitize($input['name'] ?? '');
        $email = sanitize($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $dni = sanitize($input['dni'] ?? '');
        $puntoDigitacion = sanitize($input['punto_digitacion'] ?? '');
        $fechaNacimiento = sanitize($input['fecha_nacimiento'] ?? '');
        $role = sanitize($input['role'] ?? 'user');
        
        // Validar datos
        if (empty($name) || empty($email) || empty($password) || empty($dni) || empty($puntoDigitacion)) {
            echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
            exit;
        }
        
        // Validar email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Correo electrónico no válido']);
            exit;
        }
        
        // Verificar que el email no exista
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $result = $db->fetchOne($sql, [$email]);
        
        if ($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está registrado']);
            exit;
        }
        
        // Crear usuario en Firebase
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=" . FIREBASE_API_KEY,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                "email" => $email,
                "password" => $password,
                "returnSecureToken" => true
            ]),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json"
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);
        
        if ($err) {
            echo json_encode(['success' => false, 'message' => 'Error al crear usuario en Firebase: ' . $err]);
            exit;
        }
        
        $firebaseData = json_decode($response, true);
        
        if (isset($firebaseData['error'])) {
            echo json_encode(['success' => false, 'message' => 'Error en Firebase: ' . $firebaseData['error']['message']]);
            exit;
        }
        
        // Guardar usuario en nuestra base de datos
        $firebaseUid = $firebaseData['localId'];
        
        $sql = "INSERT INTO users (name, email, firebase_uid, dni, punto_digitacion, fecha_nacimiento, role, active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())";
                
        $params = [
            $name,
            $email,
            $firebaseUid,
            $dni,
            $puntoDigitacion,
            $fechaNacimiento,
            $role
        ];
        
        try {
            $db->execute($sql, $params);
            $userId = $db->lastInsertId();
            
            // Crear carpetas del usuario
            createUserFolders($userId);
            
            echo json_encode(['success' => true, 'message' => 'Usuario creado correctamente', 'user_id' => $userId]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al guardar usuario: ' . $e->getMessage()]);
        }
        break;
        
    case 'update':
        // Actualizar usuario (sin cambiar contraseña)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('HTTP/1.0 405 Method Not Allowed');
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit;
        }
        
        // Obtener datos del formulario
        $input = json_decode(file_get_contents('php://input'), true);
        
        $userId = (int)($input['user_id'] ?? 0);
        $name = sanitize($input['name'] ?? '');
        $dni = sanitize($input['dni'] ?? '');
        $puntoDigitacion = sanitize($input['punto_digitacion'] ?? '');
        $fechaNacimiento = sanitize($input['fecha_nacimiento'] ?? '');
        $role = sanitize($input['role'] ?? 'user');
        $active = isset($input['active']) ? (int)$input['active'] : 1;
        
        // Validar datos
        if ($userId <= 0 || empty($name) || empty($dni) || empty($puntoDigitacion)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos']);
            exit;
        }
        
        // Verificar que el usuario exista
        $db = Database::getInstance();
        $sql = "SELECT * FROM users WHERE id = ? LIMIT 1";
        $user = $db->fetchOne($sql, [$userId]);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit;
        }
        
        // Actualizar usuario en nuestra base de datos
        $sql = "UPDATE users SET name = ?, dni = ?, punto_digitacion = ?, fecha_nacimiento = ?, role = ?, active = ? 
                WHERE id = ?";
                
        $params = [
            $name,
            $dni,
            $puntoDigitacion,
            $fechaNacimiento,
            $role,
            $active,
            $userId
        ];
        
        try {
            $db->execute($sql, $params);
            echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar usuario: ' . $e->getMessage()]);
        }
        break;
        
    case 'change_password':
        // Cambiar contraseña
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('HTTP/1.0 405 Method Not Allowed');
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit;
        }
        
        // Obtener datos del formulario
        $input = json_decode(file_get_contents('php://input'), true);
        
        $userId = (int)($input['user_id'] ?? 0);
        $newPassword = $input['password'] ?? '';
        
        // Validar datos
        if ($userId <= 0 || empty($newPassword) || strlen($newPassword) < 6) {
            echo json_encode(['success' => false, 'message' => 'Contraseña inválida o demasiado corta (mínimo 6 caracteres)']);
            exit;
        }
        
        // Obtener datos del usuario
        $db = Database::getInstance();
        $sql = "SELECT email, firebase_uid FROM users WHERE id = ? LIMIT 1";
        $user = $db->fetchOne($sql, [$userId]);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit;
        }
        
        // Cambiar contraseña en Firebase
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://identitytoolkit.googleapis.com/v1/accounts:update?key=" . FIREBASE_API_KEY,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                "idToken" => $input['id_token'] ?? '', // Token del admin
                "localId" => $user['firebase_uid'],
                "password" => $newPassword,
                "returnSecureToken" => false
            ]),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json"
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);
        
        if ($err) {
            echo json_encode(['success' => false, 'message' => 'Error al cambiar contraseña: ' . $err]);
            exit;
        }
        
        $firebaseData = json_decode($response, true);
        
        if (isset($firebaseData['error'])) {
            echo json_encode(['success' => false, 'message' => 'Error en Firebase: ' . $firebaseData['error']['message']]);
            exit;
        }
        
        echo json_encode(['success' => true, 'message' => 'Contraseña cambiada correctamente']);
        break;
        
    case 'delete':
        // Eliminar usuario
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('HTTP/1.0 405 Method Not Allowed');
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit;
        }
        
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de usuario no válido']);
            exit;
        }
        
        // Evitar eliminación del propio usuario administrador
        if ($userId == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'No puedes eliminar tu propio usuario']);
            exit;
        }
        
        $db = Database::getInstance();
        
        // Obtener información del usuario
        $sql = "SELECT firebase_uid FROM users WHERE id = ? LIMIT 1";
        $user = $db->fetchOne($sql, [$userId]);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit;
        }
        
        // Eliminar usuario en Firebase (requiere token de admin)
        // Esta funcionalidad requiere Firebase Admin SDK o un token especial
        // Por simplicidad, solo desactivamos el usuario en nuestra base de datos
        
        $sql = "UPDATE users SET active = 0 WHERE id = ?";
        
        try {
            $db->execute($sql, [$userId]);
            echo json_encode(['success' => true, 'message' => 'Usuario desactivado correctamente']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al desactivar usuario: ' . $e->getMessage()]);
        }
        break;
        
    default:
        header('HTTP/1.0 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}