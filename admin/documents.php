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

// Inicializar variables de filtro
$filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : '';
$type = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$startDate = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : '';

// Obtener usuarios para el selector de filtros
$usersQuery = "SELECT id, name FROM users WHERE active = 1 ORDER BY name";
$users = $db->fetchAll($usersQuery);

// Construir consulta según filtros
$params = [];
$sql = "SELECT d.*, u.name as user_name 
        FROM documents d 
        JOIN users u ON d.user_id = u.id 
        WHERE 1=1";

if (!empty($filter)) {
    $sql .= " AND (d.original_name LIKE ? OR d.description LIKE ?)";
    $filterParam = "%$filter%";
    $params[] = $filterParam;
    $params[] = $filterParam;
}

if (!empty($type)) {
    $sql .= " AND d.type = ?";
    $params[] = $type;
}

if ($selectedUserId) {
    $sql .= " AND d.user_id = ?";
    $params[] = $selectedUserId;
}

if ($startDate) {
    $sql .= " AND DATE(d.created_at) >= ?";
    $params[] = $startDate;
}

if ($endDate) {
    $sql .= " AND DATE(d.created_at) <= ?";
    $params[] = $endDate;
}

$sql .= " ORDER BY d.created_at DESC";

// Obtener documentos
$documents = $db->fetchAll($sql, $params);

// Preparar URLs seguras para los documentos
foreach ($documents as &$doc) {
    $doc['secure_url'] = getSecureFileUrl($doc['file_path']);
    $doc['created_at_formatted'] = formatDate($doc['created_at']);
}

// Definir variables para el header
$page_title = "Gestión de Documentos";
$_SESSION['name'] = $user['name'];

// Incluir el header global
include_once '../includes/header.php';
?>

<h1 class="text-2xl font-semibold text-gray-900 mb-6">Gestión de Documentos</h1>
                
<!-- Filtros -->
<div class="bg-white shadow rounded-lg mb-6">
    <div class="px-4 py-5 sm:p-6">
        <form action="" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="filter" class="block text-sm font-medium text-gray-700">Buscar</label>
                    <input type="text" name="filter" id="filter" value="<?php echo htmlspecialchars($filter); ?>" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" placeholder="Nombre de archivo, descripción...">
                </div>
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700">Tipo</label>
                    <select name="type" id="type" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <option value="">Todos</option>
                        <option value="backup" <?php echo $type === 'backup' ? 'selected' : ''; ?>>Backup</option>
                        <option value="evidencia" <?php echo $type === 'evidencia' ? 'selected' : ''; ?>>Evidencia</option>
                        <option value="reporte_cnv" <?php echo $type === 'reporte_cnv' ? 'selected' : ''; ?>>Reporte CNV</option>
                        <option value="otro" <?php echo $type === 'otro' ? 'selected' : ''; ?>>Otro</option>
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
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700">Desde</label>
                        <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($startDate); ?>" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700">Hasta</label>
                        <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($endDate); ?>" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Filtrar
                </button>
                <a href="documents.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Limpiar
                </a>
            </div>
        </form>
    </div>
</div>
                
<!-- Lista de documentos -->
<div class="bg-white shadow rounded-lg overflow-hidden">
    <div class="px-4 py-5 sm:p-6">
        <?php if (!empty($documents)): ?>
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Documento</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
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
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($doc['description'] ?? ''); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($doc['user_name']); ?></div>
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
                            <a href="<?php echo $doc['secure_url']; ?>&download=1" class="text-green-600 hover:text-green-900 mr-3">Descargar</a>
                            <button type="button" class="text-red-600 hover:text-red-900 delete-doc-btn" data-doc-id="<?php echo $doc['id']; ?>" data-doc-name="<?php echo htmlspecialchars($doc['original_name']); ?>">Eliminar</button>
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
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>

<!-- Modal de confirmación de eliminación -->
<div id="deleteModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Overlay de fondo -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
            <div>
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="delete-modal-title">
                        Eliminar Documento
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500" id="delete-doc-name">
                            ¿Estás seguro de que deseas eliminar el documento <span class="font-medium"></span>?
                        </p>
                        <p class="text-sm text-gray-500 mt-2">
                            Esta acción no se puede deshacer y el documento se eliminará permanentemente del sistema.
                        </p>
                    </div>
                </div>
            </div>
            <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                <button type="button" id="confirm-delete-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Eliminar
                </button>
                <button type="button" id="close-delete-modal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm">
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
    const deleteModal = document.getElementById('deleteModal');
    const deleteDocBtns = document.querySelectorAll('.delete-doc-btn');
    const closeDeleteModalBtn = document.getElementById('close-delete-modal');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const deleteDocNameSpan = document.querySelector('#delete-doc-name span');
    
    // Función para abrir modal de eliminación
    function openDeleteModal(docId, docName) {
        // Llenar datos
        confirmDeleteBtn.dataset.docId = docId;
        deleteDocNameSpan.textContent = docName;
        
        // Abrir modal
        deleteModal.classList.remove('hidden');
    }
    
    // Event Listeners para abrir modal
    deleteDocBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const docId = this.dataset.docId;
            const docName = this.dataset.docName;
            openDeleteModal(docId, docName);
        });
    });
    
    // Event Listener para cerrar modal
    closeDeleteModalBtn.addEventListener('click', function() {
        deleteModal.classList.add('hidden');
    });
    
    // Event Listener para cerrar modal haciendo clic fuera
    window.addEventListener('click', function(event) {
        if (event.target == deleteModal) {
            deleteModal.classList.add('hidden');
        }
    });
    
    // Event Listener para eliminar documento
    confirmDeleteBtn.addEventListener('click', function() {
        const docId = this.dataset.docId;
        
        // Deshabilitar botón
        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Eliminando...';
        
        // Enviar solicitud
        const formData = new FormData();
        formData.append('document_id', docId);
        
        fetch('../api/documents.php?action=delete', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Documento eliminado correctamente');
                deleteModal.classList.add('hidden');
                
                // Recargar página
                window.location.reload();
            } else {
                alert('Error al eliminar documento: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión al eliminar documento');
        })
        .finally(() => {
            // Re-habilitar botón
            confirmDeleteBtn.disabled = false;
            confirmDeleteBtn.innerHTML = 'Eliminar';
        });
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>