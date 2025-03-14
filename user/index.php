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

// Verificar documentos pendientes
$pendingUploads = checkPendingUploads($userId);
// Obtener estadísticas del usuario
$stats = [
    'total_documents' => 0,
    'backups' => 0,
    'evidencias' => 0,
    'reportes_cnv' => 0,
    'otros' => 0,
    'last_upload' => null
];

// Contar documentos por tipo
$sql = "SELECT type, COUNT(*) as count FROM documents WHERE user_id = ? GROUP BY type";
$documentStats = $db->fetchAll($sql, [$userId]);

foreach ($documentStats as $stat) {
    $stats['total_documents'] += $stat['count'];
    
    switch ($stat['type']) {
        case 'backup':
            $stats['backups'] = $stat['count'];
            break;
        case 'evidencia':
            $stats['evidencias'] = $stat['count'];
            break;
        case 'reporte_cnv':
            $stats['reportes_cnv'] = $stat['count'];
            break;
        case 'otro':
            $stats['otros'] = $stat['count'];
            break;
    }
}

// Obtener última subida
$sql = "SELECT created_at FROM documents WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
$lastUpload = $db->fetchOne($sql, [$userId]);

if ($lastUpload) {
    $stats['last_upload'] = formatDate($lastUpload['created_at']);
}

// Obtener últimos documentos subidos
$sql = "SELECT * FROM documents WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$recentDocuments = $db->fetchAll($sql, [$userId]);

// Preparar URLs seguras para los documentos
foreach ($recentDocuments as &$doc) {
    $doc['secure_url'] = getSecureFileUrl($doc['file_path']);
    $doc['file_type'] = getFileType($doc['original_name']);
    $doc['created_at_formatted'] = formatDate($doc['created_at']);
}

// Verificar restricciones horarias para cada tipo de documento
$dayOfWeek = date('N'); // 1 (lunes) a 7 (domingo)
$currentHour = (int)date('G'); // 0-23 formato 24h

// Inicializar variables de estado para cada tipo
$canUploadCNV = false;
$canUploadBackup = false;
$canUploadEvidencia = false;

// CNV: Solo martes de 00:00 hasta 12:00
if ($dayOfWeek == 2 && $currentHour < 12) {
    $canUploadCNV = true;
}

// Backups y Evidencias: Solo viernes de 00:00 hasta 18:00
if ($dayOfWeek == 5 && $currentHour < 18) {
    $canUploadBackup = true;
    $canUploadEvidencia = true;
}

// Set page title for the header
$pageTitle = "Dashboard de Usuario";
$userRole = "user";

// Mensaje de error para el formulario de subida
$uploadError = '';
$uploadSuccess = '';

// Procesar si hay un mensaje de éxito o error en la URL
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $uploadSuccess = 'Documento subido correctamente';
}
if (isset($_GET['error'])) {
    $uploadError = urldecode($_GET['error']);
}
?>

<?php include_once '../includes/header.php'; ?>

