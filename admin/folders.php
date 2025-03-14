<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar autenticación y rol de administrador
requireAdmin();

// Obtener información del usuario administrador
$userId = $_SESSION['user_id'];
$db = Database::getInstance();

$sql = "SELECT * FROM users WHERE id = ? LIMIT 1";
$user = $db->fetchOne($sql, [$userId]);

if (!$user) {
    // Si el usuario no existe, cerrar sesión
    header("Location: ../auth/logout.php");
    exit;
}

// Definir variables para el header
$page_title = "Explorador de Archivos";
$_SESSION['name'] = $user['name'];

// Obtener parámetros de navegación
$currentUser = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$currentYear = isset($_GET['year']) ? sanitize($_GET['year']) : '';
$currentMonth = isset($_GET['month']) ? sanitize($_GET['month']) : '';
$currentDay = isset($_GET['day']) ? sanitize($_GET['day']) : '';
$searchTerm = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$fileType = isset($_GET['file_type']) ? sanitize($_GET['file_type']) : '';
$documentType = isset($_GET['doc_type']) ? sanitize($_GET['doc_type']) : '';
$sortBy = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'date_desc';

// Obtener lista de usuarios (solo usuarios normales, no administradores) para el filtro
$sql = "SELECT id, name FROM users WHERE active = 1 AND role = 'user' ORDER BY name";
$users = $db->fetchAll($sql);

// Nombres de los meses
$monthNames = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

// Incluir el header global
include_once '../includes/header.php';
?>

<h1 class="text-2xl font-semibold text-gray-900 mb-6">Explorador de Archivos</h1>

