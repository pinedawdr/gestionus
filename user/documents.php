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

// Set page title for the header
$pageTitle = "Mis Documentos";
$userRole = "user";
?>

<?php include_once '../includes/header.php'; ?>

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
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center bg-gray-100 rounded-md">
                                            <?php if ($doc['file_type'] === 'pdf'): ?>
                                            <svg class="h-6 w-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                            </svg>
                                            <?php elseif (in_array($doc['file_type'], ['doc', 'docx'])): ?>
                                            <svg class="h-6 w-6 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                            </svg>
                                            <?php elseif (in_array($doc['file_type'], ['xls', 'xlsx'])): ?>
                                            <svg class="h-6 w-6 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                            </svg>
                                            <?php else: ?>
                                            <svg class="h-6 w-6 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                            </svg>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 truncate max-w-xs"><?php echo htmlspecialchars($doc['original_name']); ?></div>
                                            <div class="text-xs text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($doc['description'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        switch ($doc['type']) {
                                            case 'backup':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'evidencia':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'reporte_cnv':
                                                echo 'bg-yellow-100 text-yellow-800';
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
                                    <a href="<?php echo $doc['secure_url']; ?>" download class="text-green-600 hover:text-green-900">Descargar</a>
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
                        No se encontraron documentos con los filtros seleccionados.
                    </p>
                    <div class="mt-6">
                        <a href="index.php#uploadForm" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Subir documento
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>