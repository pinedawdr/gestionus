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

// Parámetros de filtrado
$type = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$month = isset($_GET['month']) ? sanitize($_GET['month']) : date('m');
$year = isset($_GET['year']) ? sanitize($_GET['year']) : date('Y');

// Obtener documentos del usuario con filtros
$params = [$userId];
$sql = "SELECT * FROM documents WHERE user_id = ?";

if (!empty($type)) {
    $sql .= " AND type = ?";
    $params[] = $type;
}

if (!empty($month) && !empty($year)) {
    $sql .= " AND MONTH(created_at) = ? AND YEAR(created_at) = ?";
    $params[] = $month;
    $params[] = $year;
}

$sql .= " ORDER BY created_at DESC";

$documents = $db->fetchAll($sql, $params);

// Preparar URLs seguras para los documentos
foreach ($documents as &$doc) {
    $doc['secure_url'] = getSecureFileUrl($doc['file_path']);
    $doc['file_type'] = getFileType($doc['original_name']);
    $doc['created_at_formatted'] = formatDate($doc['created_at']);
}

// Obtener años disponibles para filtrado
$sql = "SELECT DISTINCT YEAR(created_at) as year FROM documents WHERE user_id = ? ORDER BY year DESC";
$years = $db->fetchAll($sql, [$userId]);

// Función helper para formatear tamaño de archivo
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Documentos - GestiónUS</title>
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
                        </div>
                        <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                            <a href="index.php" class="border-transparent border-b-2 hover:border-gray-300 px-1 pt-1 text-sm font-medium">Dashboard</a>
                            <a href="documents.php" class="border-b-2 border-white px-1 pt-1 text-sm font-medium">Mis Documentos</a>
                            <a href="profile.php" class="border-transparent border-b-2 hover:border-gray-300 px-1 pt-1 text-sm font-medium">Mi Perfil</a>
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
                <!-- Filtros -->
                <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Filtrar Documentos</h3>
                        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label for="type" class="block text-sm font-medium text-gray-700">Tipo</label>
                                <select id="type" name="type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">Todos</option>
                                    <option value="backup" <?php echo $type === 'backup' ? 'selected' : ''; ?>>Backups</option>
                                    <option value="evidencia" <?php echo $type === 'evidencia' ? 'selected' : ''; ?>>Evidencias</option>
                                    <option value="reporte_cnv" <?php echo $type === 'reporte_cnv' ? 'selected' : ''; ?>>Reportes CNV</option>
                                    <option value="otro" <?php echo $type === 'otro' ? 'selected' : ''; ?>>Otros</option>
                                </select>
                            </div>
                            <div>
                                <label for="month" class="block text-sm font-medium text-gray-700">Mes</label>
                                <select id="month" name="month" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">Todos</option>
                                    <option value="01" <?php echo $month === '01' ? 'selected' : ''; ?>>Enero</option>
                                    <option value="02" <?php echo $month === '02' ? 'selected' : ''; ?>>Febrero</option>
                                    <option value="03" <?php echo $month === '03' ? 'selected' : ''; ?>>Marzo</option>
                                    <option value="04" <?php echo $month === '04' ? 'selected' : ''; ?>>Abril</option>
                                    <option value="05" <?php echo $month === '05' ? 'selected' : ''; ?>>Mayo</option>
                                    <option value="06" <?php echo $month === '06' ? 'selected' : ''; ?>>Junio</option>
                                    <option value="07" <?php echo $month === '07' ? 'selected' : ''; ?>>Julio</option>
                                    <option value="08" <?php echo $month === '08' ? 'selected' : ''; ?>>Agosto</option>
                                    <option value="09" <?php echo $month === '09' ? 'selected' : ''; ?>>Septiembre</option>
                                    <option value="10" <?php echo $month === '10' ? 'selected' : ''; ?>>Octubre</option>
                                    <option value="11" <?php echo $month === '11' ? 'selected' : ''; ?>>Noviembre</option>
                                    <option value="12" <?php echo $month === '12' ? 'selected' : ''; ?>>Diciembre</option>
                                </select>
                            </div>
                            <div>
                                <label for="year" class="block text-sm font-medium text-gray-700">Año</label>
                                <select id="year" name="year" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">Todos</option>
                                    <?php foreach ($years as $yearOption): ?>
                                    <option value="<?php echo $yearOption['year']; ?>" <?php echo $year == $yearOption['year'] ? 'selected' : ''; ?>>
                                        <?php echo $yearOption['year']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                    <?php if (empty($years)): ?>
                                    <option value="<?php echo date('Y'); ?>" selected><?php echo date('Y'); ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Filtrar
                                </button>
                                <a href="documents.php" class="ml-3 inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Limpiar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de documentos -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Mis Documentos</h3>
                        <?php if (!empty($documents)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tamaño</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($doc['original_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($doc['description']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php 
                                                switch ($doc['type']) {
                                                    case 'backup':
                                                        echo 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'evidencia':
                                                        echo 'bg-blue-100 text-blue-800';
                                                        break;
                                                    case 'reporte_cnv':
                                                        echo 'bg-purple-100 text-purple-800';
                                                        break;
                                                    default:
                                                        echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <?php 
                                                switch ($doc['type']) {
                                                    case 'backup':
                                                        echo 'Backup';
                                                        break;
                                                    case 'evidencia':
                                                        echo 'Evidencia';
                                                        break;
                                                    case 'reporte_cnv':
                                                        echo 'Reporte CNV';
                                                        break;
                                                    default:
                                                        echo 'Otro';
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo formatFileSize($doc['file_size']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $doc['created_at_formatted']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="<?php echo $doc['secure_url']; ?>" target="_blank" class="text-blue-600 hover:text-blue-900 mr-3">Ver</a>
                                            <a href="<?php echo $doc['secure_url']; ?>&download=1" class="text-green-600 hover:text-green-900">Descargar</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay documentos</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                No se encontraron documentos con los filtros aplicados.
                            </p>
                            <div class="mt-6">
                                <a href="index.php#uploadForm" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                    </svg>
                                    Subir Documento
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
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
</body>
</html>