<!-- Contenido principal -->
<div class="flex-grow">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Notificaciones -->
        <?php if (!empty($pendingUploads)): ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        <strong>Recordatorio:</strong> Tienes documentos pendientes por subir:
                        <ul class="list-disc pl-5 mt-1">
                            <?php foreach ($pendingUploads as $type): ?>
                            <li>
                                <?php 
                                switch ($type) {
                                    case 'backup':
                                        echo 'Backup de seguridad (viernes hasta las 18:00)';
                                        break;
                                    case 'evidencia':
                                        echo 'Evidencias de envío (viernes hasta las 18:00)';
                                        break;
                                    case 'reporte_cnv':
                                        echo 'Reporte CNV (martes hasta las 12:00)';
                                        break;
                                }
                                ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Mensajes de éxito o error -->
        <?php if (!empty($uploadSuccess)): ?>
        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700"><?php echo $uploadSuccess; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($uploadError)): ?>
        <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700"><?php echo $uploadError; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tarjetas de estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Documentos</dt>
                        <dd class="mt-1 text-3xl font-semibold text-gray-900"><?php echo $stats['total_documents']; ?></dd>
                    </dl>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Backups</dt>
                        <dd class="mt-1 text-3xl font-semibold text-gray-900"><?php echo $stats['backups']; ?></dd>
                    </dl>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Reportes CNV</dt>
                        <dd class="mt-1 text-3xl font-semibold text-gray-900"><?php echo $stats['reportes_cnv']; ?></dd>
                    </dl>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Última Subida</dt>
                        <dd class="mt-1 text-xl font-semibold text-gray-900"><?php echo $stats['last_upload'] ?? 'Ninguna'; ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Gráfico de documentos -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Documentos por Tipo</h3>
                <div class="max-w-md mx-auto">
                    <canvas id="docsChart" height="150"></canvas>
                </div>
            </div>
        </div>

        <!-- Subir documento -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Subir Documento</h3>
                
                <!-- Restricciones horarias -->
                <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-3 rounded-lg <?php echo $canUploadCNV ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'; ?>">
                        <span class="font-semibold">Reportes CNV:</span> 
                        <?php echo $canUploadCNV ? 'Disponible ahora' : 'Solo martes de 00:00 a 12:00'; ?>
                    </div>
                    <div class="p-3 rounded-lg <?php echo $canUploadBackup ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'; ?>">
                        <span class="font-semibold">Backups:</span> 
                        <?php echo $canUploadBackup ? 'Disponible ahora' : 'Solo viernes de 00:00 a 18:00'; ?>
                    </div>
                    <div class="p-3 rounded-lg <?php echo $canUploadEvidencia ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'; ?>">
                        <span class="font-semibold">Evidencias:</span> 
                        <?php echo $canUploadEvidencia ? 'Disponible ahora' : 'Solo viernes de 00:00 a 18:00'; ?>
                    </div>
                </div>
                
                <form id="uploadForm" action="../api/upload.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label for="docType" class="block text-sm font-medium text-gray-700">Tipo de Documento</label>
                        <select id="docType" name="type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 bg-blue-50 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="backup" <?php echo !$canUploadBackup ? 'disabled' : ''; ?>>Backup de Seguridad</option>
                            <option value="evidencia" <?php echo !$canUploadEvidencia ? 'disabled' : ''; ?>>Evidencia de Envío</option>
                            <option value="reporte_cnv" <?php echo !$canUploadCNV ? 'disabled' : ''; ?>>Reporte CNV</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    
                    <!-- Contenedor para archivos únicos (backup, reporte_cnv, otro) -->
                    <div id="single-file-container" class="space-y-4">
                        <div>
                            <label for="file-upload" class="block text-sm font-medium text-gray-700">Archivo</label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600 justify-center">
                                        <label for="file-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                            <span>Seleccionar archivo</span>
                                            <input id="file-upload" name="file" type="file" class="sr-only">
                                        </label>
                                    </div>
                                    <p class="text-xs text-gray-500" id="file-type-hint">
                                        Archivos permitidos según tipo de documento
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contenedor para múltiples archivos de evidencia -->
                    <div id="evidencia-container" class="space-y-4 hidden">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Archivos de Evidencia (Máximo 3)</label>
                            <div class="mt-1 space-y-2">
                                <div class="flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600 justify-center">
                                            <label for="evidencia-file-1" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                                <span>Archivo 1</span>
                                                <input id="evidencia-file-1" name="evidencia_files[]" type="file" accept="image/*" class="sr-only evidencia-file">
                                            </label>
                                        </div>
                                        <p class="text-xs text-gray-500 file-name-1">No seleccionado</p>
                                    </div>
                                </div>
                                
                                <div class="flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600 justify-center">
                                            <label for="evidencia-file-2" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                                <span>Archivo 2 (opcional)</span>
                                                <input id="evidencia-file-2" name="evidencia_files[]" type="file" accept="image/*" class="sr-only evidencia-file">
                                            </label>
                                        </div>
                                        <p class="text-xs text-gray-500 file-name-2">No seleccionado</p>
                                    </div>
                                </div>
                                
                                <div class="flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600 justify-center">
                                            <label for="evidencia-file-3" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                                <span>Archivo 3 (opcional)</span>
                                                <input id="evidencia-file-3" name="evidencia_files[]" type="file" accept="image/*" class="sr-only evidencia-file">
                                            </label>
                                        </div>
                                        <p class="text-xs text-gray-500 file-name-3">No seleccionado</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campo de descripción para "otros" -->
                    <div id="description-container" class="hidden">
                        <label for="description" class="block text-sm font-medium text-gray-700">Descripción</label>
                        <textarea id="description" name="description" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Describe el propósito o contenido de este documento..."></textarea>
                    </div>
                    
                    <div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Subir Documento
                        </button>
                    </div>
                    <div id="uploadStatus" class="mt-2 hidden">
                        <div class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm text-gray-700">Subiendo documento...</span>
                        </div>
                        <div class="mt-2 h-2 bg-gray-200 rounded-full">
                            <div id="uploadProgress" class="h-2 bg-blue-500 rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Documentos recientes -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Documentos Recientes</h3>
                <?php if (!empty($recentDocuments)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentDocuments as $doc): ?>
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
                                            <?php if (!empty($doc['description'])): ?>
                                            <div class="text-xs text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($doc['description']); ?></div>
                                            <?php endif; ?>
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
                <div class="mt-4 text-right">
                    <a href="documents.php" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                        Ver todos los documentos →
                    </a>
                </div>
                <?php else: ?>
                <p class="text-gray-500 text-sm">No has subido ningún documento aún.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
