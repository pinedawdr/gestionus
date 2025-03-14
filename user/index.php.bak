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

// Set page title for the header
$pageTitle = "Dashboard de Usuario";
$userRole = "user";
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
                                        echo 'Backup de seguridad (viernes)';
                                        break;
                                    case 'evidencia':
                                        echo 'Evidencias de envío (viernes)';
                                        break;
                                    case 'reporte_cnv':
                                        echo 'Reporte CNV (martes)';
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
                <div>
                    <canvas id="docsChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Subir documento -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Subir Documento</h3>
                <form id="uploadForm" class="space-y-4">
                    <div>
                        <label for="docType" class="block text-sm font-medium text-gray-700">Tipo de Documento</label>
                        <select id="docType" name="type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="backup">Backup de Seguridad</option>
                            <option value="evidencia">Evidencia de Envío</option>
                            <option value="reporte_cnv">Reporte CNV</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div>
                        <label for="docFile" class="block text-sm font-medium text-gray-700">Archivo</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600">
                                    <label for="file-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                        <span>Seleccionar archivo</span>
                                        <input id="file-upload" name="file" type="file" class="sr-only">
                                    </label>
                                    <p class="pl-1">o arrastrar y soltar</p>
                                </div>
                                <p class="text-xs text-gray-500">PDF, DOCX, XLSX, ZIP hasta 10MB</p>
                            </div>
                        </div>
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
                                    <a href="<?php echo $doc['secure_url']; ?>" download class="text-green-600 hover:text-green-900">Descargar</a>
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
    
    // Manejo de subida de archivos
    const uploadForm = document.getElementById('uploadForm');
    const fileInput = document.getElementById('file-upload');
    const uploadStatus = document.getElementById('uploadStatus');
    const uploadProgress = document.getElementById('uploadProgress');
    
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const file = fileInput.files[0];
        if (!file) {
            alert('Por favor selecciona un archivo');
            return;
        }
        
        // Verificar tamaño máximo (10MB)
        if (file.size > 10 * 1024 * 1024) {
            alert('El archivo es demasiado grande. El tamaño máximo es 10MB.');
            return;
        }
        
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', document.getElementById('docType').value);
        
        uploadStatus.classList.remove('hidden');
        
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
                        alert('Documento subido correctamente');
                        window.location.reload();
                    } else {
                        alert('Error: ' + response.message);
                        uploadStatus.classList.add('hidden');
                    }
                } catch (e) {
                    alert('Error al procesar la respuesta del servidor');
                    uploadStatus.classList.add('hidden');
                }
            } else {
                alert('Error al subir el documento');
                uploadStatus.classList.add('hidden');
            }
        };
        
        xhr.onerror = function() {
            alert('Error de red al intentar subir el documento');
            uploadStatus.classList.add('hidden');
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