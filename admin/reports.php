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

// Definir variables para el header
$page_title = "Reportes";
$_SESSION['name'] = $user['name'];

// Incluir el header global
include_once '../includes/header.php';
?>

<h1 class="text-2xl font-semibold text-gray-900 mb-6">Reportes</h1>
                
                <!-- Filtros de reporte -->
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <form action="" method="GET" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label for="report_type" class="block text-sm font-medium text-gray-700">Tipo de Reporte</label>
                                    <select name="report_type" id="report_type" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                                        <option value="">Seleccionar</option>
                                        <option value="uploads_by_user" <?php echo $reportType === 'uploads_by_user' ? 'selected' : ''; ?>>Documentos por Usuario</option>
                                        <option value="uploads_by_type" <?php echo $reportType === 'uploads_by_type' ? 'selected' : ''; ?>>Documentos por Tipo</option>
                                        <option value="uploads_by_date" <?php echo $reportType === 'uploads_by_date' ? 'selected' : ''; ?>>Documentos por Fecha</option>
                                        <option value="missing_uploads" <?php echo $reportType === 'missing_uploads' ? 'selected' : ''; ?>>Documentos Pendientes</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="user_id" class="block text-sm font-medium text-gray-700">Usuario</label>
                                    <select name="user_id" id="user_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                        <option value="">Todos</option>
                                        <?php foreach ($users as $userOption): ?>
                                        <option value="<?php echo $userOption['id']; ?>" <?php echo $selectedUserId == $userOption['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($userOption['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="start_date" class="block text-sm font-medium text-gray-700">Fecha Inicio</label>
                                    <input type="date" name="start_date" id="start_date" value="<?php echo $startDate; ?>" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="end_date" class="block text-sm font-medium text-gray-700">Fecha Fin</label>
                                    <input type="date" name="end_date" id="end_date" value="<?php echo $endDate; ?>" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Generar Reporte
                                </button>
                                <a href="reports.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Limpiar
                                </a>
                                <?php if (!empty($reportType) && !empty($reportData)): ?>
                                <button type="button" id="export-btn" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    Exportar CSV
                                </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($reportType)): ?>
                <!-- Resultados del reporte -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-4 py-5 sm:p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4"><?php echo $reportTitle; ?></h2>
                        
                        <?php if (empty($reportData)): ?>
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay datos</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                No se encontraron datos para el reporte con los filtros aplicados.
                            </p>
                        </div>
                        <?php else: ?>
                            <?php if ($reportType === 'missing_uploads'): ?>
                            <!-- Reporte de documentos pendientes -->
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Documento Pendiente</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha Límite</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($reportData as $user): ?>
                                            <?php foreach ($user['missing_docs'] as $doc): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['user_name']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['user_email']); ?></div>
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
                                                    <?php echo $doc['due_date']; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                        <?php echo $doc['status']; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button type="button" class="send-notification text-blue-600 hover:text-blue-900" data-user-id="<?php echo $user['user_id']; ?>" data-user-name="<?php echo htmlspecialchars($user['user_name']); ?>" data-user-email="<?php echo htmlspecialchars($user['user_email']); ?>" data-doc-type="<?php echo $doc['type']; ?>">
                                                        Notificar
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <!-- Gráfico para reportes con datos numéricos -->
                            <div class="h-96">
                                <canvas id="reportChart"></canvas>
                            </div>
                            
                            <!-- Tabla de datos -->
                            <div class="mt-6 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <?php if ($reportType === 'uploads_by_user'): ?>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Documentos</th>
                                            <?php elseif ($reportType === 'uploads_by_type'): ?>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                            <?php elseif ($reportType === 'uploads_by_date'): ?>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if ($reportType === 'uploads_by_user'): ?>
                                            <?php foreach ($reportData as $item): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $item['total_docs']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php elseif ($reportType === 'uploads_by_type'): ?>
                                            <?php foreach ($reportData as $item): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        <?php 
                                                        switch ($item['type']) {
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
                                                        switch ($item['type']) {
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
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $item['total']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php elseif ($reportType === 'uploads_by_date'): ?>
                                            <?php foreach ($reportData as $item): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?php 
                                                    $date = new DateTime($item['upload_date']);
                                                    echo $date->format('d/m/Y');
                                                    ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $item['total']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
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
                                        <input type="text" id="notification-subject" name="subject" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" value="Recordatorio: Documento Pendiente">
                                    </div>
                                    <div>
                                        <label for="notification-message" class="block text-sm font-medium text-gray-700">Mensaje</label>
                                        <textarea id="notification-message" name="message" rows="4" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">Estimado usuario,

Le recordamos que tiene un documento pendiente por subir en el sistema Gestionus. Por favor, suba el documento requerido a la brevedad posible.

Saludos cordiales,
Administración Gestionus</textarea>
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
                    &copy; <?php echo date('Y'); ?> Gestionus - Todos los derechos reservados
                </p>
            </div>
        </footer>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Referencias a elementos del DOM
        const notificationModal = document.getElementById('notificationModal');
        const notificationBtns = document.querySelectorAll('.send-notification');
        const closeModalBtn = document.getElementById('close-notification-modal');
        const sendNotificationBtn = document.getElementById('send-notification-btn');
        const notificationRecipient = document.querySelector('#notification-recipient span');
        const notificationUserId = document.getElementById('notification-user-id');
        const notificationSubject = document.getElementById('notification-subject');
        const notificationMessage = document.getElementById('notification-message');
        
        // Botón de exportar
        const exportBtn = document.getElementById('export-btn');
        
        <?php if (!empty($reportType) && in_array($reportType, ['uploads_by_user', 'uploads_by_type', 'uploads_by_date']) && !empty($reportData)): ?>
        // Gráfico
        const ctx = document.getElementById('reportChart').getContext('2d');
        const reportChart = new Chart(ctx, {
            type: '<?php echo $chartType; ?>',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: '<?php echo $reportTitle; ?>',
                    data: <?php echo json_encode($chartData); ?>,
                    backgroundColor: <?php echo json_encode($chartColors); ?>,
                    borderColor: <?php echo $chartType === 'line' ? json_encode(['rgba(54, 162, 235, 1)']) : json_encode($chartColors); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: '<?php echo $chartType === 'pie' ? 'right' : 'top'; ?>',
                    },
                    tooltip: {
                        callbacks: {
                            <?php if ($chartType === 'pie'): ?>
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                            <?php endif; ?>
                        }
                    }
                },
                scales: {
                    <?php if ($chartType !== 'pie'): ?>
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                    <?php endif; ?>
                }
            }
        });
        <?php endif; ?>
        
        // Función para abrir modal de notificación
        function openNotificationModal(userId, userName, userEmail, docType) {
            // Configurar destinatario
            notificationRecipient.textContent = `${userName} (${userEmail})`;
            notificationUserId.value = userId;
            
            // Personalizar mensaje según tipo de documento
            if (docType) {
                let docTypeName = '';
                switch (docType) {
                    case 'backup':
                        docTypeName = 'Backup de Seguridad';
                        break;
                    case 'evidencia':
                        docTypeName = 'Evidencia de Envío';
                        break;
                    case 'reporte_cnv':
                        docTypeName = 'Reporte CNV';
                        break;
                    default:
                        docTypeName = 'Documento';
                }
                
                notificationSubject.value = `Recordatorio: ${docTypeName} Pendiente`;
                notificationMessage.value = `Estimado usuario,

Le recordamos que tiene pendiente la subida del ${docTypeName} en el sistema Gestionus. Por favor, suba el documento requerido a la brevedad posible.

Saludos cordiales,
Administración Gestionus`;
            }
            
            // Mostrar modal
            notificationModal.classList.remove('hidden');
        }
        
        // Event Listeners para botones de notificación
        notificationBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const userName = this.getAttribute('data-user-name');
                const userEmail = this.getAttribute('data-user-email');
                const docType = this.getAttribute('data-doc-type');
                
                openNotificationModal(userId, userName, userEmail, docType);
            });
        });
        
        // Cerrar modal
        closeModalBtn.addEventListener('click', function() {
            notificationModal.classList.add('hidden');
        });
        
        // Enviar notificación
        sendNotificationBtn.addEventListener('click', function() {
            const userId = notificationUserId.value;
            const subject = notificationSubject.value;
            const message = notificationMessage.value;
            
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
        
        // Exportar a CSV
        if (exportBtn) {
            exportBtn.addEventListener('click', function() {
                let csvContent = '';
                let fileName = '';
                
                <?php if ($reportType === 'uploads_by_user'): ?>
                // Encabezados
                csvContent = 'Usuario,Total Documentos\n';
                
                // Datos
                <?php foreach ($reportData as $item): ?>
                csvContent += '<?php echo addslashes($item['name']); ?>,<?php echo $item['total_docs']; ?>\n';
                <?php endforeach; ?>
                
                fileName = 'documentos_por_usuario.csv';
                <?php elseif ($reportType === 'uploads_by_type'): ?>
                // Encabezados
                csvContent = 'Tipo,Total\n';
                
                // Datos
                <?php foreach ($reportData as $item): ?>
                csvContent += '<?php 
                    switch ($item['type']) {
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
                ?>,<?php echo $item['total']; ?>\n';
                <?php endforeach; ?>
                
                fileName = 'documentos_por_tipo.csv';
                <?php elseif ($reportType === 'uploads_by_date'): ?>
                // Encabezados
                csvContent = 'Fecha,Total\n';
                
                // Datos
                <?php foreach ($reportData as $item): ?>
                csvContent += '<?php 
                    $date = new DateTime($item['upload_date']);
                    echo $date->format('d/m/Y');
                ?>,<?php echo $item['total']; ?>\n';
                <?php endforeach; ?>
                
                fileName = 'documentos_por_fecha.csv';
                <?php elseif ($reportType === 'missing_uploads'): ?>
                // Encabezados
                csvContent = 'Usuario,Email,Documento Pendiente,Fecha Límite,Estado\n';
                
                // Datos
                <?php foreach ($reportData as $user): ?>
                    <?php foreach ($user['missing_docs'] as $doc): ?>
                csvContent += '<?php echo addslashes($user['user_name']); ?>,<?php echo $user['user_email']; ?>,<?php 
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
                ?>,<?php echo $doc['due_date']; ?>,<?php echo $doc['status']; ?>\n';
                    <?php endforeach; ?>
                <?php endforeach; ?>
                
                fileName = 'documentos_pendientes.csv';
                <?php endif; ?>
                
                // Crear enlace de descarga
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                
                link.setAttribute('href', url);
                link.setAttribute('download', fileName);
                link.style.visibility = 'hidden';
                
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        }
    });
    </script>
</body>
</html>

<?php include_once '../includes/footer.php'; ?>