<script>
// Gráfico de documentos
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('docsChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Backups', 'Evidencias', 'Reportes CNV', 'Otros'],
            datasets: [{
                data: [
                    <?php echo $stats['backups']; ?>,
                    <?php echo $stats['evidencias']; ?>,
                    <?php echo $stats['reportes_cnv']; ?>,
                    <?php echo $stats['otros']; ?>
                ],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(153, 102, 255, 0.8)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
    
    // Manejo del tipo de documento seleccionado
    const docTypeSelect = document.getElementById('docType');
    const singleFileContainer = document.getElementById('single-file-container');
    const evidenciaContainer = document.getElementById('evidencia-container');
    const descriptionContainer = document.getElementById('description-container');
    const fileTypeHint = document.getElementById('file-type-hint');
    const fileUpload = document.getElementById('file-upload');
    
    // Función para actualizar la interfaz según el tipo de documento
    function updateFormByDocType() {
        const selectedType = docTypeSelect.value;
        
        // Resetear el formulario
        singleFileContainer.classList.remove('hidden');
        evidenciaContainer.classList.add('hidden');
        descriptionContainer.classList.add('hidden');
        
        // Configurar según el tipo seleccionado
        switch(selectedType) {
            case 'backup':
                fileTypeHint.textContent = 'Solo archivos ZIP o RAR hasta 10MB';
                fileUpload.setAttribute('accept', '.zip,.rar');
                break;
            case 'evidencia':
                singleFileContainer.classList.add('hidden');
                evidenciaContainer.classList.remove('hidden');
                break;
            case 'reporte_cnv':
                fileTypeHint.textContent = 'Archivos PDF, DOC, DOCX, XLS, XLSX hasta 10MB';
                fileUpload.setAttribute('accept', '.pdf,.doc,.docx,.xls,.xlsx');
                break;
            case 'otro':
                fileTypeHint.textContent = 'Cualquier tipo de archivo hasta 10MB';
                fileUpload.removeAttribute('accept');
                descriptionContainer.classList.remove('hidden');
                break;
        }
    }
    
    // Eventos para los archivos de evidencia
    const evidenciaFiles = document.querySelectorAll('.evidencia-file');
    evidenciaFiles.forEach((input, index) => {
        input.addEventListener('change', function() {
            const fileNameDisplay = document.querySelector(`.file-name-${index + 1}`);
            if (this.files.length > 0) {
                fileNameDisplay.textContent = this.files[0].name;
            } else {
                fileNameDisplay.textContent = 'No seleccionado';
            }
        });
    });
    
    // Mostrar el nombre del archivo seleccionado
    fileUpload.addEventListener('change', function() {
        if (this.files.length > 0) {
            fileTypeHint.textContent = `Archivo seleccionado: ${this.files[0].name}`;
        } else {
            updateFormByDocType(); // Restaurar el mensaje original
        }
    });
    
    // Evento al cambiar tipo de documento
    docTypeSelect.addEventListener('change', updateFormByDocType);
    
    // Inicializar el formulario según el tipo de documento seleccionado
    updateFormByDocType();
    
    // Validación del formulario
    const uploadForm = document.getElementById('uploadForm');
    const uploadStatus = document.getElementById('uploadStatus');
    const uploadProgress = document.getElementById('uploadProgress');
    
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const selectedType = docTypeSelect.value;
        let isValid = true;
        let errorMessage = '';
        
        // Validar según el tipo de documento
        if (selectedType === 'evidencia') {
            // Para evidencias, verificar que al menos una imagen esté seleccionada
            const hasFile = Array.from(evidenciaFiles).some(input => input.files.length > 0);
            if (!hasFile) {
                isValid = false;
                errorMessage = 'Debes seleccionar al menos una imagen para la evidencia';
            } else {
                // Verificar que sean imágenes
                const invalidFile = Array.from(evidenciaFiles)
                    .filter(input => input.files.length > 0)
                    .find(input => !input.files[0].type.startsWith('image/'));
                
                if (invalidFile) {
                    isValid = false;
                    errorMessage = 'Solo se permiten archivos de imagen para evidencias';
                }
            }
        } else {
            // Para los demás tipos, verificar que hay un archivo
            if (fileUpload.files.length === 0) {
                isValid = false;
                errorMessage = 'Debes seleccionar un archivo';
            } else {
                // Verificar restricciones específicas por tipo
                if (selectedType === 'backup') {
                    const fileName = fileUpload.files[0].name.toLowerCase();
                    if (!fileName.endsWith('.zip') && !fileName.endsWith('.rar')) {
                        isValid = false;
                        errorMessage = 'Para backups solo se permiten archivos ZIP o RAR';
                    }
                }
                
                // Verificar tamaño máximo (10MB)
                if (fileUpload.files[0].size > 10 * 1024 * 1024) {
                    isValid = false;
                    errorMessage = 'El archivo es demasiado grande. El tamaño máximo es 10MB.';
                }
            }
            
            // Verificar descripción para 'otro'
            if (selectedType === 'otro' && document.getElementById('description').value.trim() === '') {
                isValid = false;
                errorMessage = 'Debes proporcionar una descripción para este tipo de documento';
            }
        }
        
        // Validar restricciones horarias
        if (selectedType === 'reporte_cnv' && !<?php echo $canUploadCNV ? 'true' : 'false'; ?>) {
            isValid = false;
            errorMessage = 'Los reportes CNV solo pueden subirse los martes de 00:00 a 12:00';
        } else if ((selectedType === 'backup' || selectedType === 'evidencia') && !<?php echo $canUploadBackup ? 'true' : 'false'; ?>) {
            isValid = false;
            errorMessage = 'Los backups y evidencias solo pueden subirse los viernes de 00:00 a 18:00';
        }
        
        if (!isValid) {
            alert(errorMessage);
            return;
        }
        
        // Proceder con la subida
        uploadStatus.classList.remove('hidden');
        
        const formData = new FormData(this);
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '../api/upload.php', true);
        
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                uploadProgress.style.width = percentComplete + '%';
            }
        };
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        window.location.href = 'index.php?success=1';
                    } else {
                        window.location.href = 'index.php?error=' + encodeURIComponent(response.message || 'Error al subir el documento');
                    }
                } catch (e) {
                    window.location.href = 'index.php?error=' + encodeURIComponent('Error al procesar la respuesta del servidor');
                }
            } else {
                window.location.href = 'index.php?error=' + encodeURIComponent('Error al subir el documento: ' + xhr.status);
            }
        };
        
        xhr.onerror = function() {
            window.location.href = 'index.php?error=' + encodeURIComponent('Error de red al intentar subir el documento');
        };
        
        xhr.send(formData);
    });
});

// Función para formatear tamaño de archivo
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>