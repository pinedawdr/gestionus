<?php
// Determinar la ruta base para los enlaces
$base_path = '';
$current_path = $_SERVER['SCRIPT_NAME'];

if (strpos($current_path, '/admin/') !== false) {
    $base_path = '..';
} elseif (strpos($current_path, '/user/') !== false) {
    $base_path = '..';
} elseif (strpos($current_path, '/auth/') !== false) {
    $base_path = '..';
}

// Determinar si es admin o usuario regular
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Obtener nombre de usuario si está disponible
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : (isset($user['name']) ? $user['name'] : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>GestiónUS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/styles.css">
    <?php if (isset($extra_css)): echo $extra_css; endif; ?>
    <style>
        .nav-link {
            position: relative;
            transition: all 0.3s ease;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: white;
            transition: width 0.3s ease;
        }
        .nav-link:hover::after, .nav-link.active::after {
            width: 100%;
        }
        .logo-container {
            transition: transform 0.3s ease;
        }
        .logo-container:hover {
            transform: scale(1.05);
        }
        @media (max-width: 640px) {
            .mobile-menu {
                display: none;
            }
            .mobile-menu.active {
                display: block;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <!-- Navegación principal -->
        <nav class="bg-gradient-to-r from-blue-700 to-blue-500 text-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 flex items-center logo-container">
                            <a href="<?php echo $base_path; ?>/<?php echo $is_admin ? 'admin' : 'user'; ?>/index.php" class="flex items-center">
                                <!-- SVG Logo -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white">
                                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                                    <circle cx="12" cy="8" r="2"></circle>
                                    <path d="M5 12a7 7 0 0 0 14 0"></path>
                                </svg>
                                <span class="ml-2 text-xl font-bold tracking-tight">GestiónUS</span>
                                <?php if ($is_admin): ?>
                                <span class="ml-2 bg-yellow-500 text-blue-900 text-xs px-2 py-1 rounded-full font-semibold">ADMIN</span>
                                <?php endif; ?>
                            </a>
                        </div>
                        
                        <!-- Menú de navegación para pantallas medianas y grandes -->
                        <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                            <?php if ($is_admin): ?>
                                <a href="<?php echo $base_path; ?>/admin/index.php" class="nav-link px-3 py-2 text-sm font-medium <?php echo (strpos($current_path, '/admin/index.php') !== false) ? 'active' : ''; ?>">Dashboard</a>
                                <a href="<?php echo $base_path; ?>/admin/users.php" class="nav-link px-3 py-2 text-sm font-medium <?php echo (strpos($current_path, '/admin/users.php') !== false) ? 'active' : ''; ?>">Usuarios</a>
                                <a href="<?php echo $base_path; ?>/admin/documents.php" class="nav-link px-3 py-2 text-sm font-medium <?php echo (strpos($current_path, '/admin/documents.php') !== false) ? 'active' : ''; ?>">Documentos</a>
                                <a href="<?php echo $base_path; ?>/admin/reports.php" class="nav-link px-3 py-2 text-sm font-medium <?php echo (strpos($current_path, '/admin/reports.php') !== false) ? 'active' : ''; ?>">Reportes</a>
                                <a href="<?php echo $base_path; ?>/admin/settings.php" class="nav-link px-3 py-2 text-sm font-medium <?php echo (strpos($current_path, '/admin/settings.php') !== false) ? 'active' : ''; ?>">Configuración</a>
                            <?php else: ?>
                                <a href="<?php echo $base_path; ?>/user/index.php" class="nav-link px-3 py-2 text-sm font-medium <?php echo (strpos($current_path, '/user/index.php') !== false) ? 'active' : ''; ?>">Dashboard</a>
                                <a href="<?php echo $base_path; ?>/user/documents.php" class="nav-link px-3 py-2 text-sm font-medium <?php echo (strpos($current_path, '/user/documents.php') !== false) ? 'active' : ''; ?>">Mis Documentos</a>
                                <a href="<?php echo $base_path; ?>/user/profile.php" class="nav-link px-3 py-2 text-sm font-medium <?php echo (strpos($current_path, '/user/profile.php') !== false) ? 'active' : ''; ?>">Mi Perfil</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Botón menú móvil -->
                    <div class="sm:hidden flex items-center">
                        <button id="mobile-menu-button" class="text-white p-2 rounded-md hover:bg-blue-600 focus:outline-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Información de usuario y cerrar sesión -->
                    <div class="hidden sm:flex items-center">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="flex items-center">
                                <div class="flex items-center space-x-1 mr-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-sm font-medium"><?php echo htmlspecialchars($user_name); ?></span>
                                </div>
                                <a href="<?php echo $base_path; ?>/auth/logout.php" class="bg-blue-800 hover:bg-blue-900 text-sm px-4 py-2 rounded-md transition-colors duration-200 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1V4a1 1 0 00-1-1H3zm9 4a1 1 0 10-2 0v4a1 1 0 102 0V7zm-3 1a1 1 0 10-2 0v4a1 1 0 102 0V8z" clip-rule="evenodd" />
                                    </svg>
                                    Cerrar Sesión
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Menú móvil -->
            <div id="mobile-menu" class="sm:hidden mobile-menu">
                <div class="px-2 pt-2 pb-3 space-y-1 border-t border-blue-400">
                    <?php if ($is_admin): ?>
                        <a href="<?php echo $base_path; ?>/admin/index.php" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-blue-600 <?php echo (strpos($current_path, '/admin/index.php') !== false) ? 'bg-blue-600' : ''; ?>">Dashboard</a>
                        <a href="<?php echo $base_path; ?>/admin/users.php" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-blue-600 <?php echo (strpos($current_path, '/admin/users.php') !== false) ? 'bg-blue-600' : ''; ?>">Usuarios</a>
                        <a href="<?php echo $base_path; ?>/admin/documents.php" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-blue-600 <?php echo (strpos($current_path, '/admin/documents.php') !== false) ? 'bg-blue-600' : ''; ?>">Documentos</a>
                        <a href="<?php echo $base_path; ?>/admin/reports.php" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-blue-600 <?php echo (strpos($current_path, '/admin/reports.php') !== false) ? 'bg-blue-600' : ''; ?>">Reportes</a>
                        <a href="<?php echo $base_path; ?>/admin/settings.php" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-blue-600 <?php echo (strpos($current_path, '/admin/settings.php') !== false) ? 'bg-blue-600' : ''; ?>">Configuración</a>
                    <?php else: ?>
                        <a href="<?php echo $base_path; ?>/user/index.php" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-blue-600 <?php echo (strpos($current_path, '/user/index.php') !== false) ? 'bg-blue-600' : ''; ?>">Dashboard</a>
                        <a href="<?php echo $base_path; ?>/user/documents.php" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-blue-600 <?php echo (strpos($current_path, '/user/documents.php') !== false) ? 'bg-blue-600' : ''; ?>">Mis Documentos</a>
                        <a href="<?php echo $base_path; ?>/user/profile.php" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-blue-600 <?php echo (strpos($current_path, '/user/profile.php') !== false) ? 'bg-blue-600' : ''; ?>">Mi Perfil</a>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="border-t border-blue-400 pt-3 pb-2">
                            <div class="flex items-center px-3">
                                <div class="flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <div class="text-base font-medium"><?php echo htmlspecialchars($user_name); ?></div>
                                </div>
                            </div>
                            <div class="mt-3 px-2">
                                <a href="<?php echo $base_path; ?>/auth/logout.php" class="block px-3 py-2 rounded-md text-base font-medium bg-blue-800 hover:bg-blue-900 text-center">Cerrar Sesión</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <!-- Contenido principal -->
        <div class="flex-grow">
            <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <!-- Aquí irá el contenido específico de cada página -->