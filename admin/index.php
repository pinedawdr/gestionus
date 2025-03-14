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

// Guardar el nombre de usuario en la sesión para el header global
$_SESSION['user_name'] = $user['name'];

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

// Definir título de la página
$page_title = "Panel de Administración";

// Incluir JavaScript adicional para gráficos
$extra_js = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

// Incluir el header global
include_once '../includes/header.php';
?>

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
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($pendingUploads as $pending): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($pending['name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($pending['email']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <ul class="text-sm text-gray-500">
                                    <?php foreach ($pending['pending_types'] as $type): ?>
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
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button class="text-blue-600 hover:text-blue-900 send-notification" data-user-id="<?php echo $pending['user_id']; ?>" data-user-name="<?php echo htmlspecialchars($pending['name']); ?>">
                                    Enviar Recordatorio
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-sm text-gray-500">No hay documentos pendientes.</p>
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
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recentDocuments as $doc): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <?php if ($doc['file_type'] === 'pdf'): ?>
                                    <svg class="h-8 w-8 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                                    </svg>
                                    <?php elseif (in_array($doc['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                    <svg class="h-8 w-8 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                    </svg>
                                    <?php elseif (in_array($doc['file_type'], ['doc', 'docx'])): ?>
                                    <svg class="h-8 w-8 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                    </svg>
                                    <?php elseif (in_array($doc['file_type'], ['xls', 'xlsx'])): ?>
                                    <svg class="h-8 w-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                    </svg>
                                    <?php else: ?>
                                    <svg class="h-8 w-8 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                    </svg>
                                    <?php endif; ?>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($doc['original_name']); ?></div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo ucfirst($doc['type']); ?> • 
                                            <?php echo formatFileSize($doc['file_size']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($doc['user_name']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500"><?php echo $doc['created_at_formatted']; ?></div>
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
            <p class="text-sm text-gray-500">No hay documentos recientes.</p>
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
    
    <!-- Actividad reciente -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Actividad Mensual</h3>
            <div>
                <canvas id="monthlyActivityChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Modal de notificación -->
<div id="notificationModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Enviar Recordatorio
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Enviar un recordatorio a <span id="notification-user-name" class="font-medium"></span> sobre los documentos pendientes.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <form id="notificationForm">
                        <input type="hidden" id="notification-user-id" name="userId">
                        <div class="mb-4">
                            <label for="notification-subject" class="block text-sm font-medium text-gray-700">Asunto</label>
                            <input type="text" id="notification-subject" name="subject" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" value="Recordatorio: Documentos Pendientes">
                        </div>
                        <div>
                            <label for="notification-message" class="block text-sm font-medium text-gray-700">Mensaje</label>
                            <textarea id="notification-message" name="message" rows="4" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">Estimado/a usuario/a,

Le recordamos que tiene documentos pendientes por subir en el sistema Gestionus. Por favor, suba los documentos requeridos a la brevedad posible.

Saludos cordiales,
Administración Gestionus</textarea>
                        </div>
                    </form>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="sendNotificationBtn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Enviar
                </button>
                <button type="button" id="closeModalBtn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
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
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Gráfico de actividad mensual (datos de ejemplo)
    const monthlyCtx = document.getElementById('monthlyActivityChart').getContext('2d');
    const monthlyChart = new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
            datasets: [{
                label: 'Documentos Subidos',
                data: [12, 19, 15, 8, 22, 14, 11, 9, 17, 13, 15, <?php echo $stats['documents_today']; ?>],
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Funcionalidad del modal de notificación
    const notificationButtons = document.querySelectorAll('.send-notification');
    const notificationModal = document.getElementById('notificationModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const sendNotificationBtn = document.getElementById('sendNotificationBtn');
    const notificationUserId = document.getElementById('notification-user-id');
    const notificationUserName = document.getElementById('notification-user-name');
    
    notificationButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name');
            
            notificationUserId.value = userId;
            notificationUserName.textContent = userName;
            
            notificationModal.classList.remove('hidden');
        });
    });
    
    closeModalBtn.addEventListener('click', function() {
        notificationModal.classList.add('hidden');
    });
    
    sendNotificationBtn.addEventListener('click', function() {
        const userId = notificationUserId.value;
        const subject = document.getElementById('notification-subject').value;
        const message = document.getElementById('notification-message').value;
        
        // Aquí iría el código para enviar la notificación
        // Por ejemplo, una llamada AJAX a un endpoint de la API
        
        // Simulación de envío exitoso
        alert('Notificación enviada correctamente');
        notificationModal.classList.add('hidden');
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>