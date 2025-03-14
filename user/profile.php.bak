<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar autenticación
requireAuth();

// Obtener información del usuario
$userId = $_SESSION['user_id'];
$db = Database::getInstance();

$sql = "SELECT * FROM users WHERE id = ? LIMIT 1";
$user = $db->fetchOne($sql, [$userId]);

if (!$user) {
    // Si el usuario no existe, cerrar sesión
    header("Location: ../auth/logout.php");
    exit;
}

// Guardar el nombre de usuario en la sesión para el header global
$_SESSION['user_name'] = $user['name'];

// Procesar cambio de contraseña
$passwordChanged = false;
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validar datos
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errorMessage = 'Todos los campos son obligatorios';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = 'Las contraseñas nuevas no coinciden';
    } elseif (strlen($newPassword) < 6) {
        $errorMessage = 'La contraseña debe tener al menos 6 caracteres';
    } else {
        // Autenticar con Firebase para verificar contraseña actual
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=" . FIREBASE_API_KEY,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                "email" => $user['email'],
                "password" => $currentPassword,
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
            $errorMessage = 'Error de conexión: ' . $err;
        } else {
            $authData = json_decode($response, true);
            
            if (isset($authData['error'])) {
                $errorMessage = 'Contraseña actual incorrecta';
            } else {
                // Cambiar contraseña en Firebase
                $idToken = $authData['idToken'];
                
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
                        "idToken" => $idToken,
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
                    $errorMessage = 'Error al cambiar contraseña: ' . $err;
                } else {
                    $updateData = json_decode($response, true);
                    
                    if (isset($updateData['error'])) {
                        $errorMessage = 'Error al actualizar contraseña: ' . $updateData['error']['message'];
                    } else {
                        $passwordChanged = true;
                    }
                }
            }
        }
    }
}

// Set page title for the header
$pageTitle = "Mi Perfil";
$userRole = "user";
?>

<?php include_once '../includes/header.php'; ?>

<!-- Contenido principal -->
<div class="flex-grow">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <!-- Información del usuario -->
            <div class="md:col-span-1">
                <div class="px-4 sm:px-0">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Información Personal</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Detalles de tu cuenta en el sistema GestiónUS.
                    </p>
                </div>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <div class="shadow sm:rounded-md sm:overflow-hidden">
                    <div class="px-4 py-5 bg-white space-y-6 sm:p-6">
                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Nombre</label>
                                <div class="mt-1 bg-gray-50 py-2 px-3 border border-gray-300 rounded-md text-gray-900 text-sm">
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </div>
                            </div>
                            <div class="col-span-6 sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Correo Electrónico</label>
                                <div class="mt-1 bg-gray-50 py-2 px-3 border border-gray-300 rounded-md text-gray-900 text-sm">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </div>
                            </div>
                            <div class="col-span-6 sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">DNI</label>
                                <div class="mt-1 bg-gray-50 py-2 px-3 border border-gray-300 rounded-md text-gray-900 text-sm">
                                    <?php echo htmlspecialchars($user['dni']); ?>
                                </div>
                            </div>
                            <div class="col-span-6 sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Punto de Digitación</label>
                                <div class="mt-1 bg-gray-50 py-2 px-3 border border-gray-300 rounded-md text-gray-900 text-sm">
                                    <?php echo htmlspecialchars($user['punto_digitacion']); ?>
                                </div>
                            </div>
                            <div class="col-span-6 sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Fecha de Nacimiento</label>
                                <div class="mt-1 bg-gray-50 py-2 px-3 border border-gray-300 rounded-md text-gray-900 text-sm">
                                    <?php echo $user['fecha_nacimiento'] ? formatDate($user['fecha_nacimiento'], 'd/m/Y') : 'No especificada'; ?>
                                </div>
                            </div>
                            <div class="col-span-6 sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Fecha de Registro</label>
                                <div class="mt-1 bg-gray-50 py-2 px-3 border border-gray-300 rounded-md text-gray-900 text-sm">
                                    <?php echo formatDate($user['created_at']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-10 sm:mt-0 mb-6">
            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-1">
                    <div class="px-4 sm:px-0">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">Cambiar Contraseña</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Actualiza tu contraseña de acceso al sistema.
                        </p>
                    </div>
                </div>
                <div class="mt-5 md:mt-0 md:col-span-2">
                    <?php if ($passwordChanged): ?>
                    <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700">
                                    Contraseña actualizada correctamente.
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($errorMessage): ?>
                    <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700">
                                    <?php echo htmlspecialchars($errorMessage); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <form action="profile.php" method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="shadow overflow-hidden sm:rounded-md">
                            <div class="px-4 py-5 bg-white sm:p-6">
                                <div class="grid grid-cols-6 gap-6">
                                    <div class="col-span-6 sm:col-span-4">
                                        <label for="current_password" class="block text-sm font-medium text-gray-700">Contraseña Actual</label>
                                        <input type="password" name="current_password" id="current_password" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                    <div class="col-span-6 sm:col-span-4">
                                        <label for="new_password" class="block text-sm font-medium text-gray-700">Nueva Contraseña</label>
                                        <input type="password" name="new_password" id="new_password" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                    <div class="col-span-6 sm:col-span-4">
                                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirmar Nueva Contraseña</label>
                                        <input type="password" name="confirm_password" id="confirm_password" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                </div>
                            </div>
                            <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Cambiar Contraseña
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>