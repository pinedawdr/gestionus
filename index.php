<?php
session_start();
if(isset($_SESSION['user_id'])) {
    // Redireccionar a panel de usuario o administrador según rol
    if($_SESSION['role'] === 'admin') {
        header("Location: admin/index.php");
    } else {
        header("Location: user/index.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GestiónUS - Sistema de Gestión de Documentos</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="bg-blue-600 py-4">
                <h2 class="text-center text-white text-2xl font-bold">GestiónUS</h2>
                <p class="text-center text-white text-sm">Sistema de Gestión de Documentos - Unidad de Seguros</p>
            </div>
            <div class="p-6">
                <div id="loginForm" class="space-y-4">
                    <div class="text-center mb-6">
                        <h3 class="text-xl font-medium text-gray-700">Iniciar Sesión</h3>
                        <p class="text-sm text-gray-500">Ingrese sus credenciales para acceder al sistema</p>
                    </div>
                    <div id="error-message" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert"></div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Correo Electrónico</label>
                        <input type="email" id="email" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                        <input type="password" id="password" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <button id="loginBtn" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Ingresar
                        </button>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 py-3 px-6 border-t border-gray-200">
                <p class="text-xs text-center text-gray-500">
                    &copy; <?php echo date('Y'); ?> GestiónUS - Todos los derechos reservados
                </p>
            </div>
        </div>
    </div>

    <!-- Scripts de Firebase -->
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-auth-compat.js"></script>
    <script src="firebase-config.js"></script>
    <script src="assets/js/auth.js"></script>
</body>
</html>