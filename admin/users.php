<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar autenticación y rol de administrador
requireAdmin();

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

// Parámetros de filtrado
$filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : '';
$role = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Obtener usuarios con filtros
$params = [];
$sql = "SELECT * FROM users WHERE 1=1";

if (!empty($filter)) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR dni LIKE ? OR punto_digitacion LIKE ?)";
    $searchTerm = "%{$filter}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($role)) {
    $sql .= " AND role = ?";
    $params[] = $role;
}

if ($status !== '') {
    $sql .= " AND active = ?";
    $params[] = (int)$status;
}

$sql .= " ORDER BY name ASC";

$users = $db->fetchAll($sql, $params);

// Formatear fechas para los usuarios
foreach ($users as &$userItem) {
    $userItem['created_at_formatted'] = formatDate($userItem['created_at']);
    $userItem['last_login_formatted'] = $userItem['last_login'] ? formatDate($userItem['last_login']) : 'Nunca';
    $userItem['fecha_nacimiento_formatted'] = $userItem['fecha_nacimiento'] ? formatDate($userItem['fecha_nacimiento'], 'd/m/Y') : 'No especificada';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - GestiónUS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <!-- Navegación principal -->
        <nav class="bg-blue-600 text-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <span class="text-xl font-bold">GestiónUS</span>
                            <span class="ml-2 bg-yellow-500 text-blue-900 text-xs px-2 py-1 rounded">ADMIN</span>
                        </div>
                        <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                            <a href="index.php" class="border-transparent border-b-2 hover:border-gray-300 px-1 pt-1 text-sm font-medium">Dashboard</a>
                            <a href="users.php" class="border-b-2 border-white px-1 pt-1 text-sm font-medium">Usuarios</a>
                            <a href="documents.php" class="border-transparent border-b-2 hover:border-gray-300 px-1 pt-1 text-sm font-medium">Documentos</a>
                            <a href="reports.php" class="border-transparent border-b-2 hover:border-gray-300 px-1 pt-1 text-sm font-medium">Reportes</a>
                            <a href="settings.php" class="border-transparent border-b-2 hover:border-gray-300 px-1 pt-1 text-sm font-medium">Configuración</a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="ml-3 relative">
                            <div class="flex items-center">
                                <span class="mr-2"><?php echo htmlspecialchars($user['name']); ?></span>
                                <a href="../auth/logout.php" class="bg-blue-700 hover:bg-blue-800 text-sm px-4 py-2 rounded">Cerrar Sesión</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Contenido principal -->
        <div class="flex-grow">
            <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <h1 class="text-2xl font-semibold text-gray-900 mb-6">Gestión de Usuarios</h1>
                
                <!-- Filtros y botón de crear usuario -->
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                            <div class="flex-1">
                                <form action="" method="GET" class="flex flex-col md:flex-row md:items-end space-y-4 md:space-y-0 md:space-x-4">
                                    <div class="flex-1">
                                        <label for="filter" class="block text-sm font-medium text-gray-700">Buscar</label>
                                        <input type="text" name="filter" id="filter" value="<?php echo htmlspecialchars($filter); ?>" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" placeholder="Nombre, Email, DNI...">
                                    </div>
                                    <div>
                                        <label for="role" class="block text-sm font-medium text-gray-700">Rol</label>
                                        <select name="role" id="role" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="">Todos</option>
                                            <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                            <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>Usuario</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                                        <select name="status" id="status" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="">Todos</option>
                                            <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Activo</option>
                                            <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Inactivo</option>
                                        </select>
                                    </div>
                                    <div class="flex space-x-3">
                                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            Filtrar
                                        </button>
                                        <a href="users.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            Limpiar
                                        </a>
                                    </div>
                                </form>
                            </div>
                            <div class="mt-4 md:mt-0">
                                <button type="button" id="create-user-btn" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                    </svg>
                                    Nuevo Usuario
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Lista de usuarios -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-4 py-5 sm:p-6">
                        <?php if (!empty($users)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Correo Electrónico</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Punto de Digitación</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rol</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Última Conexión</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($users as $userItem): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($userItem['name']); ?></div>
                                                    <div class="text-sm text-gray-500">DNI: <?php echo htmlspecialchars($userItem['dni']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($userItem['email']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($userItem['punto_digitacion']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $userItem['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                <?php echo $userItem['role'] === 'admin' ? 'Administrador' : 'Usuario'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $userItem['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo $userItem['active'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $userItem['last_login_formatted']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button type="button" class="text-blue-600 hover:text-blue-900 edit-user-btn" data-user-id="<?php echo $userItem['id']; ?>" data-user="<?php echo htmlspecialchars(json_encode($userItem)); ?>">Editar</button>
                                            <button type="button" class="ml-3 text-indigo-600 hover:text-indigo-900 change-password-btn" data-user-id="<?php echo $userItem['id']; ?>" data-user-name="<?php echo htmlspecialchars($userItem['name']); ?>">Contraseña</button>
                                            <?php if ($userItem['id'] != $userId): ?>
                                            <button type="button" class="ml-3 text-red-600 hover:text-red-900 delete-user-btn" data-user-id="<?php echo $userItem['id']; ?>" data-user-name="<?php echo htmlspecialchars($userItem['name']); ?>">Eliminar</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay usuarios</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                No se encontraron usuarios con los filtros aplicados.
                            </p>
                            <div class="mt-6">
                                <button type="button" id="create-user-empty-btn" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                    </svg>
                                    Nuevo Usuario
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de crear/editar usuario -->
        <div id="userModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Overlay de fondo -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="user-modal-title">
                                Nuevo Usuario
                            </h3>
                            <div class="mt-4">
                                <form id="userForm">
                                    <input type="hidden" id="user-id">
                                    <div class="grid grid-cols-6 gap-6">
                                        <div class="col-span-6">
                                            <label for="user-name" class="block text-sm font-medium text-gray-700">Nombre Completo</label>
                                            <input type="text" id="user-name" name="name" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        </div>
                                        <div class="col-span-6 sm:col-span-3">
                                            <label for="user-email" class="block text-sm font-medium text-gray-700">Correo Electrónico</label>
                                            <input type="email" id="user-email" name="email" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        </div>
                                        <div class="col-span-6 sm:col-span-3" id="password-container">
                                            <label for="user-password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                                            <input type="password" id="user-password" name="password" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        </div>
                                        <div class="col-span-6 sm:col-span-3">
                                            <label for="user-dni" class="block text-sm font-medium text-gray-700">DNI</label>
                                            <input type="text" id="user-dni" name="dni" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        </div>
                                        <div class="col-span-6 sm:col-span-3">
                                            <label for="user-punto-digitacion" class="block text-sm font-medium text-gray-700">Punto de Digitación</label>
                                            <input type="text" id="user-punto-digitacion" name="punto_digitacion" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        </div>
                                        <div class="col-span-6 sm:col-span-3">
                                            <label for="user-fecha-nacimiento" class="block text-sm font-medium text-gray-700">Fecha de Nacimiento</label>
                                            <input type="date" id="user-fecha-nacimiento" name="fecha_nacimiento" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        </div>
                                        <div class="col-span-6 sm:col-span-3">
                                            <label for="user-role" class="block text-sm font-medium text-gray-700">Rol</label>
                                            <select id="user-role" name="role" class="mt-1 block w-full bg-white border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                <option value="user">Usuario</option>
                                                <option value="admin">Administrador</option>
                                            </select>
                                        </div>
                                        <div class="col-span-6" id="user-active-container">
                                            <label for="user-active" class="block text-sm font-medium text-gray-700">Estado</label>
                                            <select id="user-active" name="active" class="mt-1 block w-full bg-white border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                <option value="1">Activo</option>
                                                <option value="0">Inactivo</option>
                                            </select>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                        <button type="button" id="save-user-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Guardar
                        </button>
                        <button type="button" id="close-user-modal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de cambiar contraseña -->
        <div id="passwordModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Overlay de fondo -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="password-modal-title">
                                Cambiar Contraseña
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500" id="password-user-name">
                                    Cambiar contraseña para: <span class="font-medium"></span>
                                </p>
                            </div>
                            <div class="mt-4">
                                <form id="passwordForm">
                                    <input type="hidden" id="password-user-id">
                                    <div>
                                        <label for="new-password" class="block text-sm font-medium text-gray-700">Nueva Contraseña</label>
                                        <input type="password" id="new-password" name="password" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        <p class="mt-1 text-xs text-gray-500">Mínimo 6 caracteres</p>
                                    </div>
                                    <div class="mt-4">
                                        <label for="confirm-password" class="block text-sm font-medium text-gray-700">Confirmar Contraseña</label>
                                        <input type="password" id="confirm-password" name="confirm_password" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                        <button type="button" id="save-password-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Cambiar Contraseña
                        </button>
  <button type="button" id="close-password-modal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación de eliminación -->
        <div id="deleteModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Overlay de fondo -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="delete-modal-title">
                                Eliminar Usuario
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500" id="delete-user-name">
                                    ¿Estás seguro de que deseas eliminar al usuario <span class="font-medium"></span>?
                                </p>
                                <p class="text-sm text-gray-500 mt-2">
                                    Esta acción desactivará al usuario y no podrá acceder al sistema. Sus documentos permanecerán en el sistema.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                        <button type="button" id="confirm-delete-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Eliminar
                        </button>
                        <button type="button" id="close-delete-modal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pie de página -->
        <footer class="bg-white">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <p class="text-center text-sm text-gray-500">
                    &copy; <?php echo date('Y'); ?> GestiónUS - Todos los derechos reservados
                </p>
            </div>
        </footer>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Referencias a elementos del DOM
        const userModal = document.getElementById('userModal');
        const passwordModal = document.getElementById('passwordModal');
        const deleteModal = document.getElementById('deleteModal');
        
        // Botones para abrir modales
        const createUserBtn = document.getElementById('create-user-btn');
        const createUserEmptyBtn = document.getElementById('create-user-empty-btn');
        const editUserBtns = document.querySelectorAll('.edit-user-btn');
        const changePasswordBtns = document.querySelectorAll('.change-password-btn');
        const deleteUserBtns = document.querySelectorAll('.delete-user-btn');
        
        // Botones para cerrar modales
        const closeUserModalBtn = document.getElementById('close-user-modal');
        const closePasswordModalBtn = document.getElementById('close-password-modal');
        const closeDeleteModalBtn = document.getElementById('close-delete-modal');
        
        // Botones para acciones
        const saveUserBtn = document.getElementById('save-user-btn');
        const savePasswordBtn = document.getElementById('save-password-btn');
        const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
        
        // Formularios
        const userForm = document.getElementById('userForm');
        const passwordForm = document.getElementById('passwordForm');
        
        // Campos de formulario usuario
        const userId = document.getElementById('user-id');
        const userName = document.getElementById('user-name');
        const userEmail = document.getElementById('user-email');
        const userPassword = document.getElementById('user-password');
        const userDni = document.getElementById('user-dni');
        const userPuntoDigitacion = document.getElementById('user-punto-digitacion');
        const userFechaNacimiento = document.getElementById('user-fecha-nacimiento');
        const userRole = document.getElementById('user-role');
        const userActive = document.getElementById('user-active');
        const passwordContainer = document.getElementById('password-container');
        const userActiveContainer = document.getElementById('user-active-container');
        
        // Campos de formulario cambio contraseña
        const passwordUserId = document.getElementById('password-user-id');
        const passwordUserName = document.querySelector('#password-user-name span');
        const newPassword = document.getElementById('new-password');
        const confirmPassword = document.getElementById('confirm-password');
        
        // Campos de formulario eliminar
        const deleteUserNameSpan = document.querySelector('#delete-user-name span');
        
        // Variable para almacenar tokens de autenticación
        let authToken = '';
        
        // Función para abrir modal de crear usuario
        function openCreateUserModal() {
            // Limpiar formulario
            userForm.reset();
            userId.value = '';
            
            // Mostrar campo de contraseña y ocultar estado
            passwordContainer.classList.remove('hidden');
            userActiveContainer.classList.add('hidden');
            
            // Cambiar título del modal
            document.getElementById('user-modal-title').textContent = 'Nuevo Usuario';
            
            // Abrir modal
            userModal.classList.remove('hidden');
        }
        
        // Función para abrir modal de editar usuario
        function openEditUserModal(userData) {
            // Llenar formulario con datos del usuario
            userId.value = userData.id;
            userName.value = userData.name;
            userEmail.value = userData.email;
            userDni.value = userData.dni;
            userPuntoDigitacion.value = userData.punto_digitacion;
            userFechaNacimiento.value = userData.fecha_nacimiento ? userData.fecha_nacimiento.split(' ')[0] : '';
            userRole.value = userData.role;
            userActive.value = userData.active;
            
            // Ocultar campo de contraseña y mostrar estado
            passwordContainer.classList.add('hidden');
            userActiveContainer.classList.remove('hidden');
            
            // Cambiar título del modal
            document.getElementById('user-modal-title').textContent = 'Editar Usuario';
            
            // Abrir modal
            userModal.classList.remove('hidden');
        }
        
        // Función para abrir modal de cambiar contraseña
        function openChangePasswordModal(userId, userName) {
            // Limpiar formulario
            passwordForm.reset();
            
            // Llenar datos
            passwordUserId.value = userId;
            passwordUserName.textContent = userName;
            
            // Abrir modal
            passwordModal.classList.remove('hidden');
        }
        
        // Función para abrir modal de confirmación de eliminación
        function openDeleteModal(userId, userName) {
            // Llenar datos
            document.querySelector('#confirm-delete-btn').dataset.userId = userId;
            deleteUserNameSpan.textContent = userName;
            
            // Abrir modal
            deleteModal.classList.remove('hidden');
        }
        
        // Event Listeners para abrir modales
        if (createUserBtn) {
            createUserBtn.addEventListener('click', openCreateUserModal);
        }
        
        if (createUserEmptyBtn) {
            createUserEmptyBtn.addEventListener('click', openCreateUserModal);
        }
        
        editUserBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const userData = JSON.parse(this.dataset.user);
                openEditUserModal(userData);
            });
        });
        
        changePasswordBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.dataset.userId;
                const userName = this.dataset.userName;
                openChangePasswordModal(userId, userName);
            });
        });
        
        deleteUserBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.dataset.userId;
                const userName = this.dataset.userName;
                openDeleteModal(userId, userName);
            });
        });
        
        // Event Listeners para cerrar modales
        closeUserModalBtn.addEventListener('click', function() {
            userModal.classList.add('hidden');
        });
        
        closePasswordModalBtn.addEventListener('click', function() {
            passwordModal.classList.add('hidden');
        });
        
        closeDeleteModalBtn.addEventListener('click', function() {
            deleteModal.classList.add('hidden');
        });
        
        // Event Listeners para cerrar modales haciendo clic fuera
        window.addEventListener('click', function(event) {
            if (event.target == userModal) {
                userModal.classList.add('hidden');
            } else if (event.target == passwordModal) {
                passwordModal.classList.add('hidden');
            } else if (event.target == deleteModal) {
                deleteModal.classList.add('hidden');
            }
        });
        
        // Event Listener para guardar usuario (crear o editar)
        saveUserBtn.addEventListener('click', function() {
            // Validar formulario
            if (!userName.value || !userDni.value || !userPuntoDigitacion.value) {
                alert('Por favor, complete los campos obligatorios: Nombre, DNI y Punto de Digitación');
                return;
            }
            
            if (!userId.value && (!userEmail.value || !userPassword.value)) {
                alert('El correo electrónico y la contraseña son obligatorios para nuevos usuarios');
                return;
            }
            
            if (!userId.value && userPassword.value.length < 6) {
                alert('La contraseña debe tener al menos 6 caracteres');
                return;
            }
            
            // Deshabilitar botón
            saveUserBtn.disabled = true;
            saveUserBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Guardando...';
            
            // Preparar datos
            const formData = {
                name: userName.value,
                dni: userDni.value,
                punto_digitacion: userPuntoDigitacion.value,
                fecha_nacimiento: userFechaNacimiento.value || null,
                role: userRole.value
            };
            
            if (!userId.value) {
                // Crear usuario
                formData.email = userEmail.value;
                formData.password = userPassword.value;
                
                fetch('../api/users.php?action=create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Usuario creado correctamente');
                        userModal.classList.add('hidden');
                        
                        // Recargar página
                        window.location.reload();
                    } else {
                        alert('Error al crear usuario: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error de conexión al crear usuario');
                })
                .finally(() => {
                    // Re-habilitar botón
                    saveUserBtn.disabled = false;
                    saveUserBtn.innerHTML = 'Guardar';
                });
            } else {
                // Editar usuario
                formData.user_id = userId.value;
                formData.active = userActive.value;
                
                fetch('../api/users.php?action=update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Usuario actualizado correctamente');
                        userModal.classList.add('hidden');
                        
                        // Recargar página
                        window.location.reload();
                    } else {
                        alert('Error al actualizar usuario: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error de conexión al actualizar usuario');
                })
                .finally(() => {
                    // Re-habilitar botón
                    saveUserBtn.disabled = false;
                    saveUserBtn.innerHTML = 'Guardar';
                });
            }
        });
        
        // Event Listener para cambiar contraseña
        savePasswordBtn.addEventListener('click', function() {
            // Validar formulario
            if (!newPassword.value || !confirmPassword.value) {
                alert('Por favor, complete todos los campos');
                return;
            }
            
            if (newPassword.value.length < 6) {
                alert('La contraseña debe tener al menos 6 caracteres');
                return;
            }
            
            if (newPassword.value !== confirmPassword.value) {
                alert('Las contraseñas no coinciden');
                return;
            }
            
            // Deshabilitar botón
            savePasswordBtn.disabled = true;
            savePasswordBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Cambiando...';
            
            // Preparar datos
            const formData = {
                user_id: passwordUserId.value,
                password: newPassword.value,
                id_token: authToken // Token de autenticación (se debería obtener de una solicitud previa)
            };
            
            fetch('../api/users.php?action=change_password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Contraseña cambiada correctamente');
                    passwordModal.classList.add('hidden');
                } else {
                    alert('Error al cambiar contraseña: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión al cambiar contraseña');
            })
            .finally(() => {
                // Re-habilitar botón
                savePasswordBtn.disabled = false;
                savePasswordBtn.innerHTML = 'Cambiar Contraseña';
            });
        });
        
        // Event Listener para eliminar usuario
        confirmDeleteBtn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            
            // Deshabilitar botón
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Eliminando...';
            
            // Enviar solicitud
            const formData = new FormData();
            formData.append('user_id', userId);
            
            fetch('../api/users.php?action=delete', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Usuario desactivado correctamente');
                    deleteModal.classList.add('hidden');
                    
                    // Recargar página
                    window.location.reload();
                } else {
                    alert('Error al desactivar usuario: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión al desactivar usuario');
            })
            .finally(() => {
                // Re-habilitar botón
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = 'Eliminar';
            });
        });
    });
    </script>
</body>
</html>