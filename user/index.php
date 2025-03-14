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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - GestiónUS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                            <a href="index.php" class="border-b-2 border-white px-1 pt-1 text-sm font-medium">Dashboard</a>
                            <a href="documents.php" class="border-transparent border-b-2 hover:border-gray-300 px-1 pt-1 text-sm font-medium">Mis Documentos</a>
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
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="docType" class="block text-sm font-medium text-gray-700">Tipo de Documento</label>
                                    <select id="docType" name="type" class="mt-1 block w-full bg-white border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                        <option value="backup">Backup de Seguridad</option>
                                        <option value="evidencia">Evidencia de Envío</option>
                                        <option value="reporte_cnv">Reporte CNV</option>
                                        <option value="otro">Otro Documento</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="docDescription" class="block text-sm font-medium text-gray-700">Descripción</label>
                                    <input type="text" id="docDescription" name="description" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Breve descripción del documento">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Archivo</label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="docFile" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                                <span>Subir un archivo</span>
                                                <input id="docFile" name="file" type="file" class="sr-only">
                                            </label>
                                            <p class="pl-1">o arrastrar y soltar</p>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            ZIP, RAR, PDF, DOC, DOCX, XLS, XLSX, JPG, PNG hasta 50MB
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <button type="submit" id="uploadBtn" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Subir Documento
                                </button>
                                <span id="uploadStatus" class="ml-3 text-sm text-gray-500"></span>
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
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tamaño</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($recentDocuments as $doc): ?>
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
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="<?php echo $doc['secure_url']; ?>" target="_blank" class="text-blue-600 hover:text-blue-900">Ver</a>
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
                        <div class="text-center py-4">
                            <p class="text-gray-500">No has subido ningún documento aún.</p>
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
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(201, 203, 207, 0.7)'
                    ],
                    borderColor: [
                        'rgb(54, 162, 235)',
                        'rgb(75, 192, 192)',
                        'rgb(153, 102, 255)',
                        'rgb(201, 203, 207)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Manejar subida de documentos
        const uploadForm = document.getElementById('uploadForm');
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadStatus = document.getElementById('uploadStatus');
        const fileInput = document.getElementById('docFile');
        
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const file = fileInput.files[0];
            if (!file) {
                uploadStatus.textContent = 'Por favor, selecciona un archivo.';
                uploadStatus.classList.add('text-red-500');
                return;
            }
            
            // Validar tamaño (50MB máximo)
            if (file.size > 50 * 1024 * 1024) {
                uploadStatus.textContent = 'El archivo excede el tamaño máximo permitido (50MB).';
                uploadStatus.classList.add('text-red-500');
                return;
            }
            
            // Prepare form data
            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', document.getElementById('docType').value);
            formData.append('description', document.getElementById('docDescription').value);
            
            // Disable button and show loading status
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Subiendo...';
            uploadStatus.textContent = 'Subiendo documento...';
            uploadStatus.classList.remove('text-red-500');
            uploadStatus.classList.add('text-blue-500');
            
            // Send request
            fetch('../api/upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    uploadStatus.textContent = 'Documento subido correctamente.';
                    uploadStatus.classList.remove('text-red-500', 'text-blue-500');
                    uploadStatus.classList.add('text-green-500');
                    
                    // Reset form
                    uploadForm.reset();
                    
                    // Reload page after 2 seconds to refresh stats
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    uploadStatus.textContent = 'Error: ' + data.message;
                    uploadStatus.classList.remove('text-blue-500', 'text-green-500');
                    uploadStatus.classList.add('text-red-500');
                }
            })
            .catch(error => {
                uploadStatus.textContent = 'Error de conexión.';
                uploadStatus.classList.remove('text-blue-500', 'text-green-500');
                uploadStatus.classList.add('text-red-500');
                console.error('Error:', error);
            })
            .finally(() => {
                // Re-enable button
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = 'Subir Documento';
            });
        });
    });

    // Función para formatear tamaño de archivo
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    </script>
</body>
</html>