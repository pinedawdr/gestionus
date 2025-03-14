<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Verificar que se ha proporcionado un token
if (!isset($_GET['token']) || empty($_GET['token'])) {
    header('Location: index.php');
    exit;
}

$token = sanitize($_GET['token']);
$db = Database::getInstance();

// Verificar si existen las tablas necesarias
$checkTable = $db->fetchOne("SHOW TABLES LIKE 'shares'");
if (!$checkTable) {
    $_SESSION['error'] = 'El sistema de compartir archivos no está configurado correctamente.';
    header('Location: index.php');
    exit;
}

// Obtener información de la compartición
$sql = "SELECT s.*, u.name as shared_by_name 
        FROM shares s 
        LEFT JOIN users u ON s.created_by = u.id 
        WHERE s.token = ? AND s.expires_at > NOW() 
        LIMIT 1";
$share = $db->fetchOne($sql, [$token]);

// Si no existe o ha expirado, redirigir
if (!$share) {
    $_SESSION['error'] = 'El enlace de compartición no es válido o ha expirado.';
    header('Location: index.php');
    exit;
}

// Obtener los documentos compartidos
$sql = "SELECT d.*, u.name as user_name 
        FROM share_documents sd 
        JOIN documents d ON sd.document_id = d.id 
        JOIN users u ON d.user_id = u.id 
        WHERE sd.share_id = ?";
$documents = $db->fetchAll($sql, [$share['id']]);

// Preparar URLs seguras para los archivos
foreach ($documents as &$doc) {
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
    <title>Archivos Compartidos - GestionUS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h1 class="text-lg leading-6 font-medium text-gray-900">
                        Archivos compartidos por <?php echo htmlspecialchars($share['shared_by_name'] ?? 'Un usuario'); ?>
                    </h1>
                    <?php if (!empty($share['message'])): ?>
                    <div class="mt-4 p-4 border rounded bg-gray-50">
                        <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($share['message'])); ?></p>
                    </div>
                    <?php endif; ?>
                    <p class="mt-2 text-sm text-gray-500">
                        Este enlace expirará el <?php echo date('d/m/Y', strtotime($share['expires_at'])); ?>
                    </p>
                </div>
                
                <div class="border-t border-gray-200">
                    <div class="px-4 py-5 sm:p-6">
                        <?php if (empty($documents)): ?>
                            <p class="text-center text-gray-500">No hay archivos disponibles.</p>
                        <?php else: ?>
                            <div class="flex justify-end mb-4">
                                <button id="download-all" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg class="h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Descargar todos
                                </button>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Nombre
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Tipo
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Tamaño
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Fecha
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($doc['original_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($doc['type']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo formatFileSize($doc['file_size']); ?>
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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
            });
        });
        
        // Descargar todos los archivos
        const downloadAllBtn = document.getElementById('download-all');
        if (downloadAllBtn) {
            downloadAllBtn.addEventListener('click', function() {
                const fileIds = Array.from(document.querySelectorAll('.file-checkbox')).map(checkbox => checkbox.value);
                
                if (fileIds.length === 0) {
                    alert('No hay archivos disponibles para descargar.');
                    return;
                }
                
                // Crear un formulario temporal para enviar la solicitud de descarga
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'api/documents.php?action=download_batch';
                form.style.display = 'none';
                
                fileIds.forEach(fileId => {
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
        }
    });
    </script>
</body>
</html>
