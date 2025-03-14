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

// Obtener estadísticas generales
$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'total_documents' => 0,
    'backups' => 0,
    'evidencias' => 0,
    'reportes_cnv' => 0,
    'otros' => 0,
    'documents_today' => 0
];

// Contar usuarios
$sql = "SELECT COUNT(*) as count FROM users";
$result = $db->fetchOne($sql);
$stats['total_users'] = $result['count'];

$sql = "SELECT COUNT(*) as count FROM users WHERE active = 1";
$result = $db->fetchOne($sql);
$stats['active_users'] = $result['count'];

// Contar documentos
$sql = "SELECT COUNT(*) as count FROM documents";
$result = $db->fetchOne($sql);
$stats['total_documents'] = $result['count'];

// Contar documentos por tipo
$sql = "SELECT type, COUNT(*) as count FROM documents GROUP BY type";
$documentStats = $db->fetchAll($sql);

foreach ($documentStats as $stat) {
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

// Documentos subidos hoy
$sql = "SELECT COUNT(*) as count FROM documents WHERE DATE(created_at) = CURDATE()";
$result = $db->fetchOne($sql);
$stats['documents_today'] = $result['count'];

// Obtener usuarios con documentos pendientes
$pendingUploads = [];
$sql = "SELECT id, name, email FROM users WHERE active = 1 AND role = 'user'";
$users = $db->fetchAll($sql);

foreach ($users as $userItem) {
    $pending = checkPendingUploads($userItem['id']);
    if (!empty($pending)) {
        $pendingUploads[] = [
            'user_id' => $userItem['id'],
            'name' => $userItem['name'],
            'email' => $userItem['email'],
            'pending_types' => $pending
        ];
    }
}

// Obtener últimos documentos subidos
$sql = "SELECT d.*, u.name as user_name 
        FROM documents d 
        JOIN users u ON d.user_id = u.id 
        ORDER BY d.created_at DESC LIMIT 10";
$recentDocuments = $db->fetchAll($sql);

// Preparar URLs seguras para los documentos
foreach ($recentDocuments as &$doc) {
    $doc['secure_url'] = getSecureFileUrl($doc['file_path']);
    $doc['file_type'] = getFileType($doc['original_name']);
    $doc['created_at_formatted'] = formatDate($doc['created_at']);
}

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
    <title>Panel de Administración - GestiónUS</title>
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
                            <span class="ml-2 bg-yellow-500 text-blue-900 text-xs px-2 py-1 rounded">ADMIN</span>
                        </div>
                        <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                            <a href="index.php" class="border-b-2 border-white px-1 pt-1 text-sm font-medium">Dashboard</a>
                            <a href="users.php" class="border-transparent border-b-2 hover:border-gray-300 px-1 pt-1 text-sm font-medium">Usuarios</a>
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
                <h1 class="text-2xl font-semibold text-gray-900 mb-6">Panel de Administración</h1>
                
                <!-- Tarjetas de estadísticas -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Usuarios</dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900"><?php echo $stats['active_users']; ?></dd>
                                <dd class="text-sm text-gray-500"><?php echo $stats['total_users']; ?> registrados</dd>
                            </dl>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Documentos</dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900"><?php echo $stats['total_documents']; ?></dd>
                                <dd class="text-sm text-gray-500"><?php echo $stats['documents_today']; ?> hoy</dd>
                            </dl>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Backups</dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900"><?php echo $stats['backups']; ?></dd>
                                <dd class="text-sm text-gray-500"><?php echo date('l') === 'Friday' ? 'Obligatorio hoy' : ''; ?></dd>
                            </dl>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Reportes CNV</dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900"><?php echo $stats['reportes_cnv']; ?></dd>
                                <dd class="text-sm text-gray-500"><?php echo date('l') === 'Tuesday' ? 'Obligatorio hoy' : ''; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Documentos pendientes y Últimos documentos -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Documentos pendientes -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Documentos Pendientes</h3>
                            <?php if (!empty($pendingUploads)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Documentos Pendientes</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($pendingUploads as $pendingUser): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($pendingUser['name']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($pendingUser['email']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <ul class="text-sm text-gray-900">
                                                    <?php foreach ($pendingUser['pending_types'] as $type): ?>
                                                    <li>
                                                        <?php 
                                                        switch ($type) {
                                                            case 'backup':
                                                                echo '• Backup de seguridad (viernes)';
                                                                break;
                                                            case 'evidencia':
                                                                echo '• Evidencias de envío (viernes)';
                                                                break;
                                                            case 'reporte_cnv':
                                                                echo '• Reporte CNV (martes)';
                                                                break;
                                                        }
                                                        ?>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button type="button" class="send-notification text-blue-600 hover:text-blue-900" data-user-id="<?php echo $pendingUser['user_id']; ?>" data-user-name="<?php echo htmlspecialchars($pendingUser['name']); ?>" data-user-email="<?php echo htmlspecialchars($pendingUser['email']); ?>">
                                                    Notificar
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-4">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">Todos al día</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    No hay documentos pendientes por subir.
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Últimos documentos -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Últimos Documentos</h3>
                            <?php if (!empty($recentDocuments)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Documento</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($recentDocuments as $doc): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
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
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($doc['original_name']); ?></div>
                                                        <div class="text-xs text-gray-500"><?php echo formatFileSize($doc['file_size']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($doc['user_name']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $doc['created_at_formatted']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
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
                                <p class="text-gray-500">No hay documentos disponibles.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Gráficos de estadísticas -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Documentos por tipo -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Documentos por Tipo</h3>
                            <div>
                                <canvas id="documentsByTypeChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Otro gráfico de estadísticas -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Actividad Reciente</h3>
                            <div>
                                <canvas id="recentActivityChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de notificación -->
        <div id="notificationModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Overlay de fondo -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Enviar Notificación
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500" id="notification-recipient">
                                    Enviar notificación a: <span class="font-medium"></span>
                                </p>
                            </div>
                            <div class="mt-4">
                                <form id="notificationForm">
                                    <input type="hidden" id="notification-user-id">
                                    <div class="mb-4">
                                        <label for="notification-subject" class="block text-sm font-medium text-gray-700">Asunto</label>
                                        <input type="text" id="notification-subject" name="subject" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" value="Recordatorio: Documentos Pendientes">
                                    </div>
                                    <div>
                                        <label for="notification-message" class="block text-sm font-medium text-gray-700">Mensaje</label>
                                        <textarea id="notification-message" name="message" rows="4" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">Estimado usuario,

Le recordamos que tiene documentos pendientes por subir en el sistema GestiónUS. Por favor, suba los documentos requeridos a la brevedad posible.

Saludos cordiales,
Administración GestiónUS</textarea>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                        <button type="button" id="send-notification-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Enviar
                        </button>
                        <button type="button" id="close-notification-modal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm">
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
        // Gráfico de documentos por tipo
        const docTypeCtx = document.getElementById('documentsByTypeChart').getContext('2d');
        const docTypeChart = new Chart(docTypeCtx, {
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
        
        // Gráfico de actividad reciente (últimos 7 días)
        // Aquí deberías obtener datos reales de la base de datos para los últimos 7 días
        const activityCtx = document.getElementById('recentActivityChart').getContext('2d');
        const activityChart = new Chart(activityCtx, {
            type: 'bar',
            data: {
                labels: ['Hace 6 días', 'Hace 5 días', 'Hace 4 días', 'Hace 3 días', 'Hace 2 días', 'Ayer', 'Hoy'],
                datasets: [{
                    label: 'Documentos subidos',
                    data: [12, 19, 8, 15, 10, 7, <?php echo $stats['documents_today']; ?>],
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgb(54, 162, 235)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        
        // Modal de notificación
        const notificationModal = document.getElementById('notificationModal');
        const notificationBtns = document.querySelectorAll('.send-notification');
        const closeModalBtn = document.getElementById('close-notification-modal');
        const sendNotificationBtn = document.getElementById('send-notification-btn');
        const notificationRecipient = document.querySelector('#notification-recipient span');
        const notificationUserId = document.getElementById('notification-user-id');
        
        // Abrir modal
        notificationBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const userName = this.getAttribute('data-user-name');
                const userEmail = this.getAttribute('data-user-email');
                
                notificationRecipient.textContent = `${userName} (${userEmail})`;
                notificationUserId.value = userId;
                
                notificationModal.classList.remove('hidden');
            });
        });
        
        // Cerrar modal
        closeModalBtn.addEventListener('click', function() {
            notificationModal.classList.add('hidden');
        });
        
        // Enviar notificación
        sendNotificationBtn.addEventListener('click', function() {
            const userId = notificationUserId.value;
            const subject = document.getElementById('notification-subject').value;
            const message = document.getElementById('notification-message').value;
            
            if (!subject || !message) {
                alert('Por favor, complete todos los campos');
                return;
            }
            
            // Deshabilitar botón
            sendNotificationBtn.disabled = true;
            sendNotificationBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Enviando...';
            
            // Enviar solicitud
            fetch('../api/notifications.php?action=send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    subject: subject,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Notificación enviada correctamente');
                    notificationModal.classList.add('hidden');
                    
                    // Opcional: recargar la página después de enviar la notificación
                    // window.location.reload();
                } else {
                    alert('Error al enviar notificación: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión al enviar la notificación');
            })
            .finally(() => {
                // Re-habilitar botón
                sendNotificationBtn.disabled = false;
                sendNotificationBtn.innerHTML = 'Enviar';
            });
        });
        
        // Cerrar modal al hacer clic fuera
        window.addEventListener('click', function(event) {
            if (event.target == notificationModal) {
                notificationModal.classList.add('hidden');
            }
        });
    });
    </script>
</body>
</html>