<!-- Filtros de búsqueda -->
<div class="bg-white shadow rounded-lg mb-6">
    <div class="px-4 py-5 sm:p-6">
        <form action="" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div>
                    <label for="user" class="block text-sm font-medium text-gray-700">Usuario</label>
                    <select name="user" id="user" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <option value="">Seleccionar Usuario</option>
                        <?php foreach ($users as $userOption): ?>
                        <option value="<?php echo $userOption['id']; ?>" <?php echo $currentUser == $userOption['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($userOption['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="file_type" class="block text-sm font-medium text-gray-700">Tipo de Archivo</label>
                    <select name="file_type" id="file_type" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <option value="">Todos los tipos</option>
                        <option value="document" <?php echo $fileType === 'document' ? 'selected' : ''; ?>>Documentos</option>
                        <option value="image" <?php echo $fileType === 'image' ? 'selected' : ''; ?>>Imágenes</option>
                        <option value="compressed" <?php echo $fileType === 'compressed' ? 'selected' : ''; ?>>Comprimidos</option>
                        <option value="other" <?php echo $fileType === 'other' ? 'selected' : ''; ?>>Otros</option>
                    </select>
                </div>
                <div>
                    <label for="doc_type" class="block text-sm font-medium text-gray-700">Categoría</label>
                    <select name="doc_type" id="doc_type" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <option value="">Todas las categorías</option>
                        <option value="backup" <?php echo $documentType === 'backup' ? 'selected' : ''; ?>>Backup</option>
                        <option value="evidencia" <?php echo $documentType === 'evidencia' ? 'selected' : ''; ?>>Evidencia</option>
                        <option value="reporte_cnv" <?php echo $documentType === 'reporte_cnv' ? 'selected' : ''; ?>>Reporte CNV</option>
                        <option value="otro" <?php echo $documentType === 'otro' ? 'selected' : ''; ?>>Otro</option>
                    </select>
                </div>
                <div>
                    <label for="sort" class="block text-sm font-medium text-gray-700">Ordenar por</label>
                    <select name="sort" id="sort" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <option value="date_desc" <?php echo $sortBy === 'date_desc' ? 'selected' : ''; ?>>Fecha (más reciente)</option>
                        <option value="date_asc" <?php echo $sortBy === 'date_asc' ? 'selected' : ''; ?>>Fecha (más antigua)</option>
                        <option value="name_asc" <?php echo $sortBy === 'name_asc' ? 'selected' : ''; ?>>Nombre (A-Z)</option>
                        <option value="name_desc" <?php echo $sortBy === 'name_desc' ? 'selected' : ''; ?>>Nombre (Z-A)</option>
                        <option value="size_desc" <?php echo $sortBy === 'size_desc' ? 'selected' : ''; ?>>Tamaño (mayor)</option>
                        <option value="size_asc" <?php echo $sortBy === 'size_asc' ? 'selected' : ''; ?>>Tamaño (menor)</option>
                    </select>
                </div>
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700">Buscar Archivo</label>
                    <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($searchTerm); ?>" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" placeholder="Nombre del archivo...">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Buscar
                    </button>
                    <a href="folders.php" class="ml-2 inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Navegación de carpetas -->
<div class="bg-white shadow rounded-lg mb-6">
    <div class="px-4 py-5 sm:p-6">
        <div class="flex items-center space-x-2 text-sm text-gray-500 mb-4">
            <a href="folders.php" class="hover:text-blue-600">Inicio</a>
            <?php if ($currentUser): ?>
                <?php 
                $userInfo = $db->fetchOne("SELECT name FROM users WHERE id = ?", [$currentUser]);
                ?>
                <span>/</span>
                <a href="folders.php?user=<?php echo $currentUser; ?>" class="hover:text-blue-600">
                    <?php echo htmlspecialchars($userInfo['name']); ?>
                </a>
                
                <?php if ($currentYear): ?>
                    <span>/</span>
                    <a href="folders.php?user=<?php echo $currentUser; ?>&year=<?php echo $currentYear; ?>" class="hover:text-blue-600">
                        <?php echo $currentYear; ?>
                    </a>
                    
                    <?php if ($currentMonth): ?>
                        <span>/</span>
                        <a href="folders.php?user=<?php echo $currentUser; ?>&year=<?php echo $currentYear; ?>&month=<?php echo $currentMonth; ?>" class="hover:text-blue-600">
                            <?php echo $monthNames[$currentMonth] ?? $currentMonth; ?>
                        </a>
                        
                        <?php if ($currentDay): ?>
                            <span>/</span>
                            <a href="folders.php?user=<?php echo $currentUser; ?>&year=<?php echo $currentYear; ?>&month=<?php echo $currentMonth; ?>&day=<?php echo $currentDay; ?>" class="hover:text-blue-600">
                                <?php echo $currentDay; ?>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Operaciones por lotes -->
        <div id="batch-operations" class="hidden mb-4">
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-700"><span id="selected-count">0</span> archivos seleccionados</span>
                <button type="button" id="download-selected" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Descargar
                </button>
                <button type="button" id="share-selected" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <svg class="h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                    </svg>
                    Compartir
                </button>
                <button type="button" id="select-all" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Seleccionar todos
                </button>
                <button type="button" id="unselect-all" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Deseleccionar
                </button>
            </div>
        </div>

        <?php if (!empty($searchTerm)): ?>
            <!-- Resultados de búsqueda -->
            <?php
            $searchParam = "%{$searchTerm}%";
            $sql = "SELECT d.*, u.name as user_name 
                    FROM documents d 
                    JOIN users u ON d.user_id = u.id 
                    WHERE d.original_name LIKE ? OR d.description LIKE ?";
                    
            $params = [$searchParam, $searchParam];
            
            // Filtrar por usuario si está seleccionado
            if ($currentUser) {
                $sql .= " AND d.user_id = ?";
                $params[] = $currentUser;
            }
            
            // Filtrar por tipo de archivo si está seleccionado
            if ($fileType) {
                $sql .= " AND d.type = ?";
                $params[] = $fileType;
            }
            
            // Filtrar por categoría si está seleccionada
            if ($documentType) {
                $sql .= " AND d.category = ?";
                $params[] = $documentType;
            }
            
            // Ordenar resultados según la opción seleccionada
            switch ($sortBy) {
                case 'date_desc':
                    $sql .= " ORDER BY d.created_at DESC";
                    break;
                case 'date_asc':
                    $sql .= " ORDER BY d.created_at ASC";
                    break;
                case 'name_asc':
                    $sql .= " ORDER BY d.original_name ASC";
                    break;
                case 'name_desc':
                    $sql .= " ORDER BY d.original_name DESC";
                    break;
                case 'size_desc':
                    $sql .= " ORDER BY d.file_size DESC";
                    break;
                case 'size_asc':
                    $sql .= " ORDER BY d.file_size ASC";
                    break;
            }
            
            $searchResults = $db->fetchAll($sql, $params);
            
            // Preparar URLs seguras para los documentos
            foreach ($searchResults as &$doc) {
                $doc['secure_url'] = getSecureFileUrl($doc['file_path']);
                $doc['file_type'] = getFileType($doc['original_name']);
                $doc['created_at_formatted'] = formatDate($doc['created_at']);
            }
            ?>
            
            <h3 class="text-lg font-medium text-gray-900 mb-4">Resultados de Búsqueda: "<?php echo htmlspecialchars($searchTerm); ?>"</h3>
            
            <?php if (!empty($searchResults)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($searchResults as $doc): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center bg-gray-100 rounded-md">
                                            <?php if (strpos($doc['original_name'], '.pdf') !== false): ?>
                                            <svg class="h-6 w-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                            </svg>
                                            <?php elseif (strpos($doc['original_name'], '.doc') !== false || strpos($doc['original_name'], '.docx') !== false): ?>
                                            <svg class="h-6 w-6 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                            </svg>
                                            <?php elseif (strpos($doc['original_name'], '.xls') !== false || strpos($doc['original_name'], '.xlsx') !== false): ?>
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
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($doc['original_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($doc['description'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($doc['user_name']); ?></div>
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
                                    <?php echo $doc['created_at_formatted']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="<?php echo $doc['secure_url']; ?>" target="_blank" class="text-blue-600 hover:text-blue-900" title="Descargar">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                        </a>
                                        <?php if ($doc['file_type'] === 'image'): ?>
                                        <button type="button" class="text-indigo-600 hover:text-indigo-900 preview-image-btn" 
                                                data-url="<?php echo $doc['secure_url']; ?>" 
                                                data-filename="<?php echo htmlspecialchars($doc['original_name']); ?>"
                                                title="Vista previa">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <?php elseif (strpos($doc['original_name'], '.pdf') !== false): ?>
                                        <button type="button" class="text-red-600 hover:text-red-900 preview-pdf-btn" 
                                                data-url="<?php echo $doc['secure_url']; ?>" 
                                                data-filename="<?php echo htmlspecialchars($doc['original_name']); ?>"
                                                title="Vista previa PDF">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <?php endif; ?>
                                        <input type="checkbox" name="selected_files[]" value="<?php echo $doc['id']; ?>" class="file-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1">
                                    </div>
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
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No se encontraron resultados</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        No hay archivos que coincidan con la búsqueda.
                    </p>
                </div>
            <?php endif; ?>
        <?php elseif (!$currentUser): ?>
            <!-- Mostrar lista de usuarios -->
            <h3 class="text-lg font-medium text-gray-900 mb-4">Usuarios</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($users as $userOption): ?>
                    <a href="folders.php?user=<?php echo $userOption['id']; ?>" class="flex items-center p-3 border rounded-lg hover:bg-blue-50">
                        <svg class="h-6 w-6 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 012-2h14a2 2 0 012 2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($userOption['name']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$currentYear): ?>
            <!-- Mostrar años disponibles para el usuario seleccionado -->
            <?php
            $sql = "SELECT DISTINCT YEAR(created_at) as year FROM documents WHERE user_id = ? ORDER BY year DESC";
            $years = $db->fetchAll($sql, [$currentUser]);
            ?>
            <h3 class="text-lg font-medium text-gray-900 mb-4">Años</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($years as $yearData): ?>
                    <a href="folders.php?user=<?php echo $currentUser; ?>&year=<?php echo $yearData['year']; ?>" class="flex items-center p-3 border rounded-lg hover:bg-blue-50">
                        <svg class="h-6 w-6 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 012-2h14a2 2 0 012 2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-900"><?php echo $yearData['year']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$currentMonth): ?>
            <!-- Mostrar meses disponibles para el año seleccionado -->
            <?php
            $sql = "SELECT DISTINCT MONTH(created_at) as month FROM documents WHERE user_id = ? AND YEAR(created_at) = ? ORDER BY month";
            $months = $db->fetchAll($sql, [$currentUser, $currentYear]);
            ?>
            <h3 class="text-lg font-medium text-gray-900 mb-4">Meses de <?php echo $currentYear; ?></h3>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($months as $monthData): ?>
                    <?php 
                    $monthNum = str_pad($monthData['month'], 2, '0', STR_PAD_LEFT);
                    ?>
                    <a href="folders.php?user=<?php echo $currentUser; ?>&year=<?php echo $currentYear; ?>&month=<?php echo $monthNum; ?>" class="flex items-center p-3 border rounded-lg hover:bg-blue-50">
                        <svg class="h-6 w-6 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 012-2h14a2 2 0 012 2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-900"><?php echo $monthNames[$monthNum]; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$currentDay): ?>
            <!-- Mostrar días disponibles para el mes seleccionado -->
            <?php
            $sql = "SELECT DISTINCT DAY(created_at) as day FROM documents WHERE user_id = ? AND YEAR(created_at) = ? AND MONTH(created_at) = ? ORDER BY day";
            $days = $db->fetchAll($sql, [$currentUser, $currentYear, $currentMonth]);
            ?>
            <h3 class="text-lg font-medium text-gray-900 mb-4">Días de <?php echo $monthNames[$currentMonth] . ' ' . $currentYear; ?></h3>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <?php foreach ($days as $dayData): ?>
                    <?php $dayNum = str_pad($dayData['day'], 2, '0', STR_PAD_LEFT); ?>
                    <a href="folders.php?user=<?php echo $currentUser; ?>&year=<?php echo $currentYear; ?>&month=<?php echo $currentMonth; ?>&day=<?php echo $dayNum; ?>" class="flex items-center p-3 border rounded-lg hover:bg-blue-50">
                        <svg class="h-6 w-6 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 012-2h14a2 2 0 012 2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-900"><?php echo $dayNum; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Mostrar archivos del día seleccionado -->
            <?php
            $date = "$currentYear-$currentMonth-$currentDay";
            $sql = "SELECT d.*, u.name as user_name 
                    FROM documents d 
                    JOIN users u ON d.user_id = u.id 
                    WHERE d.user_id = ? AND DATE(d.created_at) = ?
                    ORDER BY d.created_at DESC";
            $documents = $db->fetchAll($sql, [$currentUser, $date]);
            
            // Preparar URLs seguras para los documentos
            foreach ($documents as &$doc) {
                $doc['secure_url'] = getSecureFileUrl($doc['file_path']);
                $doc['file_type'] = getFileType($doc['original_name']);
                $doc['created_at_formatted'] = formatDate($doc['created_at']);
            }
            ?>
            
            <h3 class="text-lg font-medium text-gray-900 mb-4">Archivos del <?php echo "$currentDay de {$monthNames[$currentMonth]} de $currentYear"; ?></h3>
            
            <?php if (!empty($documents)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tamaño</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hora</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center bg-gray-100 rounded-md">
                                            <?php if (strpos($doc['original_name'], '.pdf') !== false): ?>
                                            <svg class="h-6 w-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                            </svg>
                                            <?php elseif (strpos($doc['original_name'], '.doc') !== false || strpos($doc['original_name'], '.docx') !== false): ?>
                                            <svg class="h-6 w-6 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                            </svg>
                                            <?php elseif (strpos($doc['original_name'], '.xls') !== false || strpos($doc['original_name'], '.xlsx') !== false): ?>
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
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($doc['original_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($doc['description'] ?? ''); ?></div>
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
                                    <?php echo date('H:i:s', strtotime($doc['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="<?php echo $doc['secure_url']; ?>" target="_blank" class="text-blue-600 hover:text-blue-900" title="Descargar">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                        </a>
                                        <?php if ($doc['file_type'] === 'image'): ?>
                                        <button type="button" class="text-indigo-600 hover:text-indigo-900 preview-image-btn" 
                                                data-url="<?php echo $doc['secure_url']; ?>" 
                                                data-filename="<?php echo htmlspecialchars($doc['original_name']); ?>"
                                                title="Vista previa">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <?php elseif (strpos($doc['original_name'], '.pdf') !== false): ?>
                                        <button type="button" class="text-red-600 hover:text-red-900 preview-pdf-btn" 
                                                data-url="<?php echo $doc['secure_url']; ?>" 
                                                data-filename="<?php echo htmlspecialchars($doc['original_name']); ?>"
                                                title="Vista previa PDF">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <?php endif; ?>
                                        <input type="checkbox" name="selected_files[]" value="<?php echo $doc['id']; ?>" class="file-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1">
                                    </div>
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
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No hay archivos</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        No se encontraron archivos en este día.
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Estadísticas -->
<div class="bg-white shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Estadísticas de Archivos</h3>
        
        <?php if ($currentUser): ?>
            <?php
            // Obtener estadísticas del usuario
            $stats = [
                'total_documents' => 0,
                'backup' => 0,
                'evidencia' => 0,
                'reporte_cnv' => 0,
                'otro' => 0
            ];
            
            // Preparar la consulta base
            $sqlBase = "SELECT COUNT(*) as count FROM documents WHERE user_id = ?";
            $paramsBase = [$currentUser];
            
            // Aplicar filtros adicionales si es necesario
            if ($currentYear) {
                $sqlBase .= " AND YEAR(created_at) = ?";
                $paramsBase[] = $currentYear;
                
                if ($currentMonth) {
                    $sqlBase .= " AND MONTH(created_at) = ?";
                    $paramsBase[] = $currentMonth;
                    
                    if ($currentDay) {
                        $sqlBase .= " AND DAY(created_at) = ?";
                        $paramsBase[] = $currentDay;
                    }
                }
            }
            
            // Total de documentos
            $result = $db->fetchOne($sqlBase, $paramsBase);
            $stats['total_documents'] = $result['count'];
            
            // Por tipo
            $types = ['backup', 'evidencia', 'reporte_cnv', 'otro'];
            foreach ($types as $type) {
                $sqlType = $sqlBase . " AND type = ?";
                $paramsType = $paramsBase;
                $paramsType[] = $type;
                
                $result = $db->fetchOne($sqlType, $paramsType);
                $stats[$type] = $result['count'];
            }
            ?>
            
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-gray-50 p-4 rounded-md">
                    <div class="text-sm font-medium text-gray-500">Total Documentos</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900"><?php echo $stats['total_documents']; ?></div>
                </div>
                <div class="bg-blue-50 p-4 rounded-md">
                    <div class="text-sm font-medium text-blue-700">Backups</div>
                    <div class="mt-1 text-3xl font-semibold text-blue-900"><?php echo $stats['backup']; ?></div>
                </div>
                <div class="bg-green-50 p-4 rounded-md">
                    <div class="text-sm font-medium text-green-700">Evidencias</div>
                    <div class="mt-1 text-3xl font-semibold text-green-900"><?php echo $stats['evidencia']; ?></div>
                </div>
                <div class="bg-purple-50 p-4 rounded-md">
                    <div class="text-sm font-medium text-purple-700">Reportes CNV</div>
                    <div class="mt-1 text-3xl font-semibold text-purple-900"><?php echo $stats['reporte_cnv']; ?></div>
                </div>
                <div class="bg-gray-50 p-4 rounded-md">
                    <div class="text-sm font-medium text-gray-700">Otros</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900"><?php echo $stats['otro']; ?></div>
                </div>
            </div>
        <?php else: ?>
            <?php
            // Estadísticas globales
            $sqlTotal = "SELECT COUNT(*) as count FROM documents";
            $resultTotal = $db->fetchOne($sqlTotal);
            $totalDocs = $resultTotal['count'];
            
            // Por tipo
            $sqlTypes = "SELECT type, COUNT(*) as count FROM documents GROUP BY type";
            $resultTypes = $db->fetchAll($sqlTypes);
            
            $typeStats = [
                'backup' => 0,
                'evidencia' => 0,
                'reporte_cnv' => 0,
                'otro' => 0
            ];
            
            foreach ($resultTypes as $typeStat) {
                $typeStats[$typeStat['type']] = $typeStat['count'];
            }
            
            // Por usuario (top 5)
            $sqlTopUsers = "SELECT u.name, COUNT(d.id) as doc_count 
                          FROM documents d 
                          JOIN users u ON d.user_id = u.id 
                          WHERE u.role = 'user'
                          GROUP BY d.user_id 
                          ORDER BY doc_count DESC 
                          LIMIT 5";
            $topUsers = $db->fetchAll($sqlTopUsers);
            ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-base font-medium text-gray-800 mb-3">Documentos por Tipo</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-gray-50 p-4 rounded-md">
                            <div class="text-sm font-medium text-gray-500">Total</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900"><?php echo $totalDocs; ?></div>
                        </div>
                        <div class="bg-blue-50 p-4 rounded-md">
                            <div class="text-sm font-medium text-blue-700">Backups</div>
                            <div class="mt-1 text-3xl font-semibold text-blue-900"><?php echo $typeStats['backup']; ?></div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-md">
                            <div class="text-sm font-medium text-green-700">Evidencias</div>
                            <div class="mt-1 text-3xl font-semibold text-green-900"><?php echo $typeStats['evidencia']; ?></div>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-md">
                            <div class="text-sm font-medium text-purple-700">Reportes CNV</div>
                            <div class="mt-1 text-3xl font-semibold text-purple-900"><?php echo $typeStats['reporte_cnv']; ?></div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h4 class="text-base font-medium text-gray-800 mb-3">Usuarios con más Documentos</h4>
                    <ul class="divide-y divide-gray-200">
                        <?php foreach ($topUsers as $index => $topUser): ?>
                            <li class="py-2 flex justify-between">
                                <span class="text-sm text-gray-800">
                                    <?php echo ($index + 1) . '. ' . htmlspecialchars($topUser['name']); ?>
                                </span>
                                <span class="text-sm font-medium text-gray-900"><?php echo $topUser['doc_count']; ?> documentos</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de vista previa de imagen -->
<div id="image-preview-modal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-4xl sm:w-full">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="image-preview-title"></h3>
                    <div class="mt-4 max-h-96 overflow-auto flex justify-center">
                        <img id="image-preview" src="" alt="Vista previa" class="max-w-full h-auto">
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button type="button" class="close-modal mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                Cerrar
            </button>
            <a id="image-download-link" href="#" class="mt-3 w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                Descargar
            </a>
        </div>
    </div>
</div>

<!-- Modal de vista previa de PDF -->
<div id="pdf-preview-modal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-5xl sm:w-full h-5/6">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 h-full flex flex-col">
            <div class="sm:flex sm:items-start">
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="pdf-preview-title"></h3>
                    <div class="mt-4 h-full">
                        <iframe id="pdf-preview" src="" width="100%" height="100%" style="min-height: 70vh;"></iframe>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button type="button" class="close-modal mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                Cerrar
            </button>
            <a id="pdf-download-link" href="#" class="mt-3 w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                Descargar
            </a>
        </div>
    </div>
</div>

<!-- Modal para compartir archivos -->
<div id="share-modal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Compartir archivos</h3>
                    <div class="mt-4">
                        <div class="mb-4">
                            <label for="share-email" class="block text-sm font-medium text-gray-700">Correo electrónico</label>
                            <input type="email" id="share-email" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" placeholder="ejemplo@correo.com">
                        </div>
                        <div class="mb-4">
                            <label for="share-message" class="block text-sm font-medium text-gray-700">Mensaje (opcional)</label>
                            <textarea id="share-message" rows="3" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
                        </div>
                        <div class="mb-4">
                            <label for="share-expiry" class="block text-sm font-medium text-gray-700">Enlace válido por</label>
                            <select id="share-expiry" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="1">1 día</option>
                                <option value="7" selected>7 días</option>
                                <option value="30">30 días</option>
                                <option value="90">90 días</option>
                            </select>
                        </div>
                        <div id="share-files-list" class="mt-4 max-h-40 overflow-y-auto">
                            <!-- Lista de archivos seleccionados -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button type="button" id="share-submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                Compartir
            </button>
            <button type="button" class="close-modal mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                Cancelar
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Funcionalidad para vista previa de imágenes
    const previewImageBtns = document.querySelectorAll('.preview-image-btn');
    const imagePreviewModal = document.getElementById('image-preview-modal');
    const imagePreview = document.getElementById('image-preview');
    const imagePreviewTitle = document.getElementById('image-preview-title');
    const imageDownloadLink = document.getElementById('image-download-link');
    
    previewImageBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            const filename = this.getAttribute('data-filename');
            
            imagePreview.src = url;
            imagePreviewTitle.textContent = filename;
            imageDownloadLink.href = url + '&download=1';
            
            imagePreviewModal.classList.remove('hidden');
        });
    });
    
    // Funcionalidad para vista previa de PDFs
    const previewPdfBtns = document.querySelectorAll('.preview-pdf-btn');
    const pdfPreviewModal = document.getElementById('pdf-preview-modal');
    const pdfPreview = document.getElementById('pdf-preview');
    const pdfPreviewTitle = document.getElementById('pdf-preview-title');
    const pdfDownloadLink = document.getElementById('pdf-download-link');
    
    previewPdfBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            const filename = this.getAttribute('data-filename');
            
            pdfPreview.src = url;
            pdfPreviewTitle.textContent = filename;
            pdfDownloadLink.href = url + '&download=1';
            
            pdfPreviewModal.classList.remove('hidden');
        });
    });
    
    // Cerrar modales
    const closeModalBtns = document.querySelectorAll('.close-modal');
    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            imagePreviewModal.classList.add('hidden');
            pdfPreviewModal.classList.add('hidden');
            document.getElementById('share-modal').classList.add('hidden');
        });
    });
    
    // Funcionalidad para operaciones por lotes
    const fileCheckboxes = document.querySelectorAll('.file-checkbox');
    const batchOperations = document.getElementById('batch-operations');
    const selectedCount = document.getElementById('selected-count');
    const selectAllBtn = document.getElementById('select-all');
    const unselectAllBtn = document.getElementById('unselect-all');
    const downloadSelectedBtn = document.getElementById('download-selected');
    const shareSelectedBtn = document.getElementById('share-selected');
    const shareModal = document.getElementById('share-modal');
    const shareFilesList = document.getElementById('share-files-list');
    const shareSubmitBtn = document.getElementById('share-submit');
    
    // Función para actualizar el contador de archivos seleccionados
    function updateSelectedCount() {
        const count = document.querySelectorAll('.file-checkbox:checked').length;
        selectedCount.textContent = count;
        
        if (count > 0) {
            batchOperations.classList.remove('hidden');
        } else {
            batchOperations.classList.add('hidden');
        }
    }
    
    // Escuchar cambios en los checkboxes
    fileCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    
    // Seleccionar todos los archivos
    selectAllBtn.addEventListener('click', function() {
        fileCheckboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        updateSelectedCount();
    });
    
    // Deseleccionar todos los archivos
    unselectAllBtn.addEventListener('click', function() {
        fileCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelectedCount();
    });
    
    // Descargar archivos seleccionados
    downloadSelectedBtn.addEventListener('click', function() {
        const selectedFiles = Array.from(document.querySelectorAll('.file-checkbox:checked')).map(checkbox => checkbox.value);
        
        if (selectedFiles.length === 0) {
            alert('Por favor seleccione al menos un archivo para descargar.');
            return;
        }
        
        // Crear un formulario temporal para enviar la solicitud de descarga
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'api/documents.php?action=download_batch';
        form.style.display = 'none';
        
        selectedFiles.forEach(fileId => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'file_ids[]';
            input.value = fileId;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });
    
    // Compartir archivos seleccionados
    shareSelectedBtn.addEventListener('click', function() {
        const selectedFiles = Array.from(document.querySelectorAll('.file-checkbox:checked'));
        
        if (selectedFiles.length === 0) {
            alert('Por favor seleccione al menos un archivo para compartir.');
            return;
        }
        
        // Limpiar la lista de archivos
        shareFilesList.innerHTML = '';
        
        // Agregar los archivos seleccionados a la lista
        selectedFiles.forEach(checkbox => {
            const fileId = checkbox.value;
            const fileRow = checkbox.closest('tr');
            const fileName = fileRow.querySelector('td:nth-child(2)').textContent.trim();
            
            const fileItem = document.createElement('div');
            fileItem.className = 'py-2 border-b border-gray-200 last:border-b-0';
            fileItem.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-700">${fileName}</span>
                    <input type="hidden" name="share_file_ids[]" value="${fileId}">
                </div>
            `;
            
            shareFilesList.appendChild(fileItem);
        });
        
        // Mostrar el modal de compartir
        shareModal.classList.remove('hidden');
    });
    
    // Enviar solicitud de compartir
    shareSubmitBtn.addEventListener('click', function() {
        const email = document.getElementById('share-email').value;
        const message = document.getElementById('share-message').value;
        const expiry = document.getElementById('share-expiry').value;
        const fileIds = Array.from(document.querySelectorAll('input[name="share_file_ids[]"]')).map(input => input.value);
        
        if (!email) {
            alert('Por favor ingrese un correo electrónico válido.');
            return;
        }
        
        // Crear un objeto con los datos para compartir
        const shareData = {
            email: email,
            message: message,
            expiry: expiry,
            file_ids: fileIds
        };
        
        // Enviar la solicitud al servidor
        fetch('api/documents.php?action=share', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(shareData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Archivos compartidos exitosamente.');
                shareModal.classList.add('hidden');
            } else {
                alert('Error al compartir archivos: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al compartir archivos. Por favor intente nuevamente.');
        });